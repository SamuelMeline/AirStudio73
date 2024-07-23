<?php

namespace App\Controller;

use App\Entity\Course;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

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

        return $this->render('calendar/index.html.twig', [
            'courses' => $courses,
            'startOfWeek' => $startOfWeek,
            'endOfWeek' => $endOfWeek,
            'currentYear' => $year,
            'currentWeek' => $week,
        ]);
    }
}
