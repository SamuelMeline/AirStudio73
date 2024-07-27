<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Course;
use App\Entity\Subscription;
use App\Entity\SubscriptionCourse;
use App\Form\BookingType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class BookingController extends AbstractController
{
    #[Route('/booking/new/{courseId}', name: 'booking_new')]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request, EntityManagerInterface $em, int $courseId): Response
    {
        $course = $em->getRepository(Course::class)->find($courseId);

        if (!$course) {
            throw $this->createNotFoundException('No course found for id ' . $courseId);
        }

        $user = $this->getUser();
        $subscriptions = $em->getRepository(Subscription::class)->findBy(['user' => $user]);

        if (!$subscriptions || count($subscriptions) === 0) {
            $this->addFlash('error', 'You do not have an active subscription.');
            return $this->redirectToRoute('subscription_new', ['courseId' => $courseId]);
        }

        $validSubscriptionCourse = null;
        foreach ($subscriptions as $sub) {
            foreach ($sub->getSubscriptionCourses() as $subscriptionCourse) {
                // Vérifiez si l'abonnement inclut le type de cours, pas seulement l'ID du cours
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

            $validSubscriptionCourse->setRemainingCredits($remainingCourseCredits - 1); // Décrémenter pour la réservation initiale
            $em->persist($validSubscriptionCourse);
            $em->flush();

            if ($isRecurrent) {
                $this->createRecurrentBookings($booking, $em, $numOccurrences - 1, $validSubscriptionCourse, $course);
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
    public function cancel(EntityManagerInterface $em, int $bookingId): Response
    {
        $booking = $em->getRepository(Booking::class)->find($bookingId);

        if (!$booking) {
            $this->addFlash('error', 'Booking not found.');
            return $this->redirectToRoute('calendar');
        }

        if ($booking->getUser() !== $this->getUser()) {
            $this->addFlash('error', 'You are not authorized to cancel this booking.');
            return $this->redirectToRoute('calendar');
        }

        $subscriptionCourse = $booking->getSubscriptionCourse();
        $subscriptionCourse->setRemainingCredits($subscriptionCourse->getRemainingCredits() + 1);

        $em->persist($subscriptionCourse);
        $em->remove($booking);
        $em->flush();

        $this->addFlash('success', 'Réservation annulée, vous avez récupéré votre crédit.');

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
public function cancelMultiple(Request $request, EntityManagerInterface $em): Response
{
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
                $newBooking->setNumOccurrences(1); // Définir numOccurrences à 1 pour chaque réservation récurrente
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
