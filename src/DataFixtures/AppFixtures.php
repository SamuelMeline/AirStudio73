<?php

namespace App\DataFixtures;

use App\Entity\ClassSession;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use DateTime;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $sessions = [
            ['Yoga aérien', '14:30', '15:30', 1, 4],
            ['Yoga aérien', '16:00', '17:00', 1, 4],
            ['Pole Dance', '17:30', '19:00', 1, 5],
            ['Hammock', '19:35', '21:00', 1, 3],
            ['Yoga aérien', '16:30', '17:30', 2, 4],
            ['Pole Dance', '18:00', '19:30', 2, 5],
            ['Yoga aérien', '20:00', '21:00', 2, 4],
            ['Hammock', '15:00', '16:30', 3, 3],
            ['Yoga aérien', '17:00', '18:00', 3, 4],
            ['Souplesse', '18:00', '19:00', 3, 8],
            ['Pole Dance', '14:30', '16:00', 4, 5],
            ['Cours à la demande', '16:00', '18:30', 4, 5],
            ['Yoga aérien', '18:30', '19:30', 4, 4],
            ['Yoga aérien', '15:30', '16:30', 5, 4],
            ['Pole Dance', '17:00', '18:30', 5, 5],
            ['Hammock', '18:30', '20:00', 5, 3],
            ['Yoga aérien', '09:00', '10:00', 6, 4],
            ['Pole Dance', '10:30', '12:00', 6, 5],
        ];

        $startWeek = (new \DateTime())->format("W");
        $endWeek = $startWeek + 4; // Générer des sessions pour 4 semaines

        for ($week = $startWeek; $week <= $endWeek; $week++) {
            foreach ($sessions as $sessionData) {
                $session = new ClassSession();
                $session->setName($sessionData[0]);
                $session->setStartTime(new DateTime("next " . $this->getDayName($sessionData[3]) . " " . $sessionData[1]));
                $session->setEndTime(new DateTime("next " . $this->getDayName($sessionData[3]) . " " . $sessionData[2]));
                $session->setDayOfWeek($sessionData[3]);
                $session->setMaxParticipants($sessionData[4]);
                $session->setCurrentParticipants(0);
                $session->setWeek($week);

                $manager->persist($session);
            }
        }

        $manager->flush();
    }

    private function getDayName(int $dayNumber): string
    {
        $days = [
            1 => 'monday',
            2 => 'tuesday',
            3 => 'wednesday',
            4 => 'thursday',
            5 => 'friday',
            6 => 'saturday',
        ];

        return $days[$dayNumber];
    }
}
