<?php

// src/Repository/CourseInstanceRepository.php

namespace App\Repository;

use App\Entity\CourseInstance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CourseInstance>
 *
 * @method CourseInstance|null find($id, $lockMode = null, $lockVersion = null)
 * @method CourseInstance|null findOneBy(array $criteria, array $orderBy = null)
 * @method CourseInstance[]    findAll()
 * @method CourseInstance[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CourseInstanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CourseInstance::class);
    }
}
