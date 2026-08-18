<?php

namespace App\Repository;

use App\Entity\ReportStatusHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Centralise les requêtes Doctrine liées à l’historique des statuts.
 *
 * @extends ServiceEntityRepository<ReportStatusHistory>
 */
final class ReportStatusHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReportStatusHistory::class);
    }
}


