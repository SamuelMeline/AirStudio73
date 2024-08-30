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

        return new Response('Success', Response::HTTP_OK);
    }

    private function handleSubscriptionUpdated($subscription)
    {
        // Récupérer l'ID de l'abonnement Stripe
        $subscriptionId = $subscription->id;

        // Rechercher l'abonnement dans la base de données
        $existingSubscription = $this->entityManager->getRepository(Subscription::class)->findOneBy([
            'stripeSubscriptionId' => $subscriptionId,
        ]);

        if (!$existingSubscription) {
            return new Response('Subscription not found', Response::HTTP_NOT_FOUND);
        }

        // Mettre à jour l'abonnement selon les données reçues
        // Par exemple, vous pourriez mettre à jour la date de fin, le statut, etc.
        $existingSubscription->setExpiryDate(new \DateTime('@' . $subscription->current_period_end));
        $existingSubscription->setIsActive($subscription->status === 'active');

        $this->entityManager->flush();
    }

    private function handleSubscriptionDeleted($subscription)
    {
        // Récupérer l'ID de l'abonnement Stripe
        $subscriptionId = $subscription->id;

        // Rechercher l'abonnement dans la base de données
        $existingSubscription = $this->entityManager->getRepository(Subscription::class)->findOneBy([
            'stripeSubscriptionId' => $subscriptionId,
        ]);

        if (!$existingSubscription) {
            return new Response('Subscription not found', Response::HTTP_NOT_FOUND);
        }

        // Désactiver l'abonnement ou le marquer comme annulé
        $existingSubscription->setIsActive(false);
        $existingSubscription->setExpiryDate(new \DateTime());

        $this->entityManager->flush();
    }
}