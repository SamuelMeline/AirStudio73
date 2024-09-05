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
    private const SENDER_EMAIL = 'contactAirstudio73@gmail.com';

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

        if (!$validSubscriptionCourse) {
            // Vérification si la date du cours est avant la date de commencement du forfait
            foreach ($subscriptions as $subscription) {
                $remainingCredits = $subscriptionCourse->getRemainingCredits();
                if ($subscription->getStartDate() > $course->getStartTime() && $remainingCredits !=0) {
                    $this->addFlash('error', sprintf(
                        'Votre forfait ne commence que le %s. Vous ne pouvez pas réserver avant cette date.',
                        $subscription->getStartDate()->format('d/m/Y')
                    ));
                    return $this->redirectToRoute('calendar');
                }
            }

            $this->addFlash('error', 'Il ne vous reste plus de crédits pour ce cours. Veuillez acheter un nouveau forfait.');
            return $this->redirectToRoute('subscription_new', ['courseId' => $courseId]);
        }

        // S'assurer que la souscription existe
        if (!$subscriptionCourse) {
            throw new \LogicException('Aucune souscription trouvée pour cet utilisateur.');
        }

        // Récupérer les crédits restants depuis SubscriptionCourse
        $remainingCredits = $subscriptionCourse->getRemainingCredits();

        $remainingCourseCredits = $validSubscriptionCourse->getRemainingCredits();

        if ($remainingCourseCredits <= 0) {
            $this->addFlash('error', 'Il ne vous reste plus de crédits pour ce cours. Veuillez acheter un nouveau forfait.');
            return $this->redirectToRoute('subscription_new', ['courseId' => $courseId]);
        }

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

            // Si c'est une réservation récurrente, on crée les réservations pour les occurrences suivantes
            if ($isRecurrent) {
                $this->createRecurrentBookings($booking, $em, $numOccurrences - 1, $validSubscriptionCourse, $course);
            }

            $this->sendEmail(
                $user->getEmail(),
                'Confirmation de Réservation',
                sprintf(
                    'Bonjour %s,

Votre réservation pour le cours "%s" prévu le %s à %s a été confirmée.
À très vite !

Cordialement,
Airstudio73',
                    $user->getFirstName(),
                    $course->getName(),
                    $course->getStartTime()->format('d/m/Y'),
                    $course->getStartTime()->format('H:i')
                ),
                $logger
            );

            $this->addFlash('success', 'Votre réservation a été prise en compte.');

            return $this->redirectToRoute('calendar');
        }

        return $this->render('booking/new.html.twig', [
            'form' => $form->createView(),
            'course' => $course,
            'remaining_courses' => $remainingCourseCredits,
            'user_credits' => $remainingCredits
        ]);
    }

    #[Route('/booking/cancel/{bookingId}', name: 'booking_cancel')]
    #[IsGranted('ROLE_USER')]
    public function cancel(int $bookingId, EntityManagerInterface $em, LoggerInterface $logger): Response
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

        $subscriptionCourse = $booking->getSubscriptionCourse();
        $subscriptionCourse->setRemainingCredits($subscriptionCourse->getRemainingCredits() + 1);

        $em->persist($subscriptionCourse);
        $em->remove($booking);
        $em->flush();

        $this->addFlash('success', 'Réservation annulée, vous avez récupéré votre crédit.');

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
            self::ADMIN_EMAIL,
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

        return $this->redirectToRoute('calendar');
    }

    #[Route('/admin/course/cancel/{courseId}', name: 'admin_course_cancel')]
    #[IsGranted('ROLE_ADMIN')]
    public function adminCancelCourse(int $courseId, EntityManagerInterface $em, LoggerInterface $logger): Response
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

            // Envoyer un email de notification
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

        return $this->redirectToRoute('calendar');
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

        $profEmail = self::ADMIN_EMAIL;
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
                ->to($profEmail)
                ->subject('Annulation de Réservation par un Utilisateur')
                ->text($profEmailMessage);

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

    private function createRecurrentBookings(Booking $booking, EntityManagerInterface $em, int $numOccurrences, SubscriptionCourse $subscriptionCourse, Course $course): void
    {
        $initialCourseStartTime = $this->ensureDateTime($course->getStartTime());

        $recurrenceInterval = 7; // Intervalle de récurrence en jours (1 semaine)

        // Créer un tableau pour stocker les nouvelles réservations
        $newBookings = [];

        for ($i = 1; $i <= $numOccurrences; $i++) {
            // Calcul de la date du prochain cours récurrent (toutes les semaines)
            $nextCourseDate = (clone $initialCourseStartTime)->add(new \DateInterval('P' . ($i * $recurrenceInterval) . 'D'));

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
            }
        }

        // Persist toutes les nouvelles réservations en une seule fois
        foreach ($newBookings as $newBooking) {
            $em->persist($newBooking);
        }

        // Flush une seule fois après toutes les réservations
        $em->flush();
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

        // Limiter la recherche à un nombre raisonnable de courses
        for ($i = 0; $i < 100; $i++) {
            // Calculer la prochaine date du cours
            $nextCourseDate = (clone $initialCourseStartTime)->add(new \DateInterval('P' . ($i * $recurrenceInterval) . 'D'));

            // Rechercher le cours récurrent à cette date
            $recurrentCourse = $em->getRepository(Course::class)->findOneBy([
                'name' => $course->getName(),
                'startTime' => $nextCourseDate,
            ]);

            // Vérifier que le cours existe et qu'il reste des places
            if ($recurrentCourse && $this->canBook($recurrentCourse, $em)) {
                $availableCourses++;
            } else {
                break; // Arrêter la boucle si aucun cours récurrent n'est disponible ou complet
            }
        }

        return new JsonResponse(['availableCourses' => $availableCourses]);
    }

    private function canBook(Course $course, EntityManagerInterface $em): bool
    {
        $currentBookings = $em->getRepository(Booking::class)->count(['course' => $course]);
        return $currentBookings < $course->getCapacity(); // Vérifier qu'il reste des places
    }


    private function sendEmail(string $to, string $subject, string $message, LoggerInterface $logger): void
    {
        $email = (new Email())
            ->from(self::SENDER_EMAIL)
            ->replyTo(self::SENDER_EMAIL)
            ->to($to)
            ->subject($subject)
            ->text($message);

        try {
            $logger->info('Envoi d\'un email à : ' . $to);
            $transport = Transport::fromDsn('smtp://contactAirstudio73@gmail.com:ofnlzwlcprshxdmv@smtp.gmail.com:587');
            $mailer = new Mailer($transport);
            $mailer->send($email);
            $logger->info('E-mail envoyé avec succès à : ' . $to);
        } catch (\Exception $e) {
            $logger->error('Échec de l\'envoi d\'un e-mail à ' . $to . ': ' . $e->getMessage());
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
                    if ($subscriptionStartDate <= $courseStartTime) {
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
