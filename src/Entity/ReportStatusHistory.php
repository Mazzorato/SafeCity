<?php

namespace App\Entity;

use App\Enum\ReportStatusEnum;
use App\Repository\ReportStatusHistoryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Conserve une transition datée du statut d’un signalement.
 */
#[ORM\Entity(repositoryClass: ReportStatusHistoryRepository::class)]
#[ORM\Index(name: 'IDX_REPORT_STATUS_TIMELINE', columns: ['report_id', 'changed_at'])]
#[ORM\Index(name: 'IDX_REPORT_STATUS_CHANGED_BY', columns: ['changed_by_id'])]
class ReportStatusHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(enumType: ReportStatusEnum::class)]
    private ?ReportStatusEnum $status = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $changedAt = null;

    #[ORM\ManyToOne(inversedBy: 'statusHistory')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Report $report = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $changedBy = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStatus(): ?ReportStatusEnum
    {
        return $this->status;
    }

    public function setStatus(ReportStatusEnum $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getChangedAt(): ?\DateTimeImmutable
    {
        return $this->changedAt;
    }

    public function setChangedAt(\DateTimeImmutable $changedAt): static
    {
        $this->changedAt = $changedAt;

        return $this;
    }

    public function getReport(): ?Report
    {
        return $this->report;
    }

    public function setReport(?Report $report): static
    {
        $this->report = $report;

        return $this;
    }

    public function getChangedBy(): ?User
    {
        return $this->changedBy;
    }

    public function setChangedBy(?User $changedBy): static
    {
        $this->changedBy = $changedBy;

        return $this;
    }
}


