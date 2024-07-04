<?php

// src/Controller/CourseController.php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\CourseInstance;
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
            $em->flush();

            // Crée l'instance du cours
            $this->createCourseInstance($course, $em);

            return $this->redirectToRoute('calendar');
        }

        return $this->render('course/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    private function createCourseInstance(Course $course, EntityManagerInterface $em): void
    {
        // Crée l'instance initiale du cours
        $this->createInstance($course, $course->getStartTime(), $course->getEndTime(), $em);

        // Crée des instances récurrentes si le cours est récurrent
        if ($course->isRecurrent()) {
            $interval = new \DateInterval('P1W');
            $startDate = new \DateTime($course->getStartTime()->format('Y-m-d H:i:s'));
            $startDate->modify('next saturday');
            $period = new \DatePeriod($startDate, $interval, 51);

            foreach ($period as $date) {
                $endDate = new \DateTime($date->format('Y-m-d H:i:s'));
                $endDate->setTime((int)$course->getEndTime()->format('H'), (int)$course->getEndTime()->format('i'));
                $this->createInstance($course, $date, $endDate, $em);
            }

            $em->flush();
        }
    }

    private function createInstance(Course $course, \DateTimeInterface $startDate, \DateTimeInterface $endDate, EntityManagerInterface $em): void
    {
        $courseInstance = new CourseInstance();
        $courseInstance->setCourse($course);
        $courseInstance->setStartTime($startDate);
        $courseInstance->setEndTime($endDate);
        $courseInstance->setCapacity($course->getCapacity());

        $em->persist($courseInstance);
    }
}
