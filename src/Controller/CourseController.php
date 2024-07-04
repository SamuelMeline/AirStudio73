<?php

// src/Controller/CourseController.php

namespace App\Controller;

use App\Entity\Course;
use App\Form\CourseType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

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

            if ($course->isRecurrent() && $course->getRecurrenceInterval()) {
                $occurrenceCount = $this->calculateOccurrences($course->getRecurrenceDuration(), $course->getStartTime());

                for ($i = 1; $i < $occurrenceCount; $i++) {
                    /** @var \DateTime $startTime */
                    $startTime = clone $course->getStartTime();
                    $startTime->add(new \DateInterval('P' . ($i * $course->getRecurrenceInterval()) . 'D'));

                    /** @var \DateTime $endTime */
                    $endTime = clone $course->getEndTime();
                    $endTime->add(new \DateInterval('P' . ($i * $course->getRecurrenceInterval()) . 'D'));

                    $recurrentCourse = clone $course;
                    $recurrentCourse->setStartTime($startTime);
                    $recurrentCourse->setEndTime($endTime);
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

    /**
     * Calculate the number of occurrences based on the recurrence duration and start date.
     *
     * @param string $recurrenceDuration The duration of recurrence.
     * @param \DateTimeInterface $startDate The start date of the course.
     * @return int The number of occurrences.
     */
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
}
