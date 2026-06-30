<?php

namespace App\Entity;

use App\Repository\EventFavoriteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EventFavoriteRepository::class)]
class EventFavorite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?bool $reminderActive = null;

    #[ORM\Column]
    private ?\DateTime $addedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $eventUser = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Event $event = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isReminderActive(): ?bool
    {
        return $this->reminderActive;
    }

    public function setReminderActive(bool $reminderActive): static
    {
        $this->reminderActive = $reminderActive;

        return $this;
    }

    public function getAddedAt(): ?\DateTime
    {
        return $this->addedAt;
    }

    public function setAddedAt(\DateTime $addedAt): static
    {
        $this->addedAt = $addedAt;

        return $this;
    }

    public function getEventUser(): ?User
    {
        return $this->eventUser;
    }

    public function setEventUser(?User $eventUser): static
    {
        $this->eventUser = $eventUser;

        return $this;
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(?Event $event): static
    {
        $this->event = $event;

        return $this;
    }
}
