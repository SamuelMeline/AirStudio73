<?php

namespace App\Controller;

use Stripe\Stripe;
use Stripe\PromotionCode;
use App\Entity\Course;
use App\Entity\Subscription;
use App\Entity\Plan;
use App\Entity\PromoCodeUsage;
use App\Entity\User;
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
            $numCourses = $plan->getCourses();

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
        $user = $em->getRepository(User::class)->findOneBy(['email' => $userId]); // Assuming 'email' is unique and used as identifier

        if (!$plan || !$user) {
            $this->addFlash('error', 'Invalid plan or user.');
            return $this->redirectToRoute('subscription_new');
        }

        $expiryDate = (new \DateTime())->add(new \DateInterval($plan->getDuration()));

        $existingSubscription = $em->getRepository(Subscription::class)->findOneBy([
            'user' => $user,
            'plan' => $plan,
        ]);

        if ($existingSubscription) {
            $existingSubscription->setRemainingCourses(
                $existingSubscription->getRemainingCourses() + $plan->getCourses()
            );

            if ($existingSubscription->getExpiryDate() < $expiryDate) {
                $existingSubscription->setExpiryDate($expiryDate);
            }

            $em->persist($existingSubscription);
        } else {
            $subscription = new Subscription();
            $subscription->setUser($user);
            $subscription->setPlan($plan);
            $subscription->setRemainingCourses($plan->getCourses());
            $subscription->setPurchaseDate(new \DateTime());
            $subscription->setExpiryDate($expiryDate);
            $subscription->setPromoCode($promoCode);
            $subscription->setPaymentMode($plan->getName());

            $em->persist($subscription);
        }

        if (strpos($plan->getName(), 'pole_annuel_classique_activite') !== false) {
            $souplessePlan = $em->getRepository(Plan::class)->findOneBy(['name' => 'souplesse_annuel_classique']);

            if ($souplessePlan) {
                $existingSouplesseSubscription = $em->getRepository(Subscription::class)->findOneBy([
                    'user' => $user,
                    'plan' => $souplessePlan,
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
                    $souplesseSubscription = new Subscription();
                    $souplesseSubscription->setUser($user);
                    $souplesseSubscription->setPlan($souplessePlan);
                    $souplesseSubscription->setRemainingCourses(43);
                    $souplesseSubscription->setPurchaseDate(new \DateTime());
                    $souplesseSubscription->setExpiryDate($expiryDate);
                    $souplesseSubscription->setPromoCode($promoCode);
                    $souplesseSubscription->setPaymentMode('souplesse_annuel_classique');

                    $em->persist($souplesseSubscription);
                }
            }
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
