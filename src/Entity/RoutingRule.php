<?php

namespace App\Entity;

use App\Enum\GravityLevelEnum;
use App\Repository\RoutingRuleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RoutingRuleRepository::class)]
#[ORM\Index(name: 'IDX_9F4B13C7C49A7F65', columns: ['gravity_level', 'enabled', 'priority'])]
/**
 * Modèle Doctrine représentant les données persistées de RoutingRule.
 */
class RoutingRule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(enumType: GravityLevelEnum::class)]
    private ?GravityLevelEnum $gravityLevel = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?ReportCategory $category = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?EmergencyService $emergencyService = null;

    #[ORM\Column(options: ['default' => 100])]
    private int $priority = 100;

    #[ORM\Column(options: ['default' => true])]
    private bool $enabled = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGravityLevel(): ?GravityLevelEnum
    {
        return $this->gravityLevel;
    }

    public function setGravityLevel(GravityLevelEnum $gravityLevel): static
    {
        $this->gravityLevel = $gravityLevel;

        return $this;
    }

    public function getCategory(): ?ReportCategory
    {
        return $this->category;
    }

    public function setCategory(?ReportCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getEmergencyService(): ?EmergencyService
    {
        return $this->emergencyService;
    }

    public function setEmergencyService(?EmergencyService $emergencyService): static
    {
        $this->emergencyService = $emergencyService;

        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): static
    {
        $this->priority = $priority;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }
}


