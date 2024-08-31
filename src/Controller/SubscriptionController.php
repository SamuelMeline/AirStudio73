<?php

namespace App\Controller;

use Stripe\Stripe;
use App\Entity\Plan;
use App\Entity\User;
use Stripe\PromotionCode;
use App\Entity\Subscription;
use App\Entity\PromoCodeUsage;
use App\Form\SubscriptionType;
use App\Entity\SubscriptionCourse;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Checkout\Session as StripeSession;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

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

            $user = $this->getUser();
            $plan = $form->get('plan')->getData();

            $subscription->setUser($user);
            $subscription->setPlan($plan);

            $promoCode = $form->get('promoCode')->getData();
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

        $subscription = new Subscription();
        $subscription->setUser($user);
        $subscription->setPlan($plan);


        // Vérification de l'endDate ici
        if ($plan->getEndDate() !== null) {
            $subscription->setExpiryDate($plan->getEndDate());
        } else {
            throw new \Exception("Plan does not have a valid end date.");
        }

        $subscription->setExpiryDate($plan->getEndDate()); // Correctement fixer la date d'expiration

        $subscription->setStripeSubscriptionId($session->subscription);
        $subscription->incrementPaymentsCount();
        $subscription->setMaxPayments($plan->getMaxPayments());
        $subscription->setPurchaseDate(new \DateTime());
        $subscription->setPromoCode($promoCode);
        $subscription->setPaymentMode($plan->getName());

        foreach ($plan->getPlanCourses() as $planCourse) {
            $subscriptionCourse = new SubscriptionCourse();
            $subscriptionCourse->setSubscription($subscription);
            $subscriptionCourse->setCourse($planCourse->getCourse());
            $subscriptionCourse->setRemainingCredits($planCourse->getCredits());
            $subscription->addSubscriptionCourse($subscriptionCourse);
        }

        $em->persist($subscription);

        if ($subscription->getMaxPayments() == 1) {
            $this->cancelStripeSubscription($subscription->getStripeSubscriptionId());
            $subscription->setIsActive(false);
        }

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
        $expiryDateFormatted = $subscription->getExpiryDate()->format('d/m/Y');
        $userEmailMessage = sprintf(
            'Bonjour,

Votre achat concernant l\'abonnement du forfait "%s" a bien été pris en compte et expirera le %s.
Nous vous remercions et vous souhaitons une très bonne journée.

Cordialement,
AirStudio73',
            $planName,
            $expiryDateFormatted
        );

        $this->sendEmail($userEmail, 'Confirmation d\'Achat de Forfait', $userEmailMessage);

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
