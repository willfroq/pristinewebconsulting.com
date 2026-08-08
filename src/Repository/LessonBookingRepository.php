<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LessonBooking;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<LessonBooking> */
final class LessonBookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LessonBooking::class);
    }

    /** @return list<LessonBooking> */
    public function upcomingFor(User $user): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.student = :user')
            ->andWhere('b.startsAt > :now')
            ->andWhere('b.status = :status')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('status', 'approved')
            ->orderBy('b.startsAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<LessonBooking> */
    public function completedFor(User $user): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.student = :user')
            ->andWhere('b.startsAt <= :now')
            ->andWhere('b.status = :status')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('status', 'approved')
            ->orderBy('b.startsAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<LessonBooking> */
    public function proposedFor(User $user): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.student = :user')
            ->andWhere('b.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'proposed')
            ->orderBy('b.startsAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<LessonBooking> */
    public function pendingProposals(): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.status = :status')
            ->setParameter('status', 'proposed')
            ->orderBy('b.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<string> */
    public function bookedTimesForDay(\DateTimeImmutable $day): array
    {
        $displayTimezone = $day->getTimezone();
        $utc = new \DateTimeZone('UTC');
        $start = $day->setTimezone($utc);
        $end = $day->modify('+1 day')->setTimezone($utc);
        $bookings = $this->createQueryBuilder('b')
            ->andWhere('b.startsAt >= :start AND b.startsAt < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (LessonBooking $booking): string => $booking->getStartsAt()
                ->setTimezone($displayTimezone)
                ->format('H:i'),
            $bookings,
        );
    }
}
