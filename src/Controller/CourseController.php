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
            // Le premier cours a bien `is_recurrent = 1`
            $course->setIsRecurrent(true);
            $em->persist($course);

            // Créer les cours récurrents si c'est un cours récurrent
            if ($course->getIsRecurrent() && $course->getRecurrenceInterval()) {
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
                    // Assurez-vous que tous les cours récurrents ont `is_recurrent = 1`
                    $recurrentCourse->setIsRecurrent(true);
                    $recurrentCourse->setRecurrenceInterval($course->getRecurrenceInterval());
                    $recurrentCourse->setRecurrenceDuration($course->getRecurrenceDuration());

                    $em->persist($recurrentCourse);
                }
            }

            $em->flush();

            return $this->redirectToRoute('calendar', [
                'year' => $request->request->get('year', date('Y')),
                'week' => $request->request->get('week', date('W')),
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
    public function edit(Request $request, Course $course, EntityManagerInterface $em, CourseRepository $courseRepository): Response
    {
        // Sauvegarder les heures originales avant modification
        $originalStartTime = $course->getStartTime()->format('H:i'); // Heure de début originale
        $originalEndTime = $course->getEndTime()->format('H:i'); // Heure de fin originale

        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $modifyAllOccurrences = $request->request->get('modify_all_occurrences', false);

            if ($course->getIsRecurrent() && $modifyAllOccurrences) {
                // Obtenir les informations du cours modifié
                $modificationDate = $course->getStartTime(); // Date du cours à partir duquel on modifie
                $startDayOfWeek = $course->getStartTime()->format('N'); // Jour de la semaine (1 pour lundi, 7 pour dimanche)

                // Récupérer tous les cours récurrents sans filtrer par le nom du cours
                $allRecurrentCourses = $courseRepository->findBy([
                    'isRecurrent' => true,
                    'recurrenceInterval' => $course->getRecurrenceInterval(),
                ]);

                $recurrentCoursesToUpdate = [];

                // Parcourir les cours récupérés pour ne garder que ceux avec la même plage horaire (avant modification)
                foreach ($allRecurrentCourses as $recurrentCourse) {
                    $courseDayOfWeek = $recurrentCourse->getStartTime()->format('N');
                    $courseStartTime = $recurrentCourse->getStartTime()->format('H:i');
                    $courseEndTime = $recurrentCourse->getEndTime()->format('H:i');

                    // Vérification : Ne pas modifier les cours antérieurs à aujourd'hui
                    if ($recurrentCourse->getStartTime() < new \DateTime()) {
                        continue;
                    }

                    // Filtrer par jour de la semaine ET par la plage horaire originale (avant modification)
                    if ($courseDayOfWeek === $startDayOfWeek && $courseStartTime === $originalStartTime && $courseEndTime === $originalEndTime) {
                        if ($recurrentCourse->getStartTime() >= $modificationDate) {
                            $recurrentCoursesToUpdate[] = $recurrentCourse;
                        }
                    }
                }

                // Ajout du dd() pour vérifier les cours récupérés avant modification
                // dd($recurrentCoursesToUpdate);

                // Appliquer les modifications à tous les cours trouvés (nom, capacité, horaires)
                foreach ($recurrentCoursesToUpdate as $recurrentCourse) {
                    // Modifier l'heure de début
                    $newStartTime = clone $recurrentCourse->getStartTime();
                    $newStartTime->setTime(
                        $course->getStartTime()->format('H'),
                        $course->getStartTime()->format('i')
                    );
                    $recurrentCourse->setStartTime($newStartTime);

                    // Modifier l'heure de fin
                    $newEndTime = clone $recurrentCourse->getEndTime();
                    $newEndTime->setTime(
                        $course->getEndTime()->format('H'),
                        $course->getEndTime()->format('i')
                    );
                    $recurrentCourse->setEndTime($newEndTime);

                    // Modifier le nom
                    $recurrentCourse->setName($course->getName());

                    // Modifier la capacité
                    $recurrentCourse->setCapacity($course->getCapacity());

                    // Persister les modifications
                    $em->persist($recurrentCourse);
                }

                // Sauvegarder toutes les modifications
                $em->flush();
            } else {
                // Sauvegarder uniquement ce cours
                $em->persist($course);
                $em->flush();
            }

            // Récupérer l'année et la semaine depuis la requête POST (provenant du formulaire)
            $year = $request->request->get('year', date('Y'));
            $week = $request->request->get('week', date('W'));

            return $this->redirectToRoute('calendar', [
                'year' => $year,
                'week' => $week,
            ]);
        }

        return $this->render('course/edit.html.twig', [
            'form' => $form->createView(),
            'course' => $course,
            'currentYear' => $request->query->get('year', date('Y')),
            'currentWeek' => $request->query->get('week', date('W')),
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
