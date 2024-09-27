<?php

namespace App\Controller;

use App\Entity\Plan;
use App\Entity\User;
use App\Entity\Course;
use App\Entity\Subscription;
use App\Form\SubscriptionType;
use App\Entity\SubscriptionCourse;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Form\AdminSubscriptionType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
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
    public function list(Request $request, UserRepository $userRepository): Response
    {
        // Récupérer le paramètre de recherche et de tri
        $search = $request->query->get('search', '');
        $sort = $request->query->get('sort', 'asc'); // 'asc' pour l'ordre croissant, 'desc' pour l'ordre décroissant

        // Rechercher les utilisateurs en fonction du nom ou prénom, et les trier par prénom
        $users = $userRepository->findBySearchAndSort($search, $sort);

        return $this->render('admin/client_list.html.twig', [
            'users' => $users,
            'search' => $search,
            'sort' => $sort,
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

    public function adjustSubscriptionCredits(Plan $plan): int
    {
        // Récupérer les crédits du plan
        $planCourses = $plan->getPlanCourses();

        if (empty($planCourses) || !isset($planCourses[0])) {
            throw new \Exception('Aucun cours associé à ce plan.');
        }

        $totalCredits = $plan->getPlanCourses()[0]->getCredits(); // Récupérer le total des crédits du plan
        $currentDate = new \DateTime();
        $startDate = $plan->getStartDate();

        // Vérifier que la date de début n'est pas nulle
        if ($startDate === null) {
            throw new \Exception('La date de début du plan est introuvable.');
        }

        // Calculer la différence en semaines entre la date de début et la date actuelle
        $weeksElapsed = floor($startDate->diff($currentDate)->days / 7);

        // Si la date de début est future, alors 0 semaines ont écoulé, pas de décrémentation
        if ($startDate > $currentDate) {
            $weeksElapsed = 0;
        }

        // Ajuster en fonction du type d'abonnement
        if ($plan->getSubscriptionType() === 'weekly' || $plan->getSubscriptionType() === 'bi-weekly') {
            // Pour un abonnement hebdomadaire ou bi-hebdomadaire, ajuster en fonction des semaines écoulées
            $remainingCredits = max(0, $totalCredits - $weeksElapsed);
        } else {
            // Autres types d'abonnement, utiliser la totalité des crédits
            $remainingCredits = $totalCredits;
        }

        return $remainingCredits;
    }

    #[Route('/admin/clients/{userId}/subscription/new', name: 'admin_subscription_new')]
    #[IsGranted('ROLE_ADMIN')]
    public function newSubscriptionForClient(Request $request, EntityManagerInterface $em, int $userId): Response
    {
        // Récupérer l'utilisateur
        $user = $em->getRepository(User::class)->find($userId);
        if (!$user) {
            $this->addFlash('error', 'Utilisateur non trouvé.');
            return $this->redirectToRoute('admin_user_subscriptions', ['userId' => $userId]);
        }

        // Utiliser le formulaire spécifique pour l'admin
        $subscription = new Subscription();
        $form = $this->createForm(AdminSubscriptionType::class, $subscription, ['em' => $em]);

        $form->handleRequest($request);

        // Si le formulaire est soumis
        if ($form->isSubmitted()) {

            // Vérifiez si le champ 'plan' existe dans le formulaire avant de l'utiliser
            if (!$form->has('plan') || !$form->get('plan')->getData()) {
                return $this->render('admin/subscription_new.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            // Récupérer le plan choisi par l'administrateur
            $plan = $form->get('plan')->getData();

            // Vérifier si le plan est expiré
            $currentDate = new \DateTime();
            $expiryDate = $plan->getEndDate();

            if ($expiryDate < $currentDate) {
                $this->addFlash('error', 'Ce forfait est expiré et ne peut plus être souscrit.');
                return $this->redirectToRoute('admin_subscription_new', ['userId' => $userId]);
            }

            // Associer le plan à la souscription pour l'utilisateur
            $subscription->setUser($user);
            $subscription->setPlan($plan);

            // Définir les dates de début et de fin de la souscription depuis le plan
            if ($plan->getStartDate() !== null) {
                $subscription->setStartDate($plan->getStartDate());
            } else {
                $subscription->setStartDate(new \DateTime());
            }

            if ($plan->getEndDate() !== null) {
                $subscription->setExpiryDate($plan->getEndDate());
            }

            // Définir le mode de paiement comme 'admin'
            $subscription->setPaymentMode('Sur place');

            // Ajuster les crédits en fonction du plan et du temps écoulé
            $remainingCredits = $this->adjustSubscriptionCredits($plan); // Appel à la méthode d'ajustement

            // Attribuer les crédits ajustés aux cours du plan
            foreach ($plan->getPlanCourses() as $planCourse) {
                $subscriptionCourse = new SubscriptionCourse();
                $subscriptionCourse->setSubscription($subscription);
                $subscriptionCourse->setCourse($planCourse->getCourse());

                // Attribuer les crédits ajustés
                $adjustedCredits = min($remainingCredits, $planCourse->getCredits()); // Limiter les crédits au total disponible pour ce cours
                $subscriptionCourse->setRemainingCredits($adjustedCredits);

                $em->persist($subscriptionCourse);
            }

            // Sauvegarder la souscription et les crédits associés
            $em->persist($subscription);
            $em->flush();

            $this->addFlash('success', 'Forfait attribué avec succès à l\'utilisateur.');

            return $this->redirectToRoute('admin_user_subscriptions', ['userId' => $userId]);
        }

        return $this->render('admin/subscription_new.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }

    #[Route('/admin/clients/{userId}/subscriptions', name: 'admin_user_subscriptions')]
    #[IsGranted('ROLE_ADMIN')]
    public function showUserSubscriptions(EntityManagerInterface $em, int $userId): Response
    {
        $user = $em->getRepository(User::class)->find($userId);
        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }

        $subscriptions = $em->getRepository(Subscription::class)->findBy(['user' => $user]);

        return $this->render('admin/user_subscriptions.html.twig', [
            'user' => $user,
            'subscriptions' => $subscriptions,
        ]);
    }

    #[Route('/admin/clients/{userId}/subscription/credits/update', name: 'admin_update_credits', methods: ['POST'])]
    public function updateCredits(Request $request, EntityManagerInterface $em, int $userId): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $courseId = $data['courseId'];
        $subscriptionId = $data['subscriptionId'];  // Récupérer l'ID de la souscription
        $increment = (int) $data['increment'];

        // Récupérer l'utilisateur
        $user = $em->getRepository(User::class)->find($userId);
        if (!$user) {
            return new JsonResponse(['error' => 'Utilisateur non trouvé'], 404);
        }

        // Récupérer la souscription spécifiée par l'ID
        $subscription = $em->getRepository(Subscription::class)->find($subscriptionId);
        if (!$subscription || $subscription->getUser() !== $user) {
            return new JsonResponse(['error' => 'Souscription non trouvée pour cet utilisateur.'], 404);
        }

        // Récupérer le cours
        $course = $em->getRepository(Course::class)->find($courseId);
        if (!$course) {
            return new JsonResponse(['error' => 'Cours non trouvé.'], 404);
        }

        // Récupérer le SubscriptionCourse
        $subscriptionCourse = $em->getRepository(SubscriptionCourse::class)->findOneBy([
            'subscription' => $subscription,
            'course' => $course,
        ]);

        if (!$subscriptionCourse) {
            return new JsonResponse(['error' => 'Aucun crédit trouvé pour ce cours.'], 404);
        }

        // Mise à jour des crédits
        $newCredits = $subscriptionCourse->getRemainingCredits() + $increment;
        if ($newCredits < 0) {
            $newCredits = 0;  // Ne pas autoriser des crédits négatifs
        }

        $subscriptionCourse->setRemainingCredits($newCredits);
        $em->persist($subscriptionCourse);
        $em->flush();

        return new JsonResponse(['newCredits' => $newCredits]);
    }
}
