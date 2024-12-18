<?php

namespace App\Controller;

use Stripe\Stripe;
use App\Entity\Plan;
use App\Entity\User;
use Stripe\PromotionCode;
use App\Entity\PlanCourse;
use App\Entity\Subscription;
use App\Entity\PromoCodeUsage;
use App\Form\SubscriptionType;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Mime\Email;
use App\Entity\SubscriptionCourse;
use App\Repository\PlanRepository;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Checkout\Session as StripeSession;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class SubscriptionController extends AbstractController
{
    private const SENDER_EMAIL = 'contactAirstudio73@gmail.com';

    #[Route('/subscription/remaining-credits', name: 'fetch_remaining_credits', methods: ['POST'])]
    public function fetchRemainingCredits(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $planId = $data['planId'] ?? null;

        if (!$planId) {
            return new JsonResponse(['error' => 'Plan ID is required'], 400);
        }

        $plan = $em->getRepository(Plan::class)->find($planId);

        if (!$plan) {
            return new JsonResponse(['error' => 'Plan not found'], 404);
        }

        // Calculer les crédits restants
        $remainingCredits = $this->adjustSubscriptionCredits($plan);

        return new JsonResponse(['remainingCredits' => $remainingCredits]);
    }

    #[Route('/subscription/new', name: 'subscription_new')]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $subscription = new Subscription();
        $form = $this->createForm(SubscriptionType::class, $subscription, ['em' => $em]);

        $form->handleRequest($request);

        $user = $this->getUser();

        // Si le formulaire est soumis
        if ($form->isSubmitted()) {

            // Vérifiez si le champ 'paymentInstallments' existe dans le formulaire et s'il est défini
            if (!$form->has('paymentInstallments') || !$form->get('paymentInstallments')->getData()) {
                // Si le champ 'paymentInstallments' n'est pas défini ou vide, revenir au formulaire
                return $this->render('subscription/new.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            // Récupérer le plan choisi par l'utilisateur
            $plan = $form->get('plan')->getData();
            $subscriptionType = $plan->getSubscriptionType();

            // Vérifier si le plan est expiré
            $currentDate = new \DateTime();
            $expiryDate = $plan->getEndDate();

            if ($expiryDate < $currentDate) {
                $this->addFlash('error', 'Ce forfait est expiré et ne peut plus être souscrit.');
                return $this->redirectToRoute('subscription_new');
            }

            // Vérification des crédits restants et duplication d'abonnement en une seule requête
            $existingSubscriptions = $em->getRepository(Subscription::class)->createQueryBuilder('s')
                ->leftJoin('s.subscriptionCourses', 'sc')
                ->where('s.user = :user')
                ->andWhere('s.plan = :plan') // Vérification d'un abonnement en double
                ->setParameter('user', $user)
                ->setParameter('plan', $plan)
                ->getQuery()
                ->getResult(); // Utilisation de getResult() pour gérer plusieurs résultats

            // Si l'utilisateur a des abonnements qui répondent aux critères
            if (count($existingSubscriptions) > 0) {
                // Si l'utilisateur a déjà souscrit à ce plan et que ce n'est pas renouvelable
                if (strpos($subscriptionType, 'renewable') === false) {
                    $this->addFlash('error', 'Vous ne pouvez pas souscrire deux fois à cet abonnement.');
                    return $this->redirectToRoute('subscription_new');
                }
            }

            // Si tout est bon, traiter la soumission du formulaire et la création de la session Stripe
            if ($form->isValid()) {
                Stripe::setApiKey($this->getParameter('stripe.secret_key'));

                // Associer le plan à la souscription
                $subscription->setUser($user);
                $subscription->setPlan($plan);

                // Définir les dates de début et de fin de la souscription depuis le plan
                if ($plan->getStartDate() !== null) {
                    $subscription->setStartDate($plan->getStartDate());
                }

                if ($plan->getEndDate() !== null) {
                    $subscription->setExpiryDate($plan->getEndDate());
                }

                // Gérer le code promo s'il est présent
                $promoCode = $form->get('promoCode')->getData();
                $stripePriceId = $plan->getStripePriceId();

                $discounts = [];

                if ($promoCode) {
                    // Vérifier si l'utilisateur a déjà utilisé ce code promo
                    $existingPromoUsage = $em->getRepository(Subscription::class)->createQueryBuilder('s')
                        ->where('s.user = :user')
                        ->andWhere('s.promoCode = :promoCode') // Vérifier le code promo
                        ->setParameter('user', $user)
                        ->setParameter('promoCode', $promoCode)
                        ->getQuery()
                        ->getResult();

                    // Si le code promo a déjà été utilisé, on bloque l'utilisation
                    if (count($existingPromoUsage) > 0) {
                        $this->addFlash('error', 'Vous avez déjà utilisé ce code promo.');
                        return $this->redirectToRoute('subscription_new');
                    }

                    // Si le code promo est valide, on l'applique
                    $promotionCodeId = $this->validatePromoCode($promoCode);
                    if ($promotionCodeId) {
                        $discounts = [['promotion_code' => $promotionCodeId]];
                    } else {
                        $this->addFlash('error', 'Le code promo est invalide');
                        return $this->redirectToRoute('subscription_new');
                    }
                }

                // Calculer les crédits restants
                $remainingCredits = $this->adjustSubscriptionCredits($plan);


                // Gérer le choix du nombre de paiements
                $installments = $form->get('paymentInstallments')->getData() ?: 1;

                // Calculer le prix ajusté en fonction du temps restant
                $newAmount = $this->adjustSubscriptionPrice($plan, $subscription, $remainingCredits, $installments, $em);

                $amountPerInstallment = round($newAmount / $installments);

                // Mettre à jour la propriété maxPayments
                $subscription->setMaxPayments($installments);

                // Si paiement en plusieurs fois, on configure l'abonnement en mode récurrent
                if ($installments > 1) {
                    $plan->setIsRecurring(true);
                    $subscription->setMaxPayments($installments);
                } else {
                    $plan->setIsRecurring(false);
                    $subscription->setMaxPayments(1);
                }

                // Créer un prix dynamique dans Stripe avec le montant ajusté
                $price = \Stripe\Price::create([
                    'unit_amount' => $amountPerInstallment, // Le prix ajusté par versement
                    'currency' => 'eur',
                    'product' => $plan->getStripeProductId(), // L'ID du produit Stripe
                    'recurring' => $installments > 1 ? ['interval' => 'month'] : null, // Réccurrence si paiement en plusieurs fois
                ]);

                // Utiliser le nouveau prix créé dans Stripe
                $stripePriceId = $price->id;

                // Déterminer le mode en fonction du nombre de paiements
                $mode = ($installments > 1) ? 'subscription' : 'payment';

                // Création de la session Stripe
                $session = StripeSession::create([
                    'payment_method_types' => ['card'],
                    'line_items' => [[
                        'price' => $stripePriceId, // Utiliser le prix dynamique créé
                        'quantity' => 1,
                    ]],
                    'mode' => $mode, // 'payment' pour un paiement unique, 'subscription' pour plusieurs paiements
                    'discounts' => $discounts,
                    'client_reference_id' => json_encode([
                        'planId' => $plan->getId(),
                        'userId' => $user->getUserIdentifier(),
                        'promoCode' => $promoCode,
                        'installments' => $installments,
                    ]),
                    'success_url' => $this->generateUrl('subscription_success', [], UrlGeneratorInterface::ABSOLUTE_URL) . '?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => $this->generateUrl('subscription_cancel', [], UrlGeneratorInterface::ABSOLUTE_URL),
                ]);

                return $this->redirect($session->url);
            }
        }


        return $this->render('subscription/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    public function adjustSubscriptionCredits(Plan $plan): int
    {
        $planCourses = $plan->getPlanCourses();

        if (empty($planCourses) || !isset($planCourses[0])) {
            throw new \Exception('Aucun cours associé à ce plan.');
        }

        $totalCredits = $planCourses[0]->getCredits(); // Récupérer le total des crédits du plan
        $currentDate = new \DateTime();
        $startDate = $plan->getStartDate();

        if ($startDate === null) {
            throw new \Exception('La date de début du plan est introuvable.');
        }

        // Si le forfait est acheté un dimanche, on n'attribue pas de crédits pour cette semaine
        if ($currentDate->format('w') == 0) {
            $daysInAWeek = 6;
            $weeksElapsed = floor($startDate->diff($currentDate)->days / $daysInAWeek);
        } else {
            // Calculer la différence en semaines
            $daysInAWeek = 7;
            $weeksElapsed = floor($startDate->diff($currentDate)->days / $daysInAWeek);
        }

        // Si la date de début est dans le futur, forcer à 0
        if ($startDate > $currentDate) {
            $weeksElapsed = 0;
        }

        // // Ajustement en fonction du type d'abonnement
        // if ($plan->getSubscriptionType() === 'weekly' || $plan->getSubscriptionType() === 'bi-weekly') {
        //     $remainingCredits = max(0, $totalCredits - $weeksElapsed);
        // } else {
        //     $remainingCredits = $totalCredits;
        // }

        if ($plan->getSubscriptionType() === 'bi-weekly') {
            $planCourses = $plan->getPlanCourses();
            $numberOfCourses = count($planCourses); // Compte le nombre de cours associés au plan

            if ($numberOfCourses == 1) {
                // Si un seul cours, on retire 2 crédits par semaine
                foreach ($planCourses as $planCourse) {
                    $remainingCredits = max(0, $planCourse->getCredits() - ($weeksElapsed * 2));
                }
            } else {
                // Si deux cours ou plus, on retire 1 crédit par cours, par semaine
                foreach ($planCourses as $planCourse) {
                    $remainingCredits = max(0, $planCourse->getCredits() - $weeksElapsed);
                }
            }
        } elseif ($plan->getSubscriptionType() === 'weekly') {
            // Si l'abonnement est weekly, on retire 1 crédit par cours par semaine
            foreach ($plan->getPlanCourses() as $planCourse) {
                $remainingCredits = max(0, $planCourse->getCredits() - $weeksElapsed);
            }
        } else {
            $remainingCredits = $totalCredits;
        }

        return $remainingCredits;
    }

    public function adjustSubscriptionPrice(Plan $plan, Subscription $subscription, int $remainingCredits, int $paymentInstallments, EntityManagerInterface $em): int
    {
        $initialTotalPrice = 0;
        $pricePerCredit = 0;

        // Calculer le prix total pour tous les crédits du plan, en centimes
        foreach ($plan->getPlanCourses() as $planCourse) {
            $initialTotalPrice += $planCourse->getPricePerCredit() * $planCourse->getCredits() * 100;
            $pricePerCredit += $planCourse->getPricePerCredit();
        }

        $currentDate = new \DateTime();
        $expiryDate = $subscription->getExpiryDate();

        // Calculer le nombre de mois restants jusqu'à la date d'expiration
        $interval = $expiryDate->diff($currentDate);
        $monthsRemaining = $interval->m + ($interval->y * 12);

        // Ajouter un mois si le mois en cours est incomplet
        if ($interval->d > 0) {
            $monthsRemaining++;
        }

        // Calculer le prix ajusté en fonction des crédits restants, en centimes
        $adjustedPrice = $remainingCredits * $pricePerCredit * 100;

        if ($paymentInstallments > 3) {
            // Ajuster le nombre de paiements en fonction des mois restants
            $maxPayments = min($paymentInstallments, $monthsRemaining);

            // Calculer le montant par versement avec l'ajustement nécessaire
            $amountPerInstallment = ($adjustedPrice / $maxPayments) * 10;
        } else {
            // Montant par versement pour un paiement unique ou jusqu'à 3 fois
            $amountPerInstallment = $adjustedPrice;
        }

        // Arrondir et retourner le montant par versement en centimes
        return round($amountPerInstallment);
    }

    #[Route('/check-plan-expiry', name: 'check_plan_expiry', methods: ['POST'])]
    public function checkPlanExpiry(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $planId = $data['planId'] ?? null;

        if (!$planId) {
            return new JsonResponse(['error' => 'Plan ID is required'], 400);
        }

        $plan = $em->getRepository(Plan::class)->find($planId);

        if (!$plan) {
            return new JsonResponse(['error' => 'Plan not found'], 404);
        }

        $currentDate = new \DateTime();
        $expiryDate = $plan->getEndDate();
        $warningPeriod = new \DateInterval('P14D');
        $warningDate = (clone $currentDate)->add($warningPeriod);

        if ($expiryDate <= $warningDate && $expiryDate > $currentDate) {
            return new JsonResponse([
                'warning' => sprintf(
                    'Attention, ce forfait expire le %s. Si vous achetez ce forfait, vous ne pourrez pas utiliser vos crédits après cette date.',
                    $expiryDate->format('d/m/Y')
                )
            ]);
        }

        return new JsonResponse(['warning' => null]);
    }

    #[Route('/subscription/success', name: 'subscription_success')]
    #[IsGranted('ROLE_USER')]
    public function success(Request $request, EntityManagerInterface $em): Response
    {
        // Récupération de l'ID de session Stripe depuis les paramètres de la requête
        $sessionId = $request->query->get('session_id');
        if (!$sessionId) {
            $this->addFlash('error', 'L\'ID de la session n\'existe pas.');
            return $this->redirectToRoute('subscription_new');
        }

        // Configuration de l'API Stripe
        Stripe::setApiKey($this->getParameter('stripe.secret_key'));

        // Récupération de la session Stripe
        $session = StripeSession::retrieve($sessionId);
        if (!$session) {
            $this->addFlash('error', 'La session n\'existe pas.');
            return $this->redirectToRoute('subscription_new');
        }

        // Vérification et décryptage des données client dans la session Stripe
        $sessionData = json_decode($session->client_reference_id, true);
        if (!$sessionData) {
            $this->addFlash('error', 'Les données de session sont manquantes.');
            return $this->redirectToRoute('subscription_new');
        }

        // Extraction des données de la session
        $planId = $sessionData['planId'] ?? null;
        $userId = $sessionData['userId'] ?? null;
        $promoCode = $sessionData['promoCode'] ?? null;
        $installments = $sessionData['installments'] ?? 1;

        // Vérification de la validité des données
        if (!$planId || !$userId) {
            $this->addFlash('error', 'Données de session invalides.');
            return $this->redirectToRoute('subscription_new');
        }

        // Recherche du plan et de l'utilisateur dans la base de données
        $plan = $em->getRepository(Plan::class)->find($planId);
        $user = $em->getRepository(User::class)->findOneBy(['email' => $userId]);

        if (!$plan || !$user) {
            $this->addFlash('error', 'Utilisateur ou forfait invalide.');
            return $this->redirectToRoute('subscription_new');
        }

        // Création de la nouvelle souscription
        $subscription = new Subscription();
        $subscription->setUser($user);
        $subscription->setPlan($plan);

        // Vérification et définition de la date de fin du plan
        if ($plan->getEndDate() !== null) {
            $subscription->setExpiryDate($plan->getEndDate());
        } else {
            throw new \Exception("La souscription ne peut pas être validée car la date de fin du forfait est déjà passée.");
        }

        // Vérification de l'ID d'abonnement Stripe pour un abonnement récurrent
        if (isset($session->subscription) && !empty($session->subscription)) {
            // C'est un abonnement récurrent
            $subscription->setStripeSubscriptionId($session->subscription);
        } else {
            // Si c'est un paiement unique, l'ID d'abonnement est vide ou une valeur par défaut est utilisée
            $subscription->setStripeSubscriptionId(''); // Vous pouvez mettre une logique alternative si nécessaire
        }

        $currentDate = new \DateTime();

        // Calcul des crédits restants en fonction du temps écoulé
        $remainingCredits = $this->adjustSubscriptionCredits($plan);

        // Définition des autres détails de la souscription
        $subscription->incrementPaymentsCount();

        // Vérification du nombre d'échéances (installments)
        if ($installments > 3) {
            // Calcul du nombre de paiements en fonction des mois restants
            $currentDate = new \DateTime();
            $expiryDate = $subscription->getExpiryDate();
            // Inclure le mois en cours et le dernier mois dans le calcul
            $monthsRemaining = $expiryDate->diff($currentDate)->m + ($expiryDate->diff($currentDate)->y * 12) + 1;

            // Ajuster le nombre de paiements à la valeur minimale entre les échéances choisies et les mois restants
            $maxPayments = min($installments, $monthsRemaining);

            // Mettre à jour maxPayments dans l'entité Subscription
            $subscription->setMaxPayments($maxPayments);
        } else {
            // Sinon, on garde le nombre de paiements tel quel
            $subscription->setMaxPayments($installments);
        }

        $subscription->setPurchaseDate(new \DateTime());
        $subscription->setPromoCode($promoCode);
        $subscription->setPaymentMode($plan->getName());

        // Association des cours du plan à la souscription
        foreach ($plan->getPlanCourses() as $planCourse) {
            $subscriptionCourse = new SubscriptionCourse();

            if ($plan->getSubscriptionType() === 'souple') {
                // Si l'abonnement est de type "souple"
                // Ne pas limiter les réservations et ajuster la période de validité à un trimestre ou à la date d'expiration
                $subscriptionStartDate = $subscription->getStartDate();

                // Vérifier que la date de début est bien un objet DateTime
                if (!$subscriptionStartDate instanceof \DateTime) {
                    throw new \Exception('La date de début de l\'abonnement n\'est pas valide.');
                }

                // Calcul du trimestre : ajouter 3 mois à la date de début
                $trimestreEndDate = (clone $subscriptionStartDate)->modify('+3 months');

                // Comparer la date de fin du plan avec la date de fin de trimestre
                $finalEndDate = $plan->getEndDate() < $trimestreEndDate ? $plan->getEndDate() : $trimestreEndDate;

                // Vérification si l'abonnement est expiré
                if ($currentDate > $finalEndDate) {
                    $this->addFlash('error', 'Votre abonnement souple a expiré.');
                    return $this->redirectToRoute('subscription_new');
                }

                // Mettre à jour la date d'expiration de l'abonnement
                $subscription->setExpiryDate($finalEndDate);
            }

            $subscriptionCourse->setSubscription($subscription);
            $subscriptionCourse->setCourse($planCourse->getCourse());
            $subscriptionCourse->setRemainingCredits($remainingCredits);
            $subscription->addSubscriptionCourse($subscriptionCourse);
        }

        // Persistance de la souscription dans la base de données
        $em->persist($subscription);

        // Enregistrement final de toutes les données
        $em->flush();

        // Affichage du message de succès
        $this->addFlash('success', 'Votre forfait a bien été créé, vous pouvez maintenant réserver.');

        // Envoyer un email de confirmation à l'utilisateur
        $userEmail = $user->getEmail();
        $planName = $plan->getName();
        $expiryDateFormatted = $subscription->getExpiryDate()->format('d/m/Y');
        $userEmailMessage = sprintf(
            'Bonjour %s,
    
    Votre achat concernant l\'abonnement du forfait "%s" a bien été pris en compte et expirera le %s.
    Nous vous remercions et vous souhaitons une très bonne journée.
    
    Cordialement,
    AirStudio73',
            $user->getFirstName(),
            $planName,
            $expiryDateFormatted
        );

        // Envoi de l'email de confirmation
        $this->sendEmail($userEmail, 'Confirmation d\'Achat de Forfait', $userEmailMessage);

        // Redirection vers le calendrier après succès
        return $this->redirectToRoute('calendar');
    }

    #[Route('/subscription/cancel', name: 'subscription_cancel')]
    public function cancel(): Response
    {
        $this->addFlash('error', 'Le paiement a été annulé.');
        return $this->redirectToRoute('subscription_new');
    }

    private function cancelStripeSubscription(string $subscriptionId): void
    {
        Stripe::setApiKey($this->getParameter('stripe.secret_key'));

        $subscription = \Stripe\Subscription::retrieve($subscriptionId);
        $subscription->cancel();
    }

    private function validatePromoCode(string $promoCode): ?string
    {
        try {
            $promotionCodes = PromotionCode::all([
                'code' => $promoCode,
                'active' => true,
                'limit' => 1,
            ]);
            foreach ($promotionCodes->data as $promotionCode) {
                if ($promotionCode->coupon->valid) {
                    return $promotionCode->id;
                }
            }
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function sendEmail(string $to, string $subject, string $message): void
    {
        $email = (new Email())
            ->from(self::SENDER_EMAIL)
            ->replyTo(self::SENDER_EMAIL)
            ->to($to)
            ->subject($subject)
            ->text($message);

        try {
            $transport = Transport::fromDsn('smtp://contactAirstudio73@gmail.com:ofnlzwlcprshxdmv@smtp.gmail.com:587');
            $mailer = new Mailer($transport);
            $mailer->send($email);
        } catch (\Exception $e) {
            // Gérer l'erreur d'envoi d'email si nécessaire
        }
    }
}
