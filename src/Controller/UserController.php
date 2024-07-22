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
    public function subscription(EntityManagerInterface $em): Response
    {
        $userName = $this->getUser()->getUserIdentifier();
        $subscription = $em->getRepository(Subscription::class)->findOneBy(['userName' => $userName]);

        return $this->render('user/subscription.html.twig', [
            'subscription' => $subscription,
        ]);
    }
}
