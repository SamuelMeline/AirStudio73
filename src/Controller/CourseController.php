<?php

namespace App\Controller;

use App\Entity\Course;
use App\Form\CourseType;
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

                    // Vérification pour s'assurer que l'heure de fin est après l'heure de début
                    if ($endTime <= $startTime) {
                        throw new \LogicException('L\'heure de fin doit être après l\'heure de début.');
                    }

                    $recurrentCourse = new Course();
                    $recurrentCourse->setName($course->getName());
                    $recurrentCourse->setStartTime($startTime);
                    $recurrentCourse->setEndTime($endTime);
                    $recurrentCourse->setCapacity($course->getCapacity());
                    $recurrentCourse->setIsRecurrent(false); // Set to false to avoid infinite recursion
                    $recurrentCourse->setRecurrenceInterval($course->getRecurrenceInterval());
                    $recurrentCourse->setRecurrenceDuration($course->getRecurrenceDuration());

                    $em->persist($recurrentCourse);
                }
            }

            $em->flush();

            return $this->redirectToRoute('calendar');
        }

        return $this->render('course/new.html.twig', [
            'form' => $form->createView(),
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

        return $this->redirectToRoute('calendar', [
            'year' => $request->query->get('year'),
            'week' => $request->query->get('week'),
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
                return 52; // 52 weeks
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

            return $this->redirectToRoute('calendar'); // Redirige vers la liste des cours
        }

        return $this->render('course/edit.html.twig', [
            'form' => $form->createView(),
            'course' => $course,
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

        return $this->redirectToRoute('calendar');
    }
}
