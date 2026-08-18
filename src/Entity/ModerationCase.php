<?php

namespace App\Entity;

use App\Enum\ModerationStatusEnum;
use App\Enum\ModerationTargetEnum;
use App\Repository\ModerationCaseRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ModerationCaseRepository::class)]
#[ORM\Index(name: 'IDX_B3D05FEACB24C6F8', columns: ['target_type', 'target_id', 'status'])]
/**
 * Modèle Doctrine représentant les données persistées de ModerationCase.
 */
class ModerationCase
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(enumType: ModerationTargetEnum::class)]
    private ?ModerationTargetEnum $targetType = null;

    #[ORM\Column]
    private ?int $targetId = null;

    #[ORM\Column(length: 255)]
    private ?string $reason = null;

    #[ORM\Column(enumType: ModerationStatusEnum::class)]
    private ModerationStatusEnum $status = ModerationStatusEnum::FLAGGED;

    #[ORM\Column]
    private ?\DateTimeImmutable $reportedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $moderatedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $reporter = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $author = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $moderator = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTargetType(): ?ModerationTargetEnum
    {
        return $this->targetType;
    }

    public function setTargetType(ModerationTargetEnum $targetType): static
    {
        $this->targetType = $targetType;

        return $this;
    }

    public function getTargetId(): ?int
    {
        return $this->targetId;
    }

    public function setTargetId(int $targetId): static
    {
        $this->targetId = $targetId;

        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(string $reason): static
    {
        $this->reason = $reason;

        return $this;
    }

    public function getStatus(): ModerationStatusEnum
    {
        return $this->status;
    }

    public function setStatus(ModerationStatusEnum $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getReportedAt(): ?\DateTimeImmutable
    {
        return $this->reportedAt;
    }

    public function setReportedAt(\DateTimeImmutable $reportedAt): static
    {
        $this->reportedAt = $reportedAt;

        return $this;
    }

    public function getModeratedAt(): ?\DateTimeImmutable
    {
        return $this->moderatedAt;
    }

    public function setModeratedAt(?\DateTimeImmutable $moderatedAt): static
    {
        $this->moderatedAt = $moderatedAt;

        return $this;
    }

    public function getReporter(): ?User
    {
        return $this->reporter;
    }

    public function setReporter(?User $reporter): static
    {
        $this->reporter = $reporter;

        return $this;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getModerator(): ?User
    {
        return $this->moderator;
    }

    public function setModerator(?User $moderator): static
    {
        $this->moderator = $moderator;

        return $this;
    }
}
