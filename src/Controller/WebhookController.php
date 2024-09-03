<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Stripe\Webhook;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use App\Entity\Subscription;

class WebhookController extends AbstractController
{
    private $stripeEndpointSecret;
    private $entityManager;
    private $logger;

    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $logger)
    {
        $this->stripeEndpointSecret = $_ENV['STRIPE_ENDPOINT_SECRET'];
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    #[Route('/stripe/webhook', name: 'stripe_webhook', methods: ['POST'])]
    public function handleStripeWebhook(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->headers->get('stripe-signature');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $this->stripeEndpointSecret);
        } catch (\UnexpectedValueException $e) {
            $this->logger->error('Invalid payload', ['error' => $e->getMessage()]);
            return new Response('Invalid payload', Response::HTTP_BAD_REQUEST);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            $this->logger->error('Invalid signature', ['error' => $e->getMessage()]);
            return new Response('Invalid signature', Response::HTTP_BAD_REQUEST);
        }

        $this->logger->info('Received event', ['type' => $event->type]);

        switch ($event->type) {
            case 'invoice.payment_succeeded':
                $this->handleInvoicePaymentSucceeded($event->data->object);
                break;
            case 'customer.subscription.updated':
                $this->handleSubscriptionUpdated($event->data->object);
                break;
            case 'customer.subscription.deleted':
                $this->handleSubscriptionDeleted($event->data->object);
                break;
            case 'checkout.session.completed':
                $this->handleCheckoutSessionCompleted($event->data->object);
                break;
            case 'payment_method.attached':
                $this->handlePaymentMethodAttached($event->data->object);
                break;
            case 'charge.succeeded':
                $this->handleChargeSucceeded($event->data->object);
                break;
            case 'customer.subscription.created':
                $this->logger->warning('Received customer.subscription.created, but no handling is implemented.');
                break;
            case 'invoice.upcoming':
                $this->logger->warning('Received invoice.upcoming, but no handling is implemented.');
                break;
            case 'test_helpers.test_clock.created':
            case 'test_helpers.test_clock.ready':
                $this->logger->warning('Test clock event received, but no handling is implemented.', ['type' => $event->type]);
                break;
            default:
                $this->logger->warning('Unhandled event type', ['type' => $event->type]);
                return new Response(sprintf('Unhandled event type: %s', $event->type));
        }

        return new Response('Success', Response::HTTP_OK);
    }

    private function handleInvoicePaymentSucceeded($invoice)
    {
        $subscriptionId = $invoice->subscription;

        $this->logger->info('Processing invoice.payment_succeeded', ['subscription_id' => $subscriptionId]);

        $subscription = $this->entityManager->getRepository(Subscription::class)->findOneBy([
            'stripeSubscriptionId' => $subscriptionId,
        ]);

        if (!$subscription) {
            $this->logger->error('Subscription not found', ['subscription_id' => $subscriptionId]);
            return;
        }

        $this->logger->info('Subscription found', [
            'payments_count' => $subscription->getPaymentsCount(),
            'is_active' => $subscription->getIsActive(),
        ]);

        $subscription->incrementPaymentsCount();

        $this->logger->info('Payments count after increment', ['payments_count' => $subscription->getPaymentsCount()]);

        if ($subscription->getPaymentsCount() >= $subscription->getMaxPayments()) {
            $subscription->setIsActive(false);

            try {
                \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);
                $stripeSubscription = \Stripe\Subscription::retrieve($subscriptionId);
                $stripeSubscription->cancel();
                $this->logger->info('Subscription cancelled on Stripe', ['subscription_id' => $subscriptionId]);
            } catch (\Stripe\Exception\ApiErrorException $e) {
                $this->logger->error('Failed to cancel subscription on Stripe', ['error' => $e->getMessage()]);
                return new Response('Failed to cancel subscription on Stripe: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        $this->entityManager->flush();
        $this->logger->info('Subscription updated successfully', [
            'payments_count' => $subscription->getPaymentsCount(),
            'is_active' => $subscription->getIsActive(),
        ]);
    }

    private function handleSubscriptionUpdated($subscription)
    {
        $subscriptionId = $subscription->id;

        $this->logger->info('Processing customer.subscription.updated', ['subscription_id' => $subscriptionId]);

        $existingSubscription = $this->entityManager->getRepository(Subscription::class)->findOneBy([
            'stripeSubscriptionId' => $subscriptionId,
        ]);

        if (!$existingSubscription) {
            $this->logger->error('Subscription not found', ['subscription_id' => $subscriptionId]);
            return;
        }

        $newExpiryDate = new \DateTime('@' . $subscription->current_period_end);

        $existingSubscription->setExpiryDate($newExpiryDate);
        $existingSubscription->setIsActive($subscription->status === 'active');

        $this->entityManager->flush();
        $this->logger->info('Subscription updated successfully', [
            'expiry_date' => $existingSubscription->getExpiryDate()->format('Y-m-d H:i:s'),
            'is_active' => $existingSubscription->getIsActive(),
        ]);
    }

    private function handleSubscriptionDeleted($subscription)
    {
        $subscriptionId = $subscription->id;

        $this->logger->info('Processing customer.subscription.deleted', ['subscription_id' => $subscriptionId]);

        $existingSubscription = $this->entityManager->getRepository(Subscription::class)->findOneBy([
            'stripeSubscriptionId' => $subscriptionId,
        ]);

        if (!$existingSubscription) {
            $this->logger->error('Subscription not found', ['subscription_id' => $subscriptionId]);
            return;
        }

        $existingSubscription->setIsActive(false);
        $existingSubscription->setExpiryDate(new \DateTime());

        $this->entityManager->flush();
        $this->logger->info('Subscription deleted successfully', [
            'subscription_id' => $subscriptionId,
            'is_active' => $existingSubscription->getIsActive(),
        ]);
    }

    private function handleCheckoutSessionCompleted($session)
    {
        // Gestion de l'événement checkout.session.completed
        $this->logger->info('Checkout session completed', ['session_id' => $session->id]);
    }

    private function handlePaymentMethodAttached($paymentMethod)
    {
        // Gestion de l'événement payment_method.attached
        $this->logger->info('Payment method attached', ['payment_method_id' => $paymentMethod->id]);
    }

    private function handleChargeSucceeded($charge)
    {
        // Gestion de l'événement charge.succeeded
        $this->logger->info('Charge succeeded', ['charge_id' => $charge->id]);
    }
}
