<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Subscription;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

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
                $aCredits = array_sum(array_map(fn ($sc) => $sc->getRemainingCredits(), $a->getSubscriptionCourses()->toArray()));
                $bCredits = array_sum(array_map(fn ($sc) => $sc->getRemainingCredits(), $b->getSubscriptionCourses()->toArray()));
                return $aCredits - $bCredits;
            }
            return $a->getExpiryDate() <=> $b->getExpiryDate();
        });

        return $this->render('user/subscription.html.twig', [
            'subscriptions' => $activeSubscriptions,
        ]);
    }

    #[Route('/admin/clients', name: 'admin_client_list')]
    #[IsGranted('ROLE_ADMIN')]
    public function list(UserRepository $userRepository): Response
    {
        $users = $userRepository->findAll();

        return $this->render('admin/client_list.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/admin/clients/new', name: 'admin_client_new')]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $em): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->get('plainPassword')->getData()) {
                $hashedPassword = $passwordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                );
                $user->setPassword($hashedPassword);
            }

            // Assurez-vous de définir les rôles en tant que tableau
            $roles = $form->get('roles')->getData();
            $user->setRoles([$roles]);

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Le client a été créé avec succès.');

            return $this->redirectToRoute('admin_client_list');
        }

        return $this->render('client/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/clients/{id}/edit', name: 'admin_client_edit')]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher, User $user): Response
    {
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Only hash and set the password if a new password is provided
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $hashedPassword = $passwordHasher->hashPassword(
                    $user,
                    $plainPassword
                );
                $user->setPassword($hashedPassword);
            }

            $em->flush();

            $this->addFlash('success', 'Le client a été mis à jour avec succès.');

            return $this->redirectToRoute('admin_client_list');
        }

        return $this->render('admin/client_edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }


    #[Route('/admin/clients/{id}/delete', name: 'admin_client_delete')]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(EntityManagerInterface $em, User $user): Response
    {
        $em->remove($user);
        $em->flush();

        $this->addFlash('success', 'Le client a été supprimé avec succès.');

        return $this->redirectToRoute('admin_client_list');
    }
}
