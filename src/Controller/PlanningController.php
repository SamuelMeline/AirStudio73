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
use Symfony\Component\Serializer\Normalizer\NormalizerInterface; // Import du normalizer
use Symfony\Component\Security\Http\Attribute\IsGranted;

class PlanningController extends AbstractController
{
    #[Route('/planning', name: 'planning')]
    public function index(ScheduleRepository $scheduleRepository, NormalizerInterface $normalizer): Response
    {
        // Récupérer tous les plannings
        $schedules = $scheduleRepository->findAll();

        // Normaliser les données en JSON, avec transformation des champs DateTime en format 'H:i'
        $schedulesData = array_map(function ($schedule) {
            return [
                'id' => $schedule->getId(),
                'day' => $schedule->getDay(),
                'courseName' => $schedule->getCourseName(),
                'startTime' => $schedule->getStartTime()->format('H:i'),
                'endTime' => $schedule->getEndTime()->format('H:i'),
            ];
        }, $schedules);

        // Créneaux horaires de la semaine
        $timeSlots = [
            '14:30 - 14:45',
            '14:45 - 15:00',
            '15:00 - 15:15',
            '15:15 - 15:30',
            '15:30 - 15:45',
            '15:45 - 16:00',
            '16:00 - 16:15',
            '16:15 - 16:30',
            '16:30 - 16:45',
            '16:45 - 17:00',
            '17:00 - 17:15',
            '17:15 - 17:30',
            '17:30 - 17:45',
            '17:45 - 18:00',
            '18:00 - 18:15',
            '18:15 - 18:30',
            '18:30 - 18:45',
            '18:45 - 19:00',
            '19:00 - 19:15',
            '19:15 - 19:30',
            '19:30 - 19:45',
            '19:45 - 20:00',
            '20:00 - 20:15',
            '20:15 - 20:30',
            '20:30 - 20:45'
        ];

        // Créneaux horaires du samedi
        $timeSlotsSaturday = [
            '09:00 - 09:15',
            '09:15 - 09:30',
            '09:30 - 09:45',
            '09:45 - 10:00',
            '10:00 - 10:15',
            '10:15 - 10:30',
            '10:30 - 10:45',
            '10:45 - 11:00',
            '11:15 - 11:30',
            '11:30 - 11:45',
            '11:45 - 12:00'
        ];

        return $this->render('planning/index.html.twig', [
            'schedules' => $schedulesData,  // Données normalisées avec des heures au format 'H:i'
            'timeSlots' => $timeSlots,  // Créneaux horaires pour la semaine
            'timeSlotsSaturday' => $timeSlotsSaturday,  // Créneaux horaires pour le samedi
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
