<?php

namespace App\Controller;

use App\Entity\Subscription;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class UserController extends AbstractController
{
    #[Route('/user/subscription', name: 'user_subscription')]
    #[IsGranted('ROLE_USER')]
    public function userSubscriptions(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $subscriptions = $em->getRepository(Subscription::class)->findBy(['user' => $user]);

        // Filtrer les abonnements pour garder seulement ceux avec des crédits restants
        $activeSubscriptions = array_filter($subscriptions, function ($subscription) {
            foreach ($subscription->getSubscriptionCourses() as $subscriptionCourse) {
                if ($subscriptionCourse->getRemainingCredits() > 0) {
                    return true;
                }
            }
            return false;
        });

        // Trier les abonnements par date d'expiration et nombre de crédits restants
        usort($activeSubscriptions, function ($a, $b) {
            if ($a->getExpiryDate() == $b->getExpiryDate()) {
                $aCredits = array_sum(array_map(fn($sc) => $sc->getRemainingCredits(), $a->getSubscriptionCourses()->toArray()));
                $bCredits = array_sum(array_map(fn($sc) => $sc->getRemainingCredits(), $b->getSubscriptionCourses()->toArray()));
                return $aCredits - $bCredits;
            }
            return $a->getExpiryDate() <=> $b->getExpiryDate();
        });

        return $this->render('user/subscription.html.twig', [
            'subscriptions' => $activeSubscriptions,
        ]);
    }
}
