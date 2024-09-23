<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Booking;
use App\Entity\Subscription;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CalendarController extends AbstractController
{
    #[Route('/calendar/{year?}/{week?}', name: 'calendar')]
    public function index(EntityManagerInterface $em, int $year = null, int $week = null): Response
    {

        $user = $this->getUser(); // Récupérer l'utilisateur connecté

        // Récupérer les abonnements actifs avec des crédits restants
        $activeSubscriptions = $em->getRepository(Subscription::class)->createQueryBuilder('s')
            ->leftJoin('s.subscriptionCourses', 'sc')
            ->where('s.user = :user')
            ->andWhere('sc.remainingCredits > 0') // Vérifier si l'utilisateur a encore des crédits
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        // Si l'utilisateur n'a pas de crédits restants, redirection vers la page d'achat d'un forfait
        if (count($activeSubscriptions) === 0) {
            $this->addFlash('error', 'Vous n\'avez plus de crédits, veuillez acheter un forfait.');
            return $this->redirectToRoute('subscription_new');
        }

        $today = new \DateTime();

        if ($year && $week) {
            $currentDate = new \DateTime();
            $currentDate->setISODate($year, $week);
            // Vérifier si la semaine sélectionnée est dans le passé
            if ($currentDate < $today->modify('monday this week')) {
                $this->addFlash('error', 'Vous ne pouvez pas accéder à une semaine passée.');
                return $this->redirectToRoute('calendar', [
                    'year' => $today->format('Y'),
                    'week' => $today->format('W')
                ]);
            }
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
