<?php 

// src/Repository/PromoCodeUsageRepository.php

namespace App\Repository;

use App\Entity\PromoCodeUsage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PromoCodeUsageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PromoCodeUsage::class);
    }
}
