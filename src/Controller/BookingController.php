<?php

// src/Controller/BookingController.php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Course;
use App\Entity\Booking;
use App\Form\BookingType;
use App\Entity\Subscription;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Email;
use App\Entity\SubscriptionCourse;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class BookingController extends AbstractController
{
    #[Route('/booking/new/{courseId}', name: 'booking_new')]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request, EntityManagerInterface $em, LoggerInterface $logger, int $courseId): Response
    {
        $course = $em->getRepository(Course::class)->find($courseId);

        if (!$course) {
            throw $this->createNotFoundException('No course found for id ' . $courseId);
        }

        $user = $this->getUser();

        if (!$user instanceof User) {
            throw new \LogicException('L\'utilisateur doit être un objet de type User.');
        }

        $subscriptions = $em->getRepository(Subscription::class)->findBy(['user' => $user]);

        if (!$subscriptions || count($subscriptions) === 0) {
            $this->addFlash('error', 'You do not have an active subscription.');
            return $this->redirectToRoute('subscription_new', ['courseId' => $courseId]);
        }

        $validSubscriptionCourse = null;
        foreach ($subscriptions as $sub) {
            foreach ($sub->getSubscriptionCourses() as $subscriptionCourse) {
                if ($subscriptionCourse->getCourse()->getName() === $course->getName() && $subscriptionCourse->getRemainingCredits() > 0) {
                    $validSubscriptionCourse = $subscriptionCourse;
                    break 2;
                }
            }
        }

        if (!$validSubscriptionCourse) {
            $this->addFlash('error', 'You do not have a valid subscription for this course.');
            return $this->redirectToRoute('subscription_new', ['courseId' => $courseId]);
        }

        $remainingCourseCredits = $validSubscriptionCourse->getRemainingCredits();

        if ($remainingCourseCredits <= 0) {
            $this->addFlash('error', 'You do not have any remaining credits for this course. Please purchase a new subscription.');
            return $this->redirectToRoute('subscription_new', ['courseId' => $courseId]);
        }

        $booking = new Booking();
        $booking->setSubscriptionCourse($validSubscriptionCourse);
        $form = $this->createForm(BookingType::class, $booking, ['remaining_courses' => $remainingCourseCredits]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $isRecurrent = $form->get('isRecurrent')->getData();
            $numOccurrences = $form->get('numOccurrences')->getData() ?? 1;

            if ($numOccurrences > $remainingCourseCredits) {
                $this->addFlash('error', 'You do not have enough remaining credits for this booking.');
                return $this->redirectToRoute('subscription_new', ['courseId' => $courseId]);
            }

            $booking->setCourse($course);
            $booking->setUser($user);
            $booking->setIsRecurrent($isRecurrent);
            $booking->setNumOccurrences($numOccurrences);

            $em->persist($booking);
            $em->flush();

            $validSubscriptionCourse->setRemainingCredits($remainingCourseCredits - 1);
            $em->persist($validSubscriptionCourse);
            $em->flush();

            if ($isRecurrent) {
                $this->createRecurrentBookings($booking, $em, $numOccurrences - 1, $validSubscriptionCourse, $course);
            }

            // Envoi d'un email de confirmation de réservation
            $userEmail = $user->getEmail();
            $courseDate = $course->getStartTime()->format('d/m/Y');
            $courseTime = $course->getStartTime()->format('H:i');
            $userEmailMessage = sprintf(
                'Votre réservation pour le cours "%s" prévu le %s à %s a été confirmée.',
                $course->getName(),
                $courseDate,
                $courseTime
            );

            $emailToUser = (new Email())
                ->from('contactAirstudio73@gmail.com')
                ->replyTo('contactAirstudio73@gmail.com')
                ->to($userEmail)
                ->subject('Confirmation de Réservation')
                ->text($userEmailMessage);

            try {
                $logger->info('Sending booking confirmation email to user: ' . $userEmail);
                $transport = Transport::fromDsn('smtp://contactAirstudio73@gmail.com:ofnlzwlcprshxdmv@smtp.gmail.com:587');
                $mailer = new Mailer($transport);
                $mailer->send($emailToUser);
                $logger->info('Booking confirmation email sent successfully.');
            } catch (\Exception $e) {
                $logger->error('Failed to send booking confirmation email: ' . $e->getMessage());
            }

            $this->addFlash('success', 'Votre réservation a été prise en compte.');

            return $this->redirectToRoute('calendar');
        }

        return $this->render('booking/new.html.twig', [
            'form' => $form->createView(),
            'course' => $course,
            'remaining_courses' => $remainingCourseCredits,
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
            $this->addFlash('error', 'Booking not found.');
            return $this->redirectToRoute('calendar');
        }

        if ($booking->getUser() !== $user) {
            $this->addFlash('error', 'You are not authorized to cancel this booking.');
            return $this->redirectToRoute('calendar');
        }

        $subscriptionCourse = $booking->getSubscriptionCourse();
        $subscriptionCourse->setRemainingCredits($subscriptionCourse->getRemainingCredits() + 1);

        $em->persist($subscriptionCourse);
        $em->remove($booking);
        $em->flush();

        $this->addFlash('success', 'Réservation annulée, vous avez récupéré votre crédit.');

        // Envoi de l'email de notification à l'utilisateur
        $userEmail = $user->getEmail();
        $courseName = $booking->getCourse()->getName();
        $courseDate = $booking->getCourse()->getStartTime()->format('d/m/Y');
        $courseTime = $booking->getCourse()->getStartTime()->format('H:i');
        $userEmailMessage = sprintf(
            'Votre réservation pour le cours "%s" prévu le %s à %s a été annulée.',
            $courseName,
            $courseDate,
            $courseTime
        );

        $emailToUser = (new Email())
            ->from('contactAirstudio73@gmail.com')
            ->replyTo('contactAirstudio73@gmail.com')
            ->to($userEmail)
            ->subject('Annulation de Réservation')
            ->text($userEmailMessage);

        // Envoi de l'email de notification à la professeure
        $profEmail = 'smelinepro@gmail.com';
        $profEmailMessage = sprintf(
            'La réservation pour le cours "%s" prévu le %s par l\'utilisateur "%s" a été annulée.',
            $courseName,
            $courseDate,
            $courseTime,
            $user->getEmail()
        );

        $emailToProf = (new Email())
            ->from('contactAirstudio73@gmail.com')
            ->replyTo('contactAirstudio73@gmail.com')
            ->to($profEmail)
            ->subject('Annulation de Réservation par un Utilisateur')
            ->text($profEmailMessage);

        try {
            $logger->info('Sending email to user: ' . $userEmail);
            $transport = Transport::fromDsn('smtp://contactAirstudio73@gmail.com:ofnlzwlcprshxdmv@smtp.gmail.com:587');
            $mailer = new Mailer($transport);

            $mailer->send($emailToUser);
            $logger->info('Email to user sent successfully.');

            $logger->info('Sending email to professor: ' . $profEmail);
            $mailer->send($emailToProf);
            $logger->info('Email to professor sent successfully.');
        } catch (\Exception $e) {
            $logger->error('Failed to send cancellation emails: ' . $e->getMessage());
        }

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

            $subscriptionCourse = $booking->getSubscriptionCourse();
            $subscriptionCourse->setRemainingCredits($subscriptionCourse->getRemainingCredits() + 1);

            $em->persist($subscriptionCourse);
            $em->remove($booking);
        }

        $em->flush();

        // Envoi de l'email de notification à l'utilisateur et à la professeure pour chaque réservation annulée
        $userEmail = $user->getEmail();
        $profEmail = 'smelinepro@gmail.com';
        $transport = Transport::fromDsn('smtp://contactAirstudio73@gmail.com:ofnlzwlcprshxdmv@smtp.gmail.com:587');
        $mailer = new Mailer($transport);

        foreach ($bookings as $booking) {
            $courseName = $booking->getCourse()->getName();
            $courseDate = $booking->getCourse()->getStartTime()->format('d/m/Y');
            $courseTime = $booking->getCourse()->getStartTime()->format('H:i');
            $userEmailMessage = sprintf(
                'Votre réservation pour le cours "%s" prévu le %s à %s a été annulée.',
                $courseName,
                $courseDate,
                $courseTime
            );
            $profEmailMessage = sprintf(
                'La réservation pour le cours "%s" prévu le %s à %s par l\'utilisateur "%s" a été annulée.',
                $courseName,
                $courseDate,
                $courseTime,
                $user->getEmail()
            );

            $emailToUser = (new Email())
                ->from('contactAirstudio73@gmail.com')
                ->replyTo('contactAirstudio73@gmail.com')
                ->to($userEmail)
                ->subject('Annulation de Réservation')
                ->text($userEmailMessage);

            $emailToProf = (new Email())
                ->from('contactAirstudio73@gmail.com')
                ->replyTo('contactAirstudio73@gmail.com')
                ->to($profEmail)
                ->subject('Annulation de Réservation par un Utilisateur')
                ->text($profEmailMessage);

            try {
                $mailer->send($emailToUser);
                $logger->info('Email to user sent successfully.');

                $mailer->send($emailToProf);
                $logger->info('Email to professor sent successfully.');
            } catch (\Exception $e) {
                $logger->error('Failed to send cancellation emails: ' . $e->getMessage());
            }
        }

        $this->addFlash('success', 'Les réservations sélectionnées ont été annulées.');
        return $this->redirectToRoute('user_subscription'); // Redirection vers la page des abonnements
    }

    private function createRecurrentBookings(Booking $booking, EntityManagerInterface $em, int $numOccurrences, SubscriptionCourse $subscriptionCourse, Course $course): void
    {
        $startTime = $course->getStartTime();
        $startTime = $this->ensureDateTime($startTime);

        $recurrenceInterval = $course->getRecurrenceInterval();

        for ($i = 1; $i <= $numOccurrences && $subscriptionCourse->getRemainingCredits() > 0; $i++) {
            $nextCourseDate = (clone $startTime)->add(new \DateInterval('P' . ($i * $recurrenceInterval) . 'D'));

            $recurrentCourse = $em->getRepository(Course::class)->findOneBy([
                'name' => $course->getName(),
                'startTime' => $nextCourseDate,
            ]);

            if ($recurrentCourse && $this->canBook($recurrentCourse, $em)) {
                $newBooking = new Booking();
                $newBooking->setUser($booking->getUser());
                $newBooking->setCourse($recurrentCourse);
                $newBooking->setIsRecurrent(true);
                $newBooking->setNumOccurrences(1);
                $newBooking->setSubscriptionCourse($subscriptionCourse);
                $em->persist($newBooking);

                $subscriptionCourse->setRemainingCredits($subscriptionCourse->getRemainingCredits() - 1);
                $em->persist($subscriptionCourse);
            }
        }

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

    private function canBook(Course $course, EntityManagerInterface $em): bool
    {
        $bookings = $em->getRepository(Booking::class)->findBy(['course' => $course]);
        return count($bookings) < $course->getCapacity();
    }
}
