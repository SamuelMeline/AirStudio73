<?php

namespace App\Controller;

use Stripe\Stripe;
use Stripe\PromotionCode;
use App\Entity\Course;
use App\Entity\Subscription;
use App\Form\SubscriptionType;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Checkout\Session as StripeSession;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class SubscriptionController extends AbstractController
{
    private $courses = [
        'Pole Dance' => [
            'name' => 'Pole Dance',
            'durations' => [
                'pole_annuel_classique' => ['duration' => 'P1Y', 'stripe_price_id' => 'price_1Pf53cKVs2gnspmUvqqnQngR', 'courses' => 43],
                'pole_annuel_classique_3x' => ['duration' => 'P1Y', 'stripe_price_id' => 'price_1Pf77fKVs2gnspmUj9U3cD4T', 'courses' => 43],
                'pole_annuel_classique_4x' => ['duration' => 'P1Y', 'stripe_price_id' => 'price_1Pf79rKVs2gnspmUGQ5S9Uhl', 'courses' => 43],
                'pole_annuel_classique_10x' => ['duration' => 'P1Y', 'stripe_price_id' => 'price_1Pf7AMKVs2gnspmUF6o09Vz0', 'courses' => 43],
                'pole_annuel_classique_12x' => ['duration' => 'P1Y', 'stripe_price_id' => 'price_1Pf7AtKVs2gnspmUjkQuYmHT', 'courses' => 43],
                'pole_annuel_classique_activite' => ['duration' => 'P1Y', 'stripe_price_id' => 'price_1Pf7BUKVs2gnspmUisiEbRuJ', 'courses' => 43],
                'pole_annuel_classique_activite_3x' => ['duration' => 'P1Y', 'stripe_price_id' => 'price_1PfLNwKVs2gnspmU2qTTwR7s', 'courses' => 43],
                'pole_annuel_classique_activite_4x' => ['duration' => 'P1Y', 'stripe_price_id' => 'price_1PfLOdKVs2gnspmUsxC6FyQe', 'courses' => 43],
                'pole_annuel_classique_activite_10x' => ['duration' => 'P1Y', 'stripe_price_id' => 'price_1PfLP0KVs2gnspmUSp2SrroS', 'courses' => 43],
                'pole_annuel_classique_activite_12x' => ['duration' => 'P1Y', 'stripe_price_id' => 'price_1PfLPjKVs2gnspmUmTvysIwF', 'courses' => 43],
                'pole_souple' => ['duration' => 'P3M', 'stripe_price_id' => 'price_1PfLSIKVs2gnspmUl66eE8DY', 'courses' => 10],
                'pole_souple_2x' => ['duration' => 'P3M', 'stripe_price_id' => 'price_1PfLSmKVs2gnspmUQjYu5OT6', 'courses' => 10],
                'pole_cours_classique_et_essaie' => ['duration' => 'P1D', 'stripe_price_id' => 'price_1PfLQgKVs2gnspmUi7vsHkah', 'courses' => 1],
                'pole_cours_particulier' => ['duration' => 'P1D', 'stripe_price_id' => 'price_1PfLR2KVs2gnspmUKtqO0QNQ', 'courses' => 1],
            ],
        ],
        'Souplesse ou Renfo' => [
            'name' => 'Souplesse ou Renfo',
            'durations' => [
                'souplesse_annuel_classique' => ['duration' => 'P1Y', 'stripe_price_id' => 'price_1PfPDiKVs2gnspmUn0NJAjrR', 'courses' => 43],
                'souplesse_souple' => ['duration' => 'P3M', 'stripe_price_id' => 'price_1PfRLIKVs2gnspmUK9SNEKyo', 'courses' => 10],
            ],
        ],
    ];

    #[Route('/subscription/new', name: 'subscription_new')]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $subscription = new Subscription();
        $form = $this->createForm(SubscriptionType::class, $subscription);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            Stripe::setApiKey($this->getParameter('stripe.secret_key'));

            $forfait = $form->get('forfait')->getData();
            $promoCode = $form->get('promoCode')->getData();
            $courseId = $request->query->get('courseId');

            if (!$courseId) {
                $this->addFlash('error', 'Course ID is missing.');
                return $this->redirectToRoute('subscription_new');
            }

            // Récupérer le nom du cours à partir de l'ID du cours
            $course = $em->getRepository(Course::class)->find($courseId);

            if (!$course) {
                $this->addFlash('error', 'Invalid course ID.');
                return $this->redirectToRoute('subscription_new', ['courseId' => $courseId]);
            }

            $courseName = $course->getName();

            // Debugging info
            $this->addFlash('info', 'Course ID: ' . $courseId);
            $this->addFlash('info', 'Course Name: ' . $courseName);
            $this->addFlash('info', 'Forfait: ' . $forfait);

            // Assurez-vous que la clé existe dans le tableau avant de l'utiliser
            if (!isset($this->courses[$courseName]['durations'][$forfait])) {
                $this->addFlash('error', 'Invalid forfait selected.');
                return $this->redirectToRoute('subscription_new', ['courseId' => $courseId]);
            }

            $stripePriceId = $this->courses[$courseName]['durations'][$forfait]['stripe_price_id'];
            $numCourses = $this->courses[$courseName]['durations'][$forfait]['courses'];

            $discounts = [];
            if ($promoCode) {
                $promotionCodeId = $this->validatePromoCode($promoCode);
                if ($promotionCodeId) {
                    $discounts = [['promotion_code' => $promotionCodeId]];
                } else {
                    $this->addFlash('error', 'Invalid promo code.');
                    return $this->redirectToRoute('subscription_new', ['courseId' => $courseId]);
                }
            }

            $lineItem = [
                'price' => $stripePriceId,
                'quantity' => 1,
            ];

            $session = StripeSession::create([
                'payment_method_types' => ['card'],
                'line_items' => [$lineItem],
                'mode' => 'subscription',
                'discounts' => $discounts,
                'client_reference_id' => json_encode([
                    'forfait' => $forfait,
                    'userName' => $this->getUser()->getUserIdentifier(),
                    'promoCode' => $promoCode,
                    'courseId' => $courseId,
                ]),
                'success_url' => $this->generateUrl('subscription_success', [], UrlGeneratorInterface::ABSOLUTE_URL) . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $this->generateUrl('subscription_cancel', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ]);

            return $this->redirect($session->url);
        }

        return $this->render('subscription/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/subscription/success', name: 'subscription_success')]
    #[IsGranted('ROLE_USER')]
    public function success(Request $request, EntityManagerInterface $em): Response
    {
        $sessionId = $request->query->get('session_id');
        if (!$sessionId) {
            $this->addFlash('error', 'Session ID is missing.');
            return $this->redirectToRoute('subscription_new');
        }

        Stripe::setApiKey($this->getParameter('stripe.secret_key'));

        $session = StripeSession::retrieve($sessionId);
        if (!$session) {
            $this->addFlash('error', 'Session not found.');
            return $this->redirectToRoute('subscription_new');
        }

        $sessionData = json_decode($session->client_reference_id, true);
        if (!$sessionData) {
            $this->addFlash('error', 'Session data is missing.');
            return $this->redirectToRoute('subscription_new');
        }

        $forfait = $sessionData['forfait'] ?? null;
        $userName = $sessionData['userName'] ?? null;
        $promoCode = $sessionData['promoCode'] ?? null;
        $courseId = $sessionData['courseId'] ?? null;

        if (!$forfait || !$userName || !$courseId) {
            $this->addFlash('error', 'Invalid session data.');
            return $this->redirectToRoute('subscription_new');
        }

        $course = $em->getRepository(Course::class)->find($courseId);
        if (!$course) {
            $this->addFlash('error', 'Invalid course ID.');
            return $this->redirectToRoute('subscription_new', ['courseId' => $courseId]);
        }

        $courseName = $course->getName();
        if (!isset($this->courses[$courseName]['durations'][$forfait])) {
            $this->addFlash('error', 'Invalid forfait selected.');
            return $this->redirectToRoute('subscription_new', ['courseId' => $courseId]);
        }

        $existingSubscription = $em->getRepository(Subscription::class)->findOneBy([
            'userName' => $userName,
            'forfait' => $forfait
        ]);

        $expiryDate = (new \DateTime())->add(new \DateInterval($this->courses[$courseName]['durations'][$forfait]['duration']));

        if ($existingSubscription) {
            // Incrémenter les crédits de l'abonnement existant
            $existingSubscription->setRemainingCourses(
                $existingSubscription->getRemainingCourses() + $this->courses[$courseName]['durations'][$forfait]['courses']
            );

            // Mettre à jour la date d'expiration si elle est plus tardive
            if ($existingSubscription->getExpiryDate() < $expiryDate) {
                $existingSubscription->setExpiryDate($expiryDate);
            }

            $em->persist($existingSubscription);
        } else {
            $subscription = new Subscription();
            $subscription->setUserName($userName);
            $subscription->setForfait($forfait);
            $subscription->setRemainingCourses($this->courses[$courseName]['durations'][$forfait]['courses']);
            $subscription->setPurchaseDate(new \DateTime());
            $subscription->setExpiryDate($expiryDate);
            $subscription->setPromoCode($promoCode);
            $subscription->setPaymentMode($forfait);

            $em->persist($subscription);
        }

        if (strpos($forfait, 'pole_annuel_classique_activite') !== false) {
            $existingSouplesseSubscription = $em->getRepository(Subscription::class)->findOneBy([
                'userName' => $userName,
                'forfait' => 'souplesse_annuel_classique'
            ]);

            if ($existingSouplesseSubscription) {
                $existingSouplesseSubscription->setRemainingCourses(
                    $existingSouplesseSubscription->getRemainingCourses() + 43
                );

                if ($existingSouplesseSubscription->getExpiryDate() < $expiryDate) {
                    $existingSouplesseSubscription->setExpiryDate($expiryDate);
                }

                $em->persist($existingSouplesseSubscription);
            } else {
                $subscriptionSouplesse = new Subscription();
                $subscriptionSouplesse->setUserName($userName);
                $subscriptionSouplesse->setForfait('souplesse_annuel_classique');
                $subscriptionSouplesse->setRemainingCourses(43);
                $subscriptionSouplesse->setPurchaseDate(new \DateTime());
                $subscriptionSouplesse->setExpiryDate($expiryDate);
                $subscriptionSouplesse->setPromoCode($promoCode);
                $subscriptionSouplesse->setPaymentMode('souplesse_annuel_classique');

                $em->persist($subscriptionSouplesse);
            }
        }

        $em->flush();

        // Supprimer les abonnements avec des crédits restants égaux à 0
        $subscriptions = $em->getRepository(Subscription::class)->findBy(['userName' => $userName]);
        foreach ($subscriptions as $subscription) {
            if ($subscription->getRemainingCourses() == 0) {
                $em->remove($subscription);
            }
        }

        $em->flush();

        $this->addFlash('success', 'Your subscription has been successfully created.');

        return $this->redirectToRoute('user_subscription');
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

    #[Route('/subscription/cancel', name: 'subscription_cancel')]
    public function cancel(): Response
    {
        $this->addFlash('error', 'The payment was canceled.');
        return $this->redirectToRoute('subscription_new');
    }
}
