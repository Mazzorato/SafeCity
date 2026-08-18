<?php

namespace App\Repository;

use App\Entity\Report;
use App\Entity\RoutingRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Centralise les requêtes Doctrine liées à RoutingRule.
 *
 * @extends ServiceEntityRepository<RoutingRule>
 */
class RoutingRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RoutingRule::class);
    }

    public function findMatching(Report $report): ?RoutingRule
    {
        if ($report->getCategory() === null || $report->getGravityLevel() === null) {
            return null;
        }

        return $this->createQueryBuilder('rule')
            ->addSelect('CASE WHEN rule.category = :category THEN 0 ELSE 1 END AS HIDDEN categoryRank')
            ->where('rule.enabled = true')
            ->andWhere('rule.gravityLevel = :gravity')
            ->andWhere('rule.category = :category OR rule.category IS NULL')
            ->setParameter('gravity', $report->getGravityLevel())
            ->setParameter('category', $report->getCategory())
            ->orderBy('categoryRank', 'ASC')
            ->addOrderBy('rule.priority', 'ASC')
            ->addOrderBy('rule.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}


