<?php

namespace App\Controller;

use App\Entity\ClassSession;
use App\Entity\Reservation;
use App\Repository\ClassSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use DateTime;

class ClassSessionController extends AbstractController
{
    #[Route('/calendar/{week}', name: 'app_calendar', requirements: ['week' => '\d+'], defaults: ['week' => null])]
    public function index(int $week = null, ClassSessionRepository $classSessionRepository): Response
    {
        if ($week === null) {
            $week = (new \DateTime())->format('W');
        }

        $year = (new \DateTime())->format('Y');
        $startOfWeek = new \DateTime();
        $startOfWeek->setISODate($year, $week);
        $endOfWeek = clone $startOfWeek;
        $endOfWeek->modify('+5 days');

        $classSessions = $classSessionRepository->findBy(['week' => $week]);

        return $this->render('class_session/index.html.twig', [
            'classSessions' => $classSessions,
            'currentWeek' => $week,
            'startOfWeek' => $startOfWeek,
            'endOfWeek' => $endOfWeek,
        ]);
    }

    #[Route('/reserve/{id}', name: 'app_reserve', methods: ['GET', 'POST'])]
    public function reserve(int $id, Request $request, ClassSessionRepository $classSessionRepository, EntityManagerInterface $entityManager): Response
    {
        $classSession = $classSessionRepository->find($id);

        if (!$classSession) {
            throw $this->createNotFoundException('The session does not exist');
        }

        if (new DateTime() > $classSession->getStartTime()) {
            $this->addFlash('error', 'You cannot reserve a session in the past.');
            return $this->redirectToRoute('app_calendar', ['week' => $classSession->getWeek()]);
        }

        if ($classSession->getCurrentParticipants() >= $classSession->getMaxParticipants()) {
            $this->addFlash('error', 'The session is fully booked.');
            return $this->redirectToRoute('app_calendar', ['week' => $classSession->getWeek()]);
        }

        if (!$this->getUser()) {
            $this->addFlash('error', 'You need to be logged in to reserve a session.');
            return $this->redirectToRoute('app_login'); // Assuming you have a login route
        }

        if ($request->isMethod('POST')) {
            $participants = $request->request->getInt('participants', 1);
            $recurrence = $request->request->get('recurrence', 'none');
            $currentWeek = $classSession->getWeek();

            if ($participants > $classSession->getMaxParticipants() - $classSession->getCurrentParticipants()) {
                $this->addFlash('error', 'Not enough spots available for the number of participants.');
                return $this->redirectToRoute('app_calendar', ['week' => $currentWeek]);
            }

            $classSession->setCurrentParticipants($classSession->getCurrentParticipants() + $participants);
            $entityManager->flush();

            // Enregistrer la réservation
            $reservation = new Reservation();
            $reservation->setUser($this->getUser());
            $reservation->setClassSession($classSession);
            $reservation->setParticipants($participants);
            $entityManager->persist($reservation);
            $entityManager->flush();

            if ($recurrence === 'weekly') {
                for ($i = 1; $i <= 4; $i++) {
                    $nextWeek = $currentWeek + $i;
                    $nextWeekSession = $classSessionRepository->findOneBy([
                        'week' => $nextWeek,
                        'dayOfWeek' => $classSession->getDayOfWeek(),
                        'startTime' => (clone $classSession->getStartTime())->modify('+' . $i . ' week'),
                        'endTime' => (clone $classSession->getEndTime())->modify('+' . $i . ' week'),
                    ]);

                    if (!$nextWeekSession) {
                        $nextWeekSession = clone $classSession;
                        $nextWeekSession->setWeek($nextWeek);
                        $nextWeekSession->setCurrentParticipants(0);
                        $nextWeekSession->setStartTime((clone $classSession->getStartTime())->modify('+' . $i . ' week'));
                        $nextWeekSession->setEndTime((clone $classSession->getEndTime())->modify('+' . $i . ' week'));
                        $entityManager->persist($nextWeekSession);
                    }

                    // Enregistrer la réservation récurrente
                    $recurringReservation = new Reservation();
                    $recurringReservation->setUser($this->getUser());
                    $recurringReservation->setClassSession($nextWeekSession);
                    $recurringReservation->setParticipants($participants);
                    $entityManager->persist($recurringReservation);
                }
                $entityManager->flush();
            }

            $this->addFlash('success', 'You have successfully reserved a spot in the session.');

            return $this->redirectToRoute('app_calendar', ['week' => $currentWeek]);
        }

        return $this->render('class_session/reserve.html.twig', [
            'classSession' => $classSession,
        ]);
    }
}
