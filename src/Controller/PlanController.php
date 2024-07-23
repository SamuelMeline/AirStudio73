<?php

namespace App\Controller;

use App\Entity\Plan;
use App\Entity\Course;
use App\Form\PlanType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class PlanController extends AbstractController
{
    #[Route('/plan/new', name: 'plan_new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $plan = new Plan();
        $form = $this->createForm(PlanType::class, $plan, [
            'courses' => $em->getRepository(Course::class)->findAll(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($plan);
            $em->flush();

            return $this->redirectToRoute('plan_list');
        }

        return $this->render('plan/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/plan', name: 'plan_list')]
    public function list(EntityManagerInterface $em): Response
    {
        $plans = $em->getRepository(Plan::class)->findAll();

        return $this->render('plan/list.html.twig', [
            'plans' => $plans,
        ]);
    }

    #[Route('/plan/edit/{id}', name: 'plan_edit')]
    public function edit(Request $request, EntityManagerInterface $em, int $id): Response
    {
        $plan = $em->getRepository(Plan::class)->find($id);

        if (!$plan) {
            throw $this->createNotFoundException('No plan found for id ' . $id);
        }

        $form = $this->createForm(PlanType::class, $plan);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            return $this->redirectToRoute('plan_list');
        }

        return $this->render('plan/edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/plan/delete/{id}', name: 'plan_delete')]
    public function delete(EntityManagerInterface $em, int $id): Response
    {
        $plan = $em->getRepository(Plan::class)->find($id);

        if (!$plan) {
            throw $this->createNotFoundException('No plan found for id ' . $id);
        }

        $em->remove($plan);
        $em->flush();

        return $this->redirectToRoute('plan_list');
    }
}
