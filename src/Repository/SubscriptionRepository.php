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
        return $this->createQueryBuilder('s')
            ->innerJoin('s.user', 'u')
            ->andWhere('u.username = :userName') // Supposons que la propriété dans User s'appelle username
            ->setParameter('userName', $userName)
            ->getQuery()
            ->getResult();
    }
}
