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
        $course = $em->getRepository(Course::class)->find($courseId);

        if (!$course) {
            throw $this->createNotFoundException('No course found for id ' . $courseId);
        }

        $booking = new Booking();
        $form = $this->createForm(BookingType::class, $booking);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $booking->setCourse($course);

            $existingBookings = $course->getBookings();

            if (count($existingBookings) < $course->getCapacity()) {
                $em->persist($booking);
                $em->flush();

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
}

