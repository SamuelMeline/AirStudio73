<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Booking;
use App\Entity\Subscription;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CalendarController extends AbstractController
{
    #[Route('/calendar/{year?}/{week?}', name: 'calendar')]
    public function index(EntityManagerInterface $em, int $year = null, int $week = null, Request $request): Response
    {
        $user = $this->getUser(); // Récupérer l'utilisateur connecté

        // Si l'utilisateur n'est pas connecté, rediriger vers la page de connexion avec un message
        if (!$user) {
            // Stocker la page de redirection après connexion
            $this->addFlash('error', 'Vous devez vous connecter pour accéder aux réservations.');
            $request->getSession()->set('target_path', $request->getUri());

            // Rediriger vers la page de connexion
            return $this->redirectToRoute('app_login'); // Remplacez 'app_login' par le nom de votre route de connexion si nécessaire
        }

        // Vérifier si l'utilisateur est admin
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            // L'administrateur peut accéder sans restriction, pas de vérifications supplémentaires
            return $this->renderAdminCalendar($em, $year, $week);
        }

        // Récupérer les abonnements actifs avec des crédits restants
        $activeSubscriptions = $em->getRepository(Subscription::class)->createQueryBuilder('s')
            ->leftJoin('s.subscriptionCourses', 'sc')
            ->where('s.user = :user')
            ->andWhere('sc.remainingCredits > 0') // Vérifier si l'utilisateur a encore des crédits
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        $bookingReservations = $em->getRepository(Booking::class)->createQueryBuilder('b')
            ->join('b.course', 'c')
            ->where('b.user = :user')
            ->andWhere('c.startTime > :now')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();

        // Si l'utilisateur n'a pas de crédits restants, redirection vers la page d'achat d'un forfait
        if (count($activeSubscriptions) === 0 &&  count($bookingReservations) === 0) {
            $this->addFlash('error', 'Vous n\'avez plus de crédits, veuillez acheter un forfait.');
            return $this->redirectToRoute('subscription_new');
        }

        // Gérer la date actuelle ou la date spécifiée par l'utilisateur
        $today = new \DateTime();
        if ($year && $week) {
            $currentDate = new \DateTime();
            $currentDate->setISODate($year, $week);
            // Vérifier si la semaine sélectionnée est dans le passé
            if ($currentDate < (clone $today)->modify('monday this week')) {
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

    /**
     * Gérer l'affichage du calendrier pour l'administrateur
     */
    private function renderAdminCalendar(EntityManagerInterface $em, int $year = null, int $week = null): Response
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
            'year' => $year,
            'week' => $week,
            'courses' => $courses,
            'bookings' => $bookings,
            'currentYear' => $year,
            'currentWeek' => $week,
            'startOfWeek' => $startOfWeek,
            'endOfWeek' => $endOfWeek,
        ]);
    }
}
