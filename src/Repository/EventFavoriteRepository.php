<?php

namespace App\Repository;

use App\Entity\Event;
use App\Entity\EventFavorite;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Centralise les requêtes Doctrine liées à EventFavorite.
 *
 * @extends ServiceEntityRepository<EventFavorite>
 */
class EventFavoriteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventFavorite::class);
    }

    /**
     * @return EventFavorite[]
     */
    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('favorite')
            ->addSelect('event')
            ->innerJoin('favorite.event', 'event')
            ->andWhere('favorite.eventUser = :user')
            ->setParameter('user', $user)
            ->orderBy('event.startedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForUserAndEvent(User $user, Event $event): ?EventFavorite
    {
        return $this->findOneBy([
            'eventUser' => $user,
            'event' => $event,
        ]);
    }

    /**
     * @return int[]
     */
    public function findEventIdsForUser(User $user): array
    {
        $rows = $this->createQueryBuilder('favorite')
            ->select('IDENTITY(favorite.event) AS eventId')
            ->andWhere('favorite.eventUser = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getArrayResult();

        return array_map(
            static fn (array $row): int => (int) $row['eventId'],
            $rows,
        );
    }

    /**
     * Retourne les rappels non envoyés dont l’événement commence dans les
     * prochaines 24 heures et respecte les préférences du compte.
     *
     * @return EventFavorite[]
     */
    public function findDueReminders(
        \DateTimeImmutable $now,
        \DateTimeImmutable $until,
    ): array {
        return $this->createQueryBuilder('favorite')
            ->addSelect('event', 'eventUser', 'profile')
            ->innerJoin('favorite.event', 'event')
            ->innerJoin('favorite.eventUser', 'eventUser')
            ->innerJoin('eventUser.profile', 'profile')
            ->andWhere('favorite.reminderActive = :active')
            ->andWhere('favorite.remindedAt IS NULL')
            ->andWhere('event.startedAt > :now')
            ->andWhere('event.startedAt <= :until')
            ->andWhere('eventUser.accountActive = :active')
            ->andWhere('profile.eventNotifications = :active')
            ->setParameter('active', true)
            ->setParameter('now', $now)
            ->setParameter('until', $until)
            ->orderBy('event.startedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}


