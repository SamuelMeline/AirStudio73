<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Course;
use App\Entity\Subscription;
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

        $userName = $this->getUser()->getUserIdentifier();
        $subscriptions = $em->getRepository(Subscription::class)->findBy(['userName' => $userName]);

        if (!$subscriptions || count($subscriptions) === 0) {
            $this->addFlash('error', 'You do not have an active subscription.');
            return $this->redirectToRoute('subscription_new', ['courseId' => $courseId]);
        }

        $validSubscription = null;
        foreach ($subscriptions as $sub) {
            if ($this->isSubscriptionValidForCourse($sub, $course)) {
                $validSubscription = $sub;
                break;
            }
        }

        if (!$validSubscription) {
            $this->addFlash('error', 'You do not have a valid subscription for this course.');
            return $this->redirectToRoute('subscription_new', ['courseId' => $courseId]);
        }

        $remainingCourses = $validSubscription->getRemainingCourses();

        if ($remainingCourses <= 0) {
            $this->addFlash('error', 'You do not have any remaining courses. Please purchase a new subscription.');
            return $this->redirectToRoute('subscription_new', ['courseId' => $courseId]);
        }

        $booking = new Booking();
        $form = $this->createForm(BookingType::class, $booking, ['remaining_courses' => $remainingCourses]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $isRecurrent = $form->get('isRecurrent')->getData();
            $numOccurrences = $form->get('numOccurrences')->getData() ?? 1;

            if ($numOccurrences > $remainingCourses) {
                $this->addFlash('error', 'You do not have enough remaining courses for this booking.');
                return $this->redirectToRoute('subscription_new', ['courseId' => $courseId]);
            }

            $booking->setCourse($course);
            $booking->setUserName($userName);
            $booking->setIsRecurrent($isRecurrent);
            $booking->setNumOccurrences($numOccurrences);

            $em->persist($booking);
            $em->flush();

            $validSubscription->decrementRemainingCourses($numOccurrences);
            $em->persist($validSubscription);
            $em->flush();

            if ($isRecurrent) {
                $this->createRecurrentBookings($booking, $em, $numOccurrences);
            }

            $this->addFlash('success', 'Your booking has been successfully created.');

            return $this->redirectToRoute('calendar');
        }

        return $this->render('booking/new.html.twig', [
            'form' => $form->createView(),
            'course' => $course,
            'remaining_courses' => $remainingCourses,
        ]);
    }


    private function isSubscriptionValidForCourse(Subscription $subscription, Course $course): bool
    {
        $forfait = $subscription->getForfait();
        if (strpos($forfait, 'pole') !== false && $course->getName() === 'Pole Dance') {
            return true;
        }
        if (strpos($forfait, 'souplesse') !== false && $course->getName() === 'Souplesse ou Renfo') {
            return true;
        }
        return false;
    }

    private function createRecurrentBookings(Booking $booking, EntityManagerInterface $em, int $numOccurrences): void
    {
        $course = $booking->getCourse();
        $startTime = $course->getStartTime();
        $startTime = $this->ensureDateTime($startTime);

        $recurrenceInterval = $course->getRecurrenceInterval();

        for ($i = 1; $i < $numOccurrences; $i++) {
            $nextCourseDate = (clone $startTime)->add(new \DateInterval('P' . ($i * $recurrenceInterval) . 'D'));

            $recurrentCourse = $em->getRepository(Course::class)->findOneBy([
                'name' => $course->getName(),
                'startTime' => $nextCourseDate,
            ]);

            if ($recurrentCourse && $this->canBook($recurrentCourse)) {
                $newBooking = new Booking();
                $newBooking->setUserName($booking->getUserName());
                $newBooking->setCourse($recurrentCourse);
                $newBooking->setIsRecurrent(true);
                $em->persist($newBooking);
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

    private function canBook(Course $course): bool
    {
        return count($course->getBookings()) < $course->getCapacity();
    }
}
