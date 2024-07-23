<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Booking;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CalendarController extends AbstractController
{
    #[Route('/calendar/{year?}/{week?}', name: 'calendar')]
    public function index(EntityManagerInterface $em, int $year = null, int $week = null): Response
    {
        $today = new \DateTime();

        if ($year && $week) {
            $currentDate = new \DateTime();
            $currentDate->setISODate($year, $week);
        } else {
            $currentDate = $today;
            $year = (int) $currentDate->format('Y');
            $week = (int) $currentDate->format('W');
        }

        $startOfWeek = (clone $currentDate)->modify('monday this week');
        $endOfWeek = (clone $startOfWeek)->modify('sunday this week');

        // Récupérer tous les cours dans la semaine
        $courses = $em->getRepository(Course::class)->createQueryBuilder('c')
            ->where('c.startTime BETWEEN :start AND :end')
            ->setParameter('start', $startOfWeek)
            ->setParameter('end', $endOfWeek)
            ->getQuery()
            ->getResult();

        // Récupérer toutes les réservations associées aux cours
        $bookings = [];
        foreach ($courses as $course) {
            $courseBookings = $em->getRepository(Booking::class)->findBy(['course' => $course]);
            $bookings[$course->getId()] = $courseBookings;
        }

        return $this->render('calendar/index.html.twig', [
            'courses' => $courses,
            'bookings' => $bookings,
            'startOfWeek' => $startOfWeek,
            'endOfWeek' => $endOfWeek,
            'currentYear' => $year,
            'currentWeek' => $week,
        ]);
    }
}
