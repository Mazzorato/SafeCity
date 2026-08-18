<?php

namespace App\Entity;

use App\Repository\EventFavoriteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EventFavoriteRepository::class)]
#[ORM\UniqueConstraint(
    name: 'UNIQ_EVENT_FAVORITE_USER_EVENT',
    columns: ['event_user_id', 'event_id']
)]
/**
 * Modèle Doctrine représentant les données persistées de EventFavorite.
 */
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

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $remindedAt = null;

    #[ORM\ManyToOne(inversedBy: 'eventFavorites')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $eventUser = null;

    #[ORM\ManyToOne(inversedBy: 'eventFavorites')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
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

    public function getRemindedAt(): ?\DateTimeImmutable
    {
        return $this->remindedAt;
    }

    public function setRemindedAt(?\DateTimeImmutable $remindedAt): static
    {
        // Cette date rend l’envoi idempotent : un favori ne reçoit qu’un rappel.
        $this->remindedAt = $remindedAt;

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


