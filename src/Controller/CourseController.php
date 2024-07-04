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
}
