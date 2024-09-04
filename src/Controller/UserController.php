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

        // Supprimez tout filtre et affichez simplement tous les abonnements
        return $this->render('user/subscription.html.twig', [
            'subscriptions' => $subscriptions,
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

    #[Route('/admin/clients_new', name: 'admin_client_new')]
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

        return $this->render('admin/client_new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/clients/{id}/edit', name: 'admin_client_edit')]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher, User $user): Response
    {
        // Ne pas exiger de mot de passe si l'admin édite un client
        $form = $this->createForm(RegistrationFormType::class, $user, [
            'is_edit' => true, // On passe un paramètre pour indiquer qu'il s'agit d'une édition
        ]);
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

    #[Route('/admin/clients/{id}/edit_notes', name: 'admin_client_edit_notes', methods: ['POST'])]
    public function editNotes(Request $request, EntityManagerInterface $em, User $user): Response
    {
        $notes = $request->request->get('notes'); // Récupérer les notes envoyées via AJAX

        if ($notes) {
            $user->setNotes($notes);  // Mettre à jour les notes
            $em->flush();  // Sauvegarder les modifications
        }

        return new Response('Notes mises à jour avec succès', Response::HTTP_OK);
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
