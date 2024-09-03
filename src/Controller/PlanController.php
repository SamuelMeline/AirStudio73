<?php

namespace App\Controller;

use App\Entity\Plan;
use App\Form\PlanType;
use App\Repository\PlanRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class PlanController extends AbstractController
{
    #[Route('/plan/new', name: 'plan_new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $plan = new Plan();
        $form = $this->createForm(PlanType::class, $plan);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($plan->getPlanCourses() as $planCourse) {
                $planCourse->setPlan($plan);
            }

            $em->persist($plan);
            $em->flush();

            $this->addFlash('success', 'Le forfait a bien été ajouté !');

            return $this->redirectToRoute('plan_list'); // Assuming you have a route to list plans
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
