<?php 

// src/Controller/CalendarController.php

namespace App\Controller;

use App\Entity\CourseInstance;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CalendarController extends AbstractController
{
    #[Route('/calendar/{year?}/{month?}/{week?}', name: 'calendar')]
    public function index(EntityManagerInterface $em, int $year = null, int $month = null, int $week = null): Response
    {
        $currentDate = new \DateTime();

        if ($year && $month && $week) {
            $currentDate->setISODate($year, $week);
        }

        $week = $currentDate->format('W');
        $year = $currentDate->format('Y');

        $startOfWeek = (clone $currentDate)->modify('monday this week');
        $endOfWeek = (clone $startOfWeek)->modify('sunday this week');

        $courseInstances = $em->getRepository(CourseInstance::class)->createQueryBuilder('ci')
            ->where('ci.startTime BETWEEN :start AND :end')
            ->setParameter('start', $startOfWeek)
            ->setParameter('end', $endOfWeek)
            ->getQuery()
            ->getResult();

        return $this->render('course/calendar.html.twig', [
            'course_instances' => $courseInstances,
            'startOfWeek' => $startOfWeek,
            'endOfWeek' => $endOfWeek,
            'currentMonth' => $currentDate,
            'week' => $week,
        ]);
    }
}



