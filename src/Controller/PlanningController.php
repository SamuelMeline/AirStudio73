<?php

namespace App\Controller;

use App\Entity\Schedule;
use App\Form\ScheduleType;
use App\Repository\ScheduleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class PlanningController extends AbstractController
{
    #[Route('/planning', name: 'planning')]
    public function index(ScheduleRepository $scheduleRepository): Response
    {
        $schedules = $scheduleRepository->findAll();

        $timeSlots = [
            '14:30 - 15:00', '15:00 - 15:30', '15:30 - 16:00', '16:00 - 16:30', 
            '16:30 - 17:00', '17:00 - 17:30', '17:30 - 18:00', '18:00 - 18:30', 
            '18:30 - 19:00', '19:00 - 19:30', '19:30 - 20:00', '20:00 - 20:30', 
            '20:30 - 21:00'
        ];

        $timeSlotsSaturday = [
            '09:00 - 09:30', '09:30 - 10:00', '10:00 - 10:30', '10:30 - 11:00', 
            '11:00 - 11:30', '11:30 - 12:00'
        ];

        return $this->render('planning/index.html.twig', [
            'schedules' => $schedules,
            'timeSlots' => $timeSlots,
            'timeSlotsSaturday' => $timeSlotsSaturday,
        ]);
    }

    #[Route('/planning/edit/{id?}', name: 'planning_edit')]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, EntityManagerInterface $em, ?Schedule $schedule = null): Response
    {
        if (!$schedule) {
            $schedule = new Schedule();
        }

        $form = $this->createForm(ScheduleType::class, $schedule);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($schedule);
            $em->flush();

            $this->addFlash('success', 'Le planning a été mis à jour avec succès.');

            return $this->redirectToRoute('planning');
        }

        return $this->render('planning/edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/planning/delete/{id}', name: 'planning_delete')]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(EntityManagerInterface $em, Schedule $schedule): Response
    {
        $em->remove($schedule);
        $em->flush();

        $this->addFlash('success', 'La session de cours a été supprimée avec succès.');

        return $this->redirectToRoute('planning');
    }
}
