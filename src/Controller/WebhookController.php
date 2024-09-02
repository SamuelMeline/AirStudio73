<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Stripe\Webhook;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Subscription;

class WebhookController extends AbstractController
{
    private $stripeEndpointSecret;
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->stripeEndpointSecret = $_ENV['STRIPE_ENDPOINT_SECRET'];
        $this->entityManager = $entityManager;
    }

    #[Route('/stripe/webhook', name: 'stripe_webhook', methods: ['POST'])]
    public function handleStripeWebhook(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->headers->get('stripe-signature');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $this->stripeEndpointSecret);
        } catch (\UnexpectedValueException $e) {
            return new Response('Invalid payload', Response::HTTP_BAD_REQUEST);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return new Response('Invalid signature', Response::HTTP_BAD_REQUEST);
        }

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
            case 'customer.created':
            case 'customer.updated':
            case 'invoice.created':
            case 'invoice.finalized':
            case 'invoice.paid':
            case 'invoice.updated':
            case 'payment_intent.succeeded':
            case 'payment_intent.created':
                break;
            default:
                return new Response(sprintf('Unhandled event type: %s', $event->type));
        }

        return new Response('Success', Response::HTTP_OK);
    }

    private function handleInvoicePaymentSucceeded($invoice)
    {
        $subscriptionId = $invoice->subscription;

        $subscription = $this->entityManager->getRepository(Subscription::class)->findOneBy([
            'stripeSubscriptionId' => $subscriptionId,
        ]);

        if (!$subscription) {
            return new Response('Subscription not found', Response::HTTP_NOT_FOUND);
        }

        $subscription->incrementPaymentsCount();

        if ($subscription->getPaymentsCount() >= $subscription->getMaxPayments()) {
            $subscription->setIsActive(false);

            try {
                \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);
                $stripeSubscription = \Stripe\Subscription::retrieve($subscriptionId);
                $stripeSubscription->cancel();
            } catch (\Stripe\Exception\ApiErrorException $e) {
                return new Response('Failed to cancel subscription on Stripe: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        $this->entityManager->flush();
    }

    private function handleSubscriptionUpdated($subscription)
    {
        $subscriptionId = $subscription->id;

        $existingSubscription = $this->entityManager->getRepository(Subscription::class)->findOneBy([
            'stripeSubscriptionId' => $subscriptionId,
        ]);

        if (!$existingSubscription) {
            return new Response('Subscription not found', Response::HTTP_NOT_FOUND);
        }

        $newExpiryDate = new \DateTime('@' . $subscription->current_period_end);

        $existingSubscription->setExpiryDate($newExpiryDate);
        $existingSubscription->setIsActive($subscription->status === 'active');

        $this->entityManager->flush();
    }

    private function handleSubscriptionDeleted($subscription)
    {
        $subscriptionId = $subscription->id;

        $existingSubscription = $this->entityManager->getRepository(Subscription::class)->findOneBy([
            'stripeSubscriptionId' => $subscriptionId,
        ]);

        if (!$existingSubscription) {
            return new Response('Subscription not found', Response::HTTP_NOT_FOUND);
        }

        $existingSubscription->setIsActive(false);
        $existingSubscription->setExpiryDate(new \DateTime());

        $this->entityManager->flush();
    }

    private function handleCheckoutSessionCompleted($session)
    {
        // Gestion de l'événement checkout.session.completed
    }

    private function handlePaymentMethodAttached($paymentMethod)
    {
        // Gestion de l'événement payment_method.attached
    }

    private function handleChargeSucceeded($charge)
    {
        // Gestion de l'événement charge.succeeded
    }
}
