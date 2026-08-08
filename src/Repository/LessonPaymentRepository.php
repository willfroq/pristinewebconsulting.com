<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LessonPayment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<LessonPayment> */
final class LessonPaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LessonPayment::class);
    }

    public function findPendingForStudent(\App\Entity\User $student): ?LessonPayment
    {
        return $this->findOneBy(['student' => $student, 'status' => 'PENDING']);
    }

    /** @return list<LessonPayment> */
    public function pending(): array
    {
        return $this->findBy(['status' => 'PENDING'], ['createdAt' => 'ASC']);
    }
}
