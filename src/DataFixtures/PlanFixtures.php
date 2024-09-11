<?php 

// src/DataFixtures/PlanFixtures.php
namespace App\DataFixtures;

use App\Entity\Plan;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class PlanFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $plan = new Plan();
        $plan->setName('Plan Test');
        $plan->setStripePriceId('price_123456789'); // Un ID Stripe valide
        $plan->setEndDate(new \DateTime('+1 month'));

        $manager->persist($plan);
        $manager->flush();
    }
}
