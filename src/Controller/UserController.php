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

        $activeSubscriptions = array_filter($subscriptions, function ($subscription) {
            return $subscription->getRemainingCourses() > 0;
        });

        return $this->render('user/subscription.html.twig', [
            'subscriptions' => $activeSubscriptions,
        ]);
    }
}
