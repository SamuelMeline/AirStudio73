<?php

// src/Controller/BookingController.php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\CourseInstance;
use App\Form\BookingType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BookingController extends AbstractController
{
    #[Route('/booking/new/{courseInstanceId}', name: 'booking_new')]
    public function new(Request $request, EntityManagerInterface $em, int $courseInstanceId): Response
    {
        $courseInstance = $em->getRepository(CourseInstance::class)->find($courseInstanceId);

        if (!$courseInstance) {
            throw $this->createNotFoundException('No course instance found for id ' . $courseInstanceId);
        }

        $booking = new Booking();
        $form = $this->createForm(BookingType::class, $booking);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $date = new \DateTime();

            $existingBookings = $courseInstance->getBookings();

            $booking->setStartDate($date);

            if (count($existingBookings) < $courseInstance->getCapacity()) {
                $booking->setCourseInstance($courseInstance);
                $booking->setStartDate($date);
                $em->persist($booking);
                $em->flush();

                return $this->redirectToRoute('calendar');
            } else {
                $this->addFlash('error', 'This course is fully booked.');
            }
        }

        return $this->render('booking/new.html.twig', [
            'form' => $form->createView(),
            'courseInstance' => $courseInstance
        ]);
    }
}
