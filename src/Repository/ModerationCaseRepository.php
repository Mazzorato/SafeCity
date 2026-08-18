<?php

namespace App\Repository;

use App\Entity\ModerationCase;
use App\Enum\ModerationStatusEnum;
use App\Enum\ModerationTargetEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Centralise les requêtes Doctrine liées à ModerationCase.
 *
 * @extends ServiceEntityRepository<ModerationCase>
 */
class ModerationCaseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ModerationCase::class);
    }

    public function hasOpenCase(ModerationTargetEnum $targetType, int $targetId): bool
    {
        return $this->count([
            'targetType' => $targetType,
            'targetId' => $targetId,
            'status' => ModerationStatusEnum::FLAGGED,
        ]) > 0;
    }

    /**
     * @return int[]
     */
    public function findHiddenTargetIds(ModerationTargetEnum $targetType): array
    {
        $rows = $this->createQueryBuilder('moderation')
            ->select('moderation.targetId')
            ->where('moderation.targetType = :targetType')
            ->andWhere('moderation.status = :status')
            ->setParameter('targetType', $targetType)
            ->setParameter('status', ModerationStatusEnum::HIDDEN)
            ->getQuery()
            ->getArrayResult();

        return array_map(
            static fn (array $row): int => (int) $row['targetId'],
            $rows
        );
    }

    /**
     * Supprime les dossiers dont la cible disparaît dans la même transaction.
     *
     * @param int[] $targetIds
     */
    public function deleteForTargets(ModerationTargetEnum $targetType, array $targetIds): int
    {
        $targetIds = array_values(array_unique(array_filter(
            array_map('intval', $targetIds),
            static fn (int $targetId): bool => $targetId > 0,
        )));
        if ($targetIds === []) {
            return 0;
        }

        return $this->createQueryBuilder('moderation')
            ->delete()
            ->where('moderation.targetType = :targetType')
            ->andWhere('moderation.targetId IN (:targetIds)')
            ->setParameter('targetType', $targetType)
            ->setParameter('targetIds', $targetIds)
            ->getQuery()
            ->execute();
    }
}
