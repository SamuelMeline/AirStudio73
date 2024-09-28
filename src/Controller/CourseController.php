<?php

namespace App\Controller;

use App\Entity\Course;
use App\Form\CourseType;
use App\Repository\CourseRepository;
use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CourseController extends AbstractController
{
    #[Route('/course/new', name: 'course_new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $course = new Course();
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($course);

            if ($course->getisRecurrent() && $course->getRecurrenceInterval()) {
                $occurrenceCount = $this->calculateOccurrences($course->getRecurrenceDuration(), $course->getStartTime());

                for ($i = 1; $i < $occurrenceCount; $i++) {
                    $startTime = clone $course->getStartTime();
                    $startTime->add(new \DateInterval('P' . ($i * $course->getRecurrenceInterval()) . 'D'));

                    $endTime = clone $course->getEndTime();
                    $endTime->add(new \DateInterval('P' . ($i * $course->getRecurrenceInterval()) . 'D'));

                    if ($endTime <= $startTime) {
                        throw new \LogicException('L\'heure de fin doit être après l\'heure de début.');
                    }

                    $recurrentCourse = new Course();
                    $recurrentCourse->setName($course->getName());
                    $recurrentCourse->setStartTime($startTime);
                    $recurrentCourse->setEndTime($endTime);
                    $recurrentCourse->setCapacity($course->getCapacity());
                    $recurrentCourse->setIsRecurrent(false);
                    $recurrentCourse->setRecurrenceInterval($course->getRecurrenceInterval());
                    $recurrentCourse->setRecurrenceDuration($course->getRecurrenceDuration());

                    $em->persist($recurrentCourse);
                }
            }

            $em->flush();

            $year = $request->request->get('year', date('Y'));
            $week = $request->request->get('week', date('W'));

            return $this->redirectToRoute('calendar', [
                'year' => $year,
                'week' => $week,
            ]);
        }

        // Récupérer les paramètres year et week ou définir les valeurs par défaut
        $year = $request->query->get('year', date('Y'));
        $week = $request->query->get('week', date('W'));

        return $this->render('course/new.html.twig', [
            'form' => $form->createView(),
            'currentYear' => $year,
            'currentWeek' => $week,
        ]);
    }

    #[Route('/course', name: 'course_list')]
    public function list(EntityManagerInterface $em): Response
    {
        $courses = $em->getRepository(Course::class)->findAll();

        return $this->render('course/list.html.twig', [
            'courses' => $courses,
        ]);
    }

    #[Route('/course/capacity/{id}', name: 'course_update_capacity', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')] // Seul l'administrateur peut gérer la capacité
    public function updateCapacity(Request $request, Course $course, EntityManagerInterface $em): Response
    {
        $action = $request->request->get('action');

        if ($action === 'increase') {
            $course->setCapacity($course->getCapacity() + 1);
        } elseif ($action === 'decrease') {
            if ($course->getCapacity() > 0) {
                $course->setCapacity($course->getCapacity() - 1);
            } else {
                $this->addFlash('error', 'La capacité ne peut pas être inférieure à zéro.');
            }
        }

        $em->persist($course);
        $em->flush();

        // Récupérer la semaine et l'année actuelles depuis le formulaire
        $year = $request->request->get('year');
        $week = $request->request->get('week');

        return $this->redirectToRoute('calendar', [
            'year' => $year,
            'week' => $week,
        ]);
    }

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
                return 40; // 40 weeks
            case '2_years':
                return 104; // 104 weeks
            case '3_years':
                return 156; // 156 weeks
            default:
                return 4; // Default to 1 month if not specified
        }
    }

    #[Route('/course/edit/{id}', name: 'course_edit')]
    public function edit(Request $request, Course $course, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush(); // Mettre à jour le cours avec les nouvelles informations

            // Récupérer l'année et la semaine depuis la requête POST (provenant du formulaire)
            $year = $request->request->get('year', date('Y'));
            $week = $request->request->get('week', date('W'));

            return $this->redirectToRoute('calendar', [
                'year' => $year,
                'week' => $week,
            ]); // Redirige vers la bonne semaine après modification
        }

        // Ajouter les valeurs `year` et `week` dans la vue pour les envoyer dans le formulaire
        return $this->render('course/edit.html.twig', [
            'form' => $form->createView(),
            'course' => $course,
            'currentYear' => $request->query->get('year', date('Y')), // Transmettre les valeurs à la vue
            'currentWeek' => $request->query->get('week', date('W')), // Transmettre les valeurs à la vue
        ]);
    }

    #[Route('/course/delete/{id}', name: 'course_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, Course $course, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $course->getId(), $request->request->get('_token'))) {
            $em->remove($course);
            $em->flush();

            $this->addFlash('success', 'Le cours a été supprimé avec succès.');
        } else {
            $this->addFlash('error', 'Le jeton CSRF est invalide.');
        }

        // Récupérer l'année et la semaine depuis la requête POST
        $year = $request->request->get('year', date('Y'));
        $week = $request->request->get('week', date('W'));

        return $this->redirectToRoute('calendar', [
            'year' => $year,
            'week' => $week,
        ]);
    }

    public function calendar(CourseRepository $courseRepository, BookingRepository $bookingRepository)
    {
        // Récupérer tous les cours de la semaine
        $courses = $courseRepository->findCoursesForCurrentWeek(); // À ajuster en fonction de ta logique

        // Associer les réservations pour chaque cours
        $bookingsByCourse = [];
        foreach ($courses as $course) {
            $bookingsByCourse[$course->getId()] = $bookingRepository->findBy(['course' => $course]);
        }

        return $this->render('calendar/index.html.twig', [
            'courses' => $courses,
            'bookings' => $bookingsByCourse,
        ]);
    }
}
