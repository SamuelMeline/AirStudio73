<?php

namespace App\Controller;

use Stripe\Stripe;
use Stripe\Event;
use App\Entity\Plan;
use App\Entity\User;
use App\Entity\Course;
use Stripe\Webhook;
use Stripe\PromotionCode;
use App\Entity\Subscription;
use App\Entity\PromoCodeUsage;
use App\Form\SubscriptionType;
use App\Entity\SubscriptionCourse;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Checkout\Session as StripeSession;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

class SubscriptionController extends AbstractController
{
    private const SENDER_EMAIL = 'contactAirstudio73@gmail.com';

    #[Route('/subscription/new', name: 'subscription_new')]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $subscription = new Subscription();
        $form = $this->createForm(SubscriptionType::class, $subscription);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            Stripe::setApiKey($this->getParameter('stripe.secret_key'));

            $plan = $subscription->getPlan();
            $promoCode = $form->get('promoCode')->getData();
            $user = $this->getUser();

            // Vérifier si l'utilisateur a déjà utilisé ce code promo
            $existingUsage = $em->getRepository(PromoCodeUsage::class)
                ->findOneBy(['user' => $user, 'promoCode' => $promoCode]);

            if ($existingUsage) {
                $this->addFlash('error', 'You have already used this promo code.');
                return $this->redirectToRoute('subscription_new');
            }

            $stripePriceId = $plan->getStripePriceId();

            $discounts = [];
            if ($promoCode) {
                $promotionCodeId = $this->validatePromoCode($promoCode);
                if ($promotionCodeId) {
                    $discounts = [['promotion_code' => $promotionCodeId]];
                } else {
                    $this->addFlash('error', 'Invalid promo code.');
                    return $this->redirectToRoute('subscription_new');
                }
            }

            $lineItem = [
                'price' => $stripePriceId,
                'quantity' => 1,
            ];

            // Forcer l'utilisation du mode "subscription" pour Stripe
            $session = StripeSession::create([
                'payment_method_types' => ['card'],
                'line_items' => [$lineItem],
                'mode' => 'subscription',
                'discounts' => $discounts,
                'client_reference_id' => json_encode([
                    'planId' => $plan->getId(),
                    'userId' => $user->getUserIdentifier(),
                    'promoCode' => $promoCode,
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
    public function success(Request $request, EntityManagerInterface $em, LoggerInterface $logger): Response
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

        $planId = $sessionData['planId'] ?? null;
        $userId = $sessionData['userId'] ?? null;
        $promoCode = $sessionData['promoCode'] ?? null;

        if (!$planId || !$userId) {
            $this->addFlash('error', 'Invalid session data.');
            return $this->redirectToRoute('subscription_new');
        }

        $plan = $em->getRepository(Plan::class)->find($planId);
        $user = $em->getRepository(User::class)->findOneBy(['email' => $userId]);

        if (!$plan || !$user) {
            $this->addFlash('error', 'Invalid plan or user.');
            return $this->redirectToRoute('subscription_new');
        }

        $expiryDate = (new \DateTime())->add(new \DateInterval($plan->getDuration()));

        // Créer un nouvel abonnement, même s'il en existe déjà un du même type
        $subscription = new Subscription();
        $subscription->setUser($user);
        $subscription->setPlan($plan);
        $subscription->setStripeSubscriptionId($session->subscription);
        $subscription->incrementPaymentsCount();
        $subscription->setMaxPayments($plan->getMaxPayments());

        foreach ($plan->getPlanCourses() as $planCourse) {
            $subscriptionCourse = new SubscriptionCourse();
            $subscriptionCourse->setSubscription($subscription);
            $subscriptionCourse->setCourse($planCourse->getCourse());
            $subscriptionCourse->setRemainingCredits($planCourse->getCredits());
            $subscription->addSubscriptionCourse($subscriptionCourse);
        }

        $subscription->setPurchaseDate(new \DateTime());
        $subscription->setExpiryDate($expiryDate);
        $subscription->setPromoCode($promoCode);
        $subscription->setPaymentMode($plan->getName());

        $em->persist($subscription);

        // Annuler immédiatement l'abonnement si max_payments est 1
        if ($subscription->getMaxPayments() == 1) {
            $this->cancelStripeSubscription($subscription->getStripeSubscriptionId());
            $subscription->setIsActive(false);
            $subscription->setExpiryDate(new \DateTime());
        }

        // Enregistrer l'utilisation du code promo
        if ($promoCode) {
            $promoCodeUsage = new PromoCodeUsage();
            $promoCodeUsage->setUser($user);
            $promoCodeUsage->setPromoCode($promoCode);
            $promoCodeUsage->setUsedAt(new \DateTime());

            $em->persist($promoCodeUsage);
        }

        $em->flush();

        $this->addFlash('success', 'Your subscription has been successfully created.');

        $userEmail = $user->getEmail();
        $planName = $plan->getName();
        $expiryDateFormatted = $expiryDate->format('d/m/Y');
        $userEmailMessage = sprintf(
            'Bonjour,

Votre achat concernant l\'abonnement du forfait "%s" a bien été pris en compte et expirera le %s.
Nous vous remercions et vous souhaitons une très bonne journée.

Cordialement,
AirStudio73
        ',
            $planName,
            $expiryDateFormatted
        );

        $this->sendEmail($userEmail, 'Confirmation d\'Achat de Forfait', $userEmailMessage, $logger);

        return $this->redirectToRoute('user_subscription');
    }

    #[Route('/subscription/cancel', name: 'subscription_cancel')]
    public function cancel(): Response
    {
        $this->addFlash('error', 'The payment was canceled.');
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

    private function sendEmail(string $to, string $subject, string $message, LoggerInterface $logger): void
    {
        $email = (new Email())
            ->from(self::SENDER_EMAIL)
            ->replyTo(self::SENDER_EMAIL)
            ->to($to)
            ->subject($subject)
            ->text($message);

        try {
            $logger->info('Sending email to: ' . $to);
            $transport = Transport::fromDsn('smtp://contactAirstudio73@gmail.com:ofnlzwlcprshxdmv@smtp.gmail.com:587');
            $mailer = new Mailer($transport);
            $mailer->send($email);
            $logger->info('Email sent successfully to: ' . $to);
        } catch (\Exception $e) {
            $logger->error('Failed to send email to ' . $to . ': ' . $e->getMessage());
        }
    }
}
