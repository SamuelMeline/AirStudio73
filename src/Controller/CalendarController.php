<?php

// src/Controller/CalendarController.php

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
        $currentDate = new \DateTime();

        if ($year && $week) {
            $currentDate->setISODate($year, $week);
        }

        $week = $currentDate->format('W');
        $year = $currentDate->format('Y');

        $startOfWeek = (clone $currentDate)->modify('monday this week');
        $endOfWeek = (clone $startOfWeek)->modify('sunday this week');

        // Récupérer tous les cours récurrents et non récurrents
        $courses = $em->getRepository(Course::class)->createQueryBuilder('c')
            ->where('c.startTime BETWEEN :start AND :end')
            ->orWhere('c.isRecurrent = true')
            ->setParameter('start', $startOfWeek)
            ->setParameter('end', $endOfWeek)
            ->getQuery()
            ->getResult();

        $recurrentCourses = [];
        foreach ($courses as $course) {
            if ($course->isRecurrent()) {
                $courseClone = clone $course;
                $courseClone->setStartTime((clone $startOfWeek)->modify($course->getStartTime()->format('l'))->setTime(
                    $course->getStartTime()->format('H'),
                    $course->getStartTime()->format('i')
                ));
                $courseClone->setEndTime((clone $startOfWeek)->modify($course->getStartTime()->format('l'))->setTime(
                    $course->getEndTime()->format('H'),
                    $course->getEndTime()->format('i')
                ));
                $recurrentCourses[] = $courseClone;
            }
        }

        return $this->render('calendar/index.html.twig', [
            'courses' => array_merge($courses, $recurrentCourses),
            'startOfWeek' => $startOfWeek,
            'endOfWeek' => $endOfWeek,
            'currentYear' => $year,
            'currentWeek' => $week,
        ]);
    }
}


