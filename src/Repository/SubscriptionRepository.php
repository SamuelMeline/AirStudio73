<?php

namespace App\Repository;

use App\Entity\Subscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Subscription>
 */
class SubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    /**
     * @return Subscription[] Returns an array of Subscription objects
     */
    public function findByUserName(string $userName): array
    {
        // Ajout de débogage pour vérifier la requête
        error_log('Finding subscriptions for user: ' . $userName);

        return $this->createQueryBuilder('s')
            ->andWhere('s.userName = :userName')
            ->setParameter('userName', $userName)
            ->getQuery()
            ->getResult();
    }
}
