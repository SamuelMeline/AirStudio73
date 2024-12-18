<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Course;
use App\Entity\Booking;
use App\Form\BookingType;
use App\Entity\Subscription;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Email;
use App\Entity\SubscriptionCourse;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class BookingController extends AbstractController
{
    private const ADMIN_EMAIL = 'smelinepro@gmail.com';
    private const ADMIN_EMAIL_2 = 'airstudio.73@gmail.com'; // Remplace par la deuxième adresse e-mail

    private const SENDER_EMAIL = 'contactAirstudio73@gmail.com';

    private $entityManager;

    // Injecter l'EntityManager dans le constructeur
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/booking/new/{courseId}', name: 'booking_new')]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request, EntityManagerInterface $em, LoggerInterface $logger, int $courseId): Response
    {
        $course = $em->getRepository(Course::class)->find($courseId);
        if (!$course) {
            throw $this->createNotFoundException('No course found for id ' . $courseId);
        }

        // Vérification si le cours est déjà passé
        if ($course->getStartTime() < new \DateTime()) {
            $this->addFlash('error', 'Vous ne pouvez pas réserver un cours qui est déjà passé.');
            return $this->redirectToRoute('calendar');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('L\'utilisateur doit être un objet de type User.');
        }

        $subscriptions = $em->getRepository(Subscription::class)->findBy(['user' => $user]);

        if (!$subscriptions || count($subscriptions) === 0) {
            $this->addFlash('error', 'Vous n\'avez pas de forfait actif.');
            return $this->redirectToRoute('subscription_new', ['courseId' => $courseId]);
        }

        $validSubscriptionCourse = $this->getValidSubscriptionCourse($subscriptions, $course);

        // Récupérer la souscription en cours pour l'utilisateur via Subscription
        $subscriptionCourse = $em->getRepository(SubscriptionCourse::class)->findOneBy([
            'subscription' => $em->getRepository(Subscription::class)->findOneBy(['user' => $user])
        ]);

        // Si aucune souscription valide n'est trouvée, vérification des dates de début/fin et crédits restants
        if (!$validSubscriptionCourse) {
            foreach ($subscriptions as $subscription) {
                $remainingCredits = $subscriptionCourse->getRemainingCredits();
                $courseStartTime = $course->getStartTime();

                // Vérification si la date du cours est avant la date de commencement du forfait
                if ($subscription->getStartDate() !== null && $subscription->getStartDate() > $courseStartTime) {
                    $this->addFlash('error', sprintf(
                        'Votre forfait ne commence que le %s. Vous ne pouvez pas réserver avant cette date.',
                        $subscription->getStartDate()->format('d/m/Y')
                    ));
                    return $this->redirectToRoute('calendar');
                }

                // Vérification si la date du cours est après la date d'expiration du forfait
                if ($subscription->getExpiryDate() !== null && $subscription->getExpiryDate() < $courseStartTime) {
                    $this->addFlash('error', sprintf(
                        'Votre forfait se termine le %s. Vous ne pouvez pas réserver après cette date.',
                        $subscription->getExpiryDate()->format('d/m/Y')
                    ));
                    return $this->redirectToRoute('calendar');
                }
            }

            $this->addFlash('error', 'Il ne vous reste plus de crédits pour ce cours. Veuillez acheter un nouveau forfait.');
            return $this->redirectToRoute('subscription_new', ['courseId' => $courseId]);
        }

        $startDate = $course->getStartTime();
        // Récupérer l'année et la semaine depuis le formulaire ou la requête
        $year = $request->query->get('year', date('Y')); // valeur par défaut : l'année en cours
        $week = $request->query->get('week', date('W')); // valeur par défaut : la semaine en cours

        // Vérifier le type de l'abonnement, et limiter à 1 ou 2 cours par semaine si nécessaire
        $subscription = $validSubscriptionCourse->getSubscription();

        if ($subscription->getPlan()->getSubscriptionType() === 'weekly' || $subscription->getPlan()->getSubscriptionType() === 'weekly-renewable' || $subscription->getPlan()->getSubscriptionType() === 'bi-weekly') {

            // Récupérer l'utilisateur actuellement connecté
            $currentUser = $this->getUser();

            // Récupérer la date du cours que l'utilisateur souhaite réserver
            $course = $this->entityManager->getRepository(Course::class)->find($courseId);
            $courseDate = $course->getStartTime();

            // Calculer la semaine du cours
            $startOfWeek = (clone $courseDate)->modify('monday this week');
            $endOfWeek = (clone $courseDate)->modify('sunday this week 23:59:59');

            // Récupérer les SubscriptionCourses de l'utilisateur pour cette Subscription
            $subscriptionCourses = $subscription->getSubscriptionCourses();

            // Si la souscription a 2 cours ou plus, on applique la règle pour bloquer une réservation du même type de cours
            // Récupérer toutes les réservations faites cette semaine pour cet abonnement
            $existingReservations = $this->entityManager->createQuery(
                'SELECT b
                FROM App\Entity\Booking b
                JOIN b.course c
                JOIN b.subscriptionCourse sc
                WHERE b.user = :user
                AND sc.subscription = :subscription
                AND c.startTime BETWEEN :startOfWeek AND :endOfWeek'
            )
                ->setParameter('user', $currentUser)
                ->setParameter('subscription', $subscription)
                ->setParameter('startOfWeek', $startOfWeek)
                ->setParameter('endOfWeek', $endOfWeek)
                ->getResult();

            // Vérifier si la subscription a 2 courses ou plus
            if (count($subscriptionCourses) >= 2) {
                // Parcourir les réservations existantes pour voir si un cours du même nom a déjà été réservé
                foreach ($existingReservations as $reservation) {
                    $reservedCourse = $reservation->getCourse();
                    if ($reservedCourse->getName() === $course->getName()) {
                        // Si un cours du même nom a déjà été réservé, bloquer la réservation
                        $this->addFlash('error', 'Vous avez déjà réservé un cours de ce type cette semaine.');
                        return $this->redirectToRoute('calendar', [
                            'year' => $year,
                            'week' => $week,
                        ]);
                    }
                }
                // Vérifier les règles pour les abonnements bi-weekly (deux cours par semaine maximum)
                if ($subscription->getPlan()->getSubscriptionType() === 'bi-weekly' && count($existingReservations) >= 2) {
                    $this->addFlash('error', 'Vous avez déjà réservé deux cours cette semaine.');
                    return $this->redirectToRoute('calendar', [
                        'year' => $year,
                        'week' => $week,
                    ]);
                }
            }

            // Vérifier les règles pour les abonnements bi-weekly (2 cours par semaine maximum)
            if ($subscription->getPlan()->getSubscriptionType() === 'bi-weekly' && count($existingReservations) >= 2) {
                $this->addFlash('error', 'Vous avez déjà réservé deux cours cette semaine.');
                return $this->redirectToRoute('calendar', [
                    'year' => $year,
                    'week' => $week,
                ]);
            }

            // Vérifier les règles pour les abonnements weekly (un seul cours par semaine)
            if ($subscription->getPlan()->getSubscriptionType() === 'weekly' && count($existingReservations) >= 1) {
                $this->addFlash('error', 'Vous avez déjà réservé un cours cette semaine.');
                return $this->redirectToRoute('calendar', [
                    'year' => $year,
                    'week' => $week,
                ]);
            }

            // Vérifier les règles pour les abonnements weekly (un seul cours par semaine)
            if ($subscription->getPlan()->getSubscriptionType() === 'weekly-renewable' && count($existingReservations) >= 1) {
                $this->addFlash('error', 'Vous avez déjà réservé un cours cette semaine.');
                return $this->redirectToRoute('calendar', [
                    'year' => $year,
                    'week' => $week,
                ]);
            }
        }

        // S'assurer que la souscription existe
        if (!$subscriptionCourse) {
            throw new \LogicException('Aucune souscription trouvée pour cet utilisateur.');
        }

        // Récupérer les crédits restants pour l'utilisateur
        $remainingCredits = [];
        foreach ($subscriptions as $subscription) {
            foreach ($subscription->getSubscriptionCourses() as $subscriptionCourse) {
                $courseName = $subscriptionCourse->getCourse()->getName();
                $remainingCredits[$courseName] = $subscriptionCourse->getRemainingCredits();
            }
        }
        // Récupérer les crédits restants depuis SubscriptionCourse
        $remainingCourseCredits = $validSubscriptionCourse->getRemainingCredits();

        if ($remainingCourseCredits <= 0) {
            $this->addFlash('error', 'Il ne vous reste plus de crédits pour ce cours. Veuillez acheter un nouveau forfait.');
            return $this->redirectToRoute('subscription_new', ['courseId' => $courseId]);
        }

        // Logique de réservation

        $booking = new Booking();
        $booking->setSubscriptionCourse($validSubscriptionCourse);
        $form = $this->createForm(BookingType::class, $booking, ['remaining_courses' => $remainingCourseCredits]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $isRecurrent = $form->get('isRecurrent')->getData();
            $numOccurrences = $form->get('numOccurrences')->getData() ?? 1;

            // Vérification que le nombre de réservations demandées n'excède pas les crédits restants
            if ($numOccurrences > $remainingCourseCredits) {
                $this->addFlash('error', 'Vous n\'avez pas assez de crédits restants pour cette réservation.');
                return $this->redirectToRoute('subscription_new', ['courseId' => $courseId]);
            }

            $booking->setCourse($course);
            $booking->setUser($user);
            $booking->setIsRecurrent($isRecurrent);
            $booking->setNumOccurrences($numOccurrences);

            // Persistance de la première réservation
            $em->persist($booking);
            $em->flush();

            // Décrémenter les crédits restants
            $validSubscriptionCourse->setRemainingCredits($remainingCourseCredits - $numOccurrences);
            $em->persist($validSubscriptionCourse);
            $em->flush();

            if ($isRecurrent) {
                // Créer les réservations récurrentes et récupérer les dates créées
                $recurrentDates = $this->createRecurrentBookings(
                    $booking,
                    $em,
                    $numOccurrences - 1, // Puisque la première réservation est déjà créée
                    $validSubscriptionCourse,
                    $course
                );

                // Inclure la date de la réservation initiale
                $dates = [];
                $initialDate = $course->getStartTime()->format('d/m/Y H:i');
                $dates[] = $initialDate;

                // Ajouter les dates récurrentes formatées
                foreach ($recurrentDates as $date) {
                    $dates[] = $date->format('d/m/Y H:i');
                }

                // Liste des dates
                $datesList = implode(", ", $dates); 

                // Message pour l'utilisateur
                $emailMessage = sprintf(
                    'Bonjour %s,
            
            Votre réservation récurrente pour le cours "%s" est confirmée.
            Les réservations sont programmées aux dates suivantes : %s.
            Notre studio se situe au 25 Chemin de la Plaine, 73220 AITON.
            
            À très vite !
            
            Cordialement,
            Airstudio73',
                    $user->getFirstName(),
                    $course->getName(),
                    $datesList
                );
            } else {
                // Si la réservation est unique
                $emailMessage = sprintf(
                    'Bonjour %s,
            
            Votre réservation pour le cours "%s" prévu le %s à %s a été confirmée.
            Notre studio se situe au 25 Chemin de la Plaine, 73220 AITON.
            
            À très vite !
            
            Cordialement,
            Airstudio73',
                    $user->getFirstName(),
                    $course->getName(),
                    $course->getStartTime()->format('d/m/Y'),
                    $course->getStartTime()->format('H:i')
                );
            }

            // Envoi de l'e-mail à l'utilisateur
            $this->sendEmail(
                $user->getEmail(),
                'Confirmation de Réservation',
                $emailMessage,
                $logger
            );

            // Préparer le message pour les administrateurs
            $adminMessage = sprintf(
                'Une nouvelle réservation a été effectuée.
            
            Détails de la réservation :
            - Utilisateur : %s %s
            - Cours : %s le "%s" à "%s"
            ',
                $user->getFirstName(),
                $user->getLastName(),
                $course->getName(),
                $course->getStartTime()->format('d/m/Y'),
                $course->getStartTime()->format('H:i')
            );

            if ($isRecurrent && !empty($datesList)) {
                $adminMessage .= sprintf("\nLes réservations sont programmées aux dates suivantes : %s", $datesList);
            }

            // Envoi de l'e-mail aux administrateurs
            $this->sendEmail(
                [self::ADMIN_EMAIL, self::ADMIN_EMAIL_2],
                'Nouvelle Réservation',
                $adminMessage,
                $logger
            );

            $this->addFlash('success', 'Votre réservation a été prise en compte.');

            return $this->redirectToRoute('calendar', [
                'year' => $year,
                'week' => $week,
            ]);
        }

        return $this->render('booking/new.html.twig', [
            'form' => $form->createView(),
            'course' => $course,
            'remaining_courses' => $remainingCourseCredits,
            'remainingCredits' => $remainingCredits,
            'user_credits' => $remainingCredits
        ]);
    }

    #[Route('/booking/cancel/{bookingId}', name: 'booking_cancel')]
    #[IsGranted('ROLE_USER')]
    public function cancel(int $bookingId, Request $request, EntityManagerInterface $em, LoggerInterface $logger): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw new \LogicException('L\'utilisateur doit être un objet de type User.');
        }

        $booking = $em->getRepository(Booking::class)->find($bookingId);

        if (!$booking) {
            $this->addFlash('error', 'Réservation introuvable.');
            return $this->redirectToRoute('calendar');
        }

        if ($booking->getUser() !== $user) {
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à annuler cette réservation.');
            return $this->redirectToRoute('calendar');
        }

        // Vérification si le cours commence dans moins de 6 heures
        $courseStartTime = $booking->getCourse()->getStartTime();
        $currentDate = new \DateTime();
        if ($courseStartTime < $currentDate->add(new \DateInterval('PT6H'))) {
            $this->addFlash('error', 'Vous ne pouvez pas annuler une réservation moins de 6 heures avant le cours.');
            return $this->redirectToRoute('calendar');
        }

        // Gestion des crédits et suppression de la réservation
        $subscriptionCourse = $booking->getSubscriptionCourse();
        $subscriptionCourse->setRemainingCredits($subscriptionCourse->getRemainingCredits() + 1);

        $em->persist($subscriptionCourse);
        $em->remove($booking);
        $em->flush();

        $this->addFlash('success', 'Réservation annulée, vous avez récupéré votre crédit.');

        // Envoi d'e-mails
        $courseDate = $booking->getCourse()->getStartTime()->format('d/m/Y');
        $courseTime = $booking->getCourse()->getStartTime()->format('H:i');
        $courseName = $booking->getCourse()->getName();

        $this->sendEmail(
            $user->getEmail(),
            'Annulation de Réservation',
            sprintf(
                'Bonjour %s,

Votre réservation pour le cours "%s" prévu le %s à %s a été annulée.
En espérant vous revoir prochainement !

Cordialement,
Airstudio73
            ',
                $user->getFirstName(),
                $courseName,
                $courseDate,
                $courseTime
            ),
            $logger
        );

        $this->sendEmail(
            [self::ADMIN_EMAIL, self::ADMIN_EMAIL_2],
            'Annulation de Réservation par un Utilisateur',
            sprintf(
                'La réservation pour le cours "%s" prévu le %s à %s par l\'utilisateur %s %s a été annulée.',
                $courseName,
                $courseDate,
                $courseTime,
                $user->getFirstName(),
                $user->getLastName(),
            ),
            $logger
        );

        // Récupérer l'année et la semaine depuis la requête
        $year = $request->query->get('year', date('Y')); // l'année en cours si non définie
        $week = $request->query->get('week', date('W')); // la semaine en cours si non définie

        return $this->redirectToRoute('calendar', [
            'year' => $year,
            'week' => $week,
        ]);
    }

    #[Route('/admin/course/cancel/{courseId}', name: 'admin_course_cancel')]
    #[IsGranted('ROLE_ADMIN')]
    public function adminCancelCourse(int $courseId, Request $request, EntityManagerInterface $em, LoggerInterface $logger): Response
    {
        $course = $em->getRepository(Course::class)->find($courseId);

        if (!$course) {
            $this->addFlash('error', 'Cours non trouvé.');
            return $this->redirectToRoute('calendar');
        }

        // Marquer le cours comme annulé
        $course->setIsCanceled(true);
        $em->persist($course);

        // Récupérer toutes les réservations liées au cours
        $bookings = $em->getRepository(Booking::class)->findBy(['course' => $course]);

        foreach ($bookings as $booking) {
            // Rembourser les crédits
            $subscriptionCourse = $booking->getSubscriptionCourse();
            $subscriptionCourse->setRemainingCredits($subscriptionCourse->getRemainingCredits() + $booking->getNumOccurrences());

            // Supprimer la réservation
            $em->remove($booking);

            // Envoyer un email de notification à l'utilisateur
            $this->sendEmail(
                $booking->getUser()->getEmail(),
                'Annulation de Réservation et Remboursement',
                sprintf(
                    'Bonjour,

Votre réservation pour le cours "%s" prévu le %s à %s a été annulée par l\'administration.
Vous avez été remboursé de vos crédits.

Cordialement,
Airstudio73',
                    $course->getName(),
                    $course->getStartTime()->format('d/m/Y'),
                    $course->getStartTime()->format('H:i')
                ),
                $logger
            );
        }

        $em->flush();

        $this->addFlash('success', 'Le cours a été annulé et les utilisateurs ont récupéré leurs crédits.');

        // Récupérer l'année et la semaine depuis le formulaire (car c'est un POST)
        $year = $request->request->get('year', date('Y'));
        $week = $request->request->get('week', date('W'));

        // Redirection vers le calendrier avec la bonne semaine et année
        return $this->redirectToRoute('calendar', [
            'year' => $year,
            'week' => $week,
        ]);
    }

    #[Route('/admin/booking/client', name: 'admin_booking_client')]
    #[IsGranted('ROLE_ADMIN')]
    public function bookingForClient(Request $request, EntityManagerInterface $em): Response
    {
        // Récupérer tous les clients
        $clients = $em->getRepository(User::class)->findAll();

        // Initialiser les variables
        $availableCourses = [];
        $remainingCredits = [];

        // Récupérer l'ID du client sélectionné
        $userId = $request->query->get('userId');

        if ($userId) {
            // Récupérer le client
            $user = $em->getRepository(User::class)->find($userId);

            if (!$user) {
                $this->addFlash('error', 'Client non trouvé.');
                return $this->redirectToRoute('admin_booking_client');
            }

            // Récupérer les abonnements du client
            $subscriptions = $em->getRepository(Subscription::class)->findBy(['user' => $user]);

            if (empty($subscriptions)) {
                $this->addFlash('error', 'Aucun abonnement trouvé pour ce client.');
            } else {
                // Récupérer tous les cours futurs
                $futureCourses = $em->getRepository(Course::class)->createQueryBuilder('c')
                    ->where('c.startTime >= :now')
                    ->setParameter('now', new \DateTime())
                    ->orderBy('c.startTime', 'ASC')
                    ->getQuery()
                    ->getResult();

                // Parcourir chaque abonnement pour accumuler les crédits restants
                foreach ($subscriptions as $subscription) {
                    $subscriptionCourses = $em->getRepository(SubscriptionCourse::class)->findBy(['subscription' => $subscription]);

                    foreach ($subscriptionCourses as $subscriptionCourse) {
                        $course = $subscriptionCourse->getCourse();
                        $remainingCredits[$course->getName()] = $subscriptionCourse->getRemainingCredits();

                        if ($subscriptionCourse->getRemainingCredits() > 0) {
                            foreach ($futureCourses as $futureCourse) {
                                if ($futureCourse->getName() === $course->getName()) {
                                    $availableCourses[$futureCourse->getId()] = $futureCourse;
                                }
                            }
                        }
                    }
                }
            }
        }

        // Si un client et un cours sont sélectionnés pour la réservation
        if ($request->isMethod('POST')) {
            $userId = $request->request->get('userId');
            $courseId = $request->request->get('courseId');
            $isRecurrent = $request->request->get('isRecurrent') ? true : false;
            $numOccurrences = $request->request->get('numOccurrences') ?? 1;

            // Récupérer le client et le cours
            $user = $em->getRepository(User::class)->find($userId);
            $course = $em->getRepository(Course::class)->find($courseId);

            if (!$user || !$course) {
                $this->addFlash('error', 'Client ou cours non trouvé.');
                return $this->redirectToRoute('admin_booking_client');
            }

            // Vérifier que le client n'a pas déjà réservé ce cours
            $existingBooking = $em->getRepository(Booking::class)->findOneBy([
                'user' => $user,
                'course' => $course
            ]);

            if ($existingBooking) {
                $this->addFlash('error', 'Le client a déjà réservé ce cours.');
                return $this->redirectToRoute('admin_booking_client', ['userId' => $userId]);
            }

            // Vérifier si le cours est complet
            if (!$this->canBook($course, $em)) {
                $this->addFlash('error', 'Le cours est complet.');
                return $this->redirectToRoute('admin_booking_client', ['userId' => $userId]);
            }

            // Récupérer les abonnements de l'utilisateur
            $subscriptions = $em->getRepository(Subscription::class)->findBy(['user' => $user]);

            // Gérer la réservation avec les abonnements de l'utilisateur
            $subscriptionCourse = $this->getValidSubscriptionCourse($subscriptions, $course);

            if ($subscriptionCourse && $subscriptionCourse->getRemainingCredits() >= $numOccurrences) {
                // Décrémenter les crédits en fonction du nombre d'occurrences
                $subscriptionCourse->setRemainingCredits($subscriptionCourse->getRemainingCredits() - $numOccurrences);
                $em->persist($subscriptionCourse);

                // Créer la première réservation
                $booking = new Booking();
                $booking->setUser($user);
                $booking->setCourse($course);
                $booking->setSubscriptionCourse($subscriptionCourse);
                $booking->setIsRecurrent($isRecurrent);
                $booking->setNumOccurrences($numOccurrences);
                $em->persist($booking);

                // Créer les réservations récurrentes si nécessaire
                if ($isRecurrent && $numOccurrences > 1) {
                    $this->createRecurrentBookings($booking, $em, $numOccurrences - 1, $subscriptionCourse, $course);
                }

                $em->flush();

                $this->addFlash('success', 'Réservation effectuée pour ' . $user->getFirstName());
                return $this->redirectToRoute('admin_booking_client', ['userId' => $userId]);
            } else {
                $this->addFlash('error', 'Pas assez de crédits pour réserver ce cours.');
                return $this->redirectToRoute('admin_booking_client', ['userId' => $userId]);
            }
        }

        return $this->render('admin/booking_for_client.html.twig', [
            'clients' => $clients,
            'courses' => $availableCourses,
            'remainingCredits' => $remainingCredits,
        ]);
    }

    #[Route('/admin/client/{userId}/reservations', name: 'admin_client_reservations')]
    #[IsGranted('ROLE_ADMIN')]
    public function viewClientReservations(int $userId, EntityManagerInterface $em): Response
    {
        $client = $em->getRepository(User::class)->find($userId);

        if (!$client) {
            throw $this->createNotFoundException('Client non trouvé');
        }

        $reservations = $em->getRepository(Booking::class)->findBy(['user' => $client]);

        return $this->render('admin/client_reservations.html.twig', [
            'client' => $client,
            'reservations' => $reservations,
        ]);
    }

    #[Route('/admin/booking/cancel/{bookingId}', name: 'admin_booking_cancel')]
    #[IsGranted('ROLE_ADMIN')]
    public function adminCancelBooking(int $bookingId, EntityManagerInterface $em): Response
    {
        $booking = $em->getRepository(Booking::class)->find($bookingId);

        if (!$booking) {
            $this->addFlash('error', 'Réservation introuvable.');
            return $this->redirectToRoute('admin_client_list'); // Route vers la liste des clients ou réservations
        }

        // Récupérer le client
        $client = $booking->getUser();

        // Récupérer le SubscriptionCourse et ajuster les crédits restants
        $subscriptionCourse = $booking->getSubscriptionCourse();
        if ($subscriptionCourse) {
            $subscriptionCourse->setRemainingCredits($subscriptionCourse->getRemainingCredits() + 1);
            $em->persist($subscriptionCourse);
        }

        // Supprimer la réservation
        $em->remove($booking);
        $em->flush();

        $this->addFlash('success', sprintf(
            'La réservation pour le cours "%s" du client %s %s a été annulée, et les crédits ont été ajustés.',
            $booking->getCourse()->getName(),
            $client->getFirstName(),
            $client->getLastName()
        ));

        // Redirection vers la liste des réservations ou la page du client
        return $this->redirectToRoute('admin_client_reservations', ['userId' => $client->getId()]);
    }

    #[Route('/booking/manage', name: 'booking_manage')]
    #[IsGranted('ROLE_USER')]
    public function manage(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $bookings = $em->getRepository(Booking::class)->findBy(['user' => $user]);

        return $this->render('booking/manage.html.twig', [
            'bookings' => $bookings,
        ]);
    }

    #[Route('/booking/cancel-multiple', name: 'booking_cancel_multiple', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function cancelMultiple(Request $request, EntityManagerInterface $em, LoggerInterface $logger): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw new \LogicException('L\'utilisateur doit être un objet de type User.');
        }
        $bookingIds = $request->request->all('bookingIds');

        if (empty($bookingIds)) {
            $this->addFlash('error', 'Aucune réservation sélectionnée.');
            return $this->redirectToRoute('booking_manage');
        }

        $bookings = $em->getRepository(Booking::class)->findBy(['id' => $bookingIds]);

        foreach ($bookings as $booking) {
            if ($booking->getUser() !== $this->getUser()) {
                $this->addFlash('error', 'Vous n\'êtes pas autorisé à annuler cette réservation.');
                return $this->redirectToRoute('booking_manage');
            }

            // Vérification si le cours commence dans moins de 6 heures
            $courseStartTime = $booking->getCourse()->getStartTime();
            $currentDate = new \DateTime();
            if ($courseStartTime < $currentDate->add(new \DateInterval('PT6H'))) {
                $this->addFlash('error', sprintf(
                    'Vous ne pouvez pas annuler la réservation pour le cours "%s" prévu le %s à %s car il commence dans moins de 6 heures.',
                    $booking->getCourse()->getName(),
                    $courseStartTime->format('d/m/Y'),
                    $courseStartTime->format('H:i')
                ));
                return $this->redirectToRoute('booking_manage');
            }

            $subscriptionCourse = $booking->getSubscriptionCourse();
            $subscriptionCourse->setRemainingCredits($subscriptionCourse->getRemainingCredits() + 1);

            $em->persist($subscriptionCourse);
            $em->remove($booking);
        }

        $em->flush();

        $profEmail = [self::ADMIN_EMAIL, self::ADMIN_EMAIL_2];
        $transport = Transport::fromDsn('smtp://contactAirstudio73@gmail.com:ofnlzwlcprshxdmv@smtp.gmail.com:587');
        $mailer = new Mailer($transport);

        foreach ($bookings as $booking) {
            $courseName = $booking->getCourse()->getName();
            $courseDate = $booking->getCourse()->getStartTime()->format('d/m/Y');
            $courseTime = $booking->getCourse()->getStartTime()->format('H:i');
            $userEmailMessage = sprintf(
                'Bonjour %s,

Votre réservation pour le cours "%s" prévu le %s à %s a été annulée.
En espérant vous revoir prochainement !

Cordialement,
Airstudio73
                ',
                $user->getFirstName(),
                $courseName,
                $courseDate,
                $courseTime
            );
            $profEmailMessage = sprintf(
                'La réservation pour le cours "%s" prévu le %s à %s par l\'utilisateur "%s %s" a été annulée.',
                $courseName,
                $courseDate,
                $courseTime,
                $user->getFirstName(),
                $user->getLastName()
            );

            $emailToUser = (new Email())
                ->from(self::SENDER_EMAIL)
                ->replyTo(self::SENDER_EMAIL)
                ->to($user->getEmail())
                ->subject('Annulation de Réservation')
                ->text($userEmailMessage);

            $emailToProf = (new Email())
                ->from(self::SENDER_EMAIL)
                ->replyTo(self::SENDER_EMAIL)
                ->subject('Annulation de Réservation par un Utilisateur')
                ->text($profEmailMessage);

            // Ajouter plusieurs destinataires
            foreach ($profEmail as $recipient) {
                $emailToProf->addTo($recipient);
            }

            try {
                $mailer->send($emailToUser);
                $logger->info('E-mail envoyé à l\'utilisateur avec succès');

                $mailer->send($emailToProf);
                $logger->info('E-mail envoyé au professeur avec succès.');
            } catch (\Exception $e) {
                $logger->error('Échec de l\'envoi des e-mails d\'annulation : ' . $e->getMessage());
            }
        }

        $this->addFlash('success', 'Les réservations sélectionnées ont été annulées.');
        return $this->redirectToRoute('user_subscription');
    }

    private function createRecurrentBookings(
        Booking $booking,
        EntityManagerInterface $em,
        int $numOccurrences,
        SubscriptionCourse $subscriptionCourse,
        Course $course
    ): array {
        $initialCourseStartTime = $this->ensureDateTime($course->getStartTime());
        $recurrenceInterval = 7; // Intervalle de récurrence en jours (1 semaine)
        $newBookings = [];
        $createdOccurrences = 0;
        $currentWeek = 1;
        $maxWeeks = $numOccurrences * 4; // Ajustez selon vos besoins
        $createdDates = []; // Tableau pour stocker les dates des réservations créées

        while ($createdOccurrences < $numOccurrences && $currentWeek <= $maxWeeks) {
            // Calcul de la date du prochain cours récurrent
            $nextCourseDate = (clone $initialCourseStartTime)->add(new \DateInterval('P' . (($currentWeek) * $recurrenceInterval) . 'D'));

            // Recherche du cours correspondant à cette date
            $recurrentCourse = $em->getRepository(Course::class)->findOneBy([
                'name' => $course->getName(),
                'startTime' => $nextCourseDate,
            ]);

            if ($recurrentCourse && $this->canBook($recurrentCourse, $em)) {
                // Création d'une nouvelle réservation
                $newBooking = new Booking();
                $newBooking->setUser($booking->getUser());
                $newBooking->setCourse($recurrentCourse);
                $newBooking->setSubscriptionCourse($subscriptionCourse);
                $newBooking->setIsRecurrent(true);
                $newBooking->setNumOccurrences(1); // Traiter chaque occurrence individuellement

                // Ajouter la réservation au tableau
                $newBookings[] = $newBooking;
                $createdDates[] = $recurrentCourse->getStartTime(); // Ajouter la date
                $createdOccurrences++;
            }

            $currentWeek++;
        }

        if ($createdOccurrences < $numOccurrences) {
            // Optionnel: Gérer le cas où toutes les occurrences n'ont pas pu être créées
            // Par exemple, enregistrer un message d'erreur ou notifier l'utilisateur
        }

        // Persist toutes les nouvelles réservations en une seule fois
        foreach ($newBookings as $newBooking) {
            $em->persist($newBooking);
        }

        // Flush une seule fois après toutes les réservations
        $em->flush();

        return $createdDates; // Retourner les dates créées
    }

    private function ensureDateTime($startTime): \DateTimeInterface
    {
        if ($startTime instanceof \DateTimeInterface) {
            return $startTime;
        }

        if (is_string($startTime)) {
            return new \DateTime($startTime);
        }

        return new \DateTime('now');
    }

    #[Route('/booking/available-courses/{courseId}', name: 'booking_available_courses', methods: ['GET'])]
    public function countAvailableCourses(int $courseId, EntityManagerInterface $em): JsonResponse
    {
        $course = $em->getRepository(Course::class)->find($courseId);

        if (!$course) {
            return new JsonResponse(['error' => 'Cours introuvable'], Response::HTTP_NOT_FOUND);
        }

        $initialCourseStartTime = $course->getStartTime();
        $recurrenceInterval = 7; // Intervalle de récurrence (ex. une semaine)
        $availableCourses = 0;
        $maxWeeks = 100; // Limiter la recherche à 100 semaines
        $currentWeek = 1;

        while ($currentWeek <= $maxWeeks) {
            // Calculer la prochaine date du cours
            $nextCourseDate = (clone $initialCourseStartTime)->add(new \DateInterval('P' . (($currentWeek - 1) * $recurrenceInterval) . 'D'));

            // Rechercher le cours récurrent à cette date
            $recurrentCourse = $em->getRepository(Course::class)->findOneBy([
                'name' => $course->getName(),
                'startTime' => $nextCourseDate,
            ]);

            // Vérifier que le cours existe et qu'il reste des places
            if ($recurrentCourse && $this->canBook($recurrentCourse, $em)) {
                $availableCourses++;
            }

            $currentWeek++;
        }

        return new JsonResponse(['availableCourses' => $availableCourses]);
    }

    private function canBook(Course $course, EntityManagerInterface $em): bool
    {
        $currentBookings = $em->getRepository(Booking::class)->count(['course' => $course]);
        return $currentBookings < $course->getCapacity(); // Vérifier qu'il reste des places
    }

    private function sendEmail($to, string $subject, string $message, LoggerInterface $logger): void
    {
        $email = (new Email())
            ->from(self::SENDER_EMAIL)
            ->replyTo(self::SENDER_EMAIL)
            ->subject($subject)
            ->text($message);

        // Vérifie si $to est un tableau, et ajoute les adresses en conséquence
        if (is_array($to)) {
            foreach ($to as $recipient) {
                $email->addTo($recipient);
            }
        } else {
            $email->to($to);
        }

        try {
            $logger->info('Envoi d\'un email à : ' . implode(', ', (array)$to)); // Log l'adresse
            $transport = Transport::fromDsn('smtp://contactAirstudio73@gmail.com:ofnlzwlcprshxdmv@smtp.gmail.com:587');
            $mailer = new Mailer($transport);
            $mailer->send($email);
            $logger->info('E-mail envoyé avec succès à : ' . implode(', ', (array)$to));
        } catch (\Exception $e) {
            $logger->error('Échec de l\'envoi d\'un e-mail à ' . implode(', ', (array)$to) . ': ' . $e->getMessage());
        }
    }

    private function getValidSubscriptionCourse(array $subscriptions, Course $course): ?SubscriptionCourse
    {
        $validSubscriptionCourse = null;

        foreach ($subscriptions as $subscription) {
            // Si l'abonnement n'est pas valide, on le saute
            if (!$this->isSubscriptionValid($subscription)) {
                error_log(sprintf("Ignorer l'ID d'abonnement expiré ou inactif : %d", $subscription->getId()));
                continue;
            }

            // Parcourir les cours liés à cet abonnement
            foreach ($subscription->getSubscriptionCourses() as $subscriptionCourse) {
                // Vérifier que le cours correspond et qu'il reste des crédits
                if (
                    $subscriptionCourse->getCourse()->getName() === $course->getName()
                    && $subscriptionCourse->getRemainingCredits() > 0
                ) {

                    $subscriptionStartDate = $subscription->getStartDate();
                    $subscriptionExpiryDate = $subscription->getExpiryDate();
                    $courseStartTime = $course->getStartTime();

                    // Vérifier que la date de début de la souscription est avant ou égale à la date du cours
                    // et que la date d'expiration est après ou égale à la date du cours
                    if (
                        $subscriptionStartDate <= $courseStartTime &&
                        ($subscriptionExpiryDate === null || $subscriptionExpiryDate >= $courseStartTime)
                    ) {

                        // Si aucun forfait valide n'a encore été trouvé, on sélectionne le premier compatible
                        if (!$validSubscriptionCourse) {
                            $validSubscriptionCourse = $subscriptionCourse;
                        } else {
                            // Comparer la date d'expiration et la date de début pour choisir le meilleur forfait
                            $currentValidExpiryDate = $validSubscriptionCourse->getSubscription()->getExpiryDate();
                            $currentValidStartDate = $validSubscriptionCourse->getSubscription()->getStartDate();

                            // Priorité à la date d'expiration la plus proche
                            if (
                                ($subscriptionExpiryDate !== null && $currentValidExpiryDate !== null && $subscriptionExpiryDate < $currentValidExpiryDate)
                                || ($currentValidExpiryDate === null && $subscriptionExpiryDate !== null)
                                || ($subscriptionExpiryDate === $currentValidExpiryDate && $subscriptionStartDate <= $currentValidStartDate)
                            ) {
                                $validSubscriptionCourse = $subscriptionCourse;
                            }
                        }
                    }
                }
            }
        }

        return $validSubscriptionCourse; // Retourner le forfait valide trouvé, ou null si aucun
    }

    private function isSubscriptionValid(Subscription $subscription): bool
    {
        return $subscription->isValid();
    }
}
