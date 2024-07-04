<?php

// src/Controller/BookingController.php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Course;
use App\Form\BookingType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BookingController extends AbstractController
{
    #[Route('/booking/new/{courseId}', name: 'booking_new')]
    public function new(Request $request, EntityManagerInterface $em, int $courseId): Response
    {
        /** @var Course $course */
        $course = $em->getRepository(Course::class)->find($courseId);

        if (!$course) {
            throw $this->createNotFoundException('No course found for id ' . $courseId);
        }

        $booking = new Booking();
        $form = $this->createForm(BookingType::class, $booking);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->canBook($course)) {
                $booking->setCourse($course);
                $em->persist($booking);
                $em->flush();

                if ($booking->isRecurrent() && $course->isRecurrent()) {
                    $this->createRecurrentBookings($booking, $em);
                }

                return $this->redirectToRoute('course_list');
            } else {
                $this->addFlash('error', 'This course is fully booked.');
            }
        }

        return $this->render('booking/new.html.twig', [
            'form' => $form->createView(),
            'course' => $course,
        ]);
    }

    private function canBook(Course $course): bool
    {
        return count($course->getBookings()) < $course->getCapacity();
    }

    private function createRecurrentBookings(Booking $booking, EntityManagerInterface $em): void
    {
        $course = $booking->getCourse();
        /** @var \DateTime $startTime */
        $startTime = $course->getStartTime();
        $recurrenceDuration = $course->getRecurrenceDuration();
        $recurrenceInterval = $course->getRecurrenceInterval();
        $occurrences = $this->calculateOccurrences($recurrenceDuration, $startTime);

        for ($i = 1; $i < $occurrences; $i++) {
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

    /**
     * Calculate the number of occurrences based on the recurrence duration and start date.
     *
     * @param string $recurrenceDuration The duration of recurrence.
     * @param \DateTimeInterface $startDate The start date of the course.
     * @return int The number of occurrences.
     */
    private function calculateOccurrences(string $recurrenceDuration, \DateTimeInterface $startDate): int
    {
        switch ($recurrenceDuration) {
            case '1_month':
                return 4; // 4 weeks
            case '3_months':
                return 12; // 12 weeks
            case '6_months':
                return 24; // 24 weeks
            case '1_year':
                return 52; // 52 weeks
            case '2_years':
                return 104; // 104 weeks
            case '3_years':
                return 156; // 156 weeks
            default:
                return 4; // Default to 1 month if not specified
        }
    }
}
