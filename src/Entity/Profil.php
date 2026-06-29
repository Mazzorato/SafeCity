<?php

namespace App\Entity;

use App\Repository\ProfilRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProfilRepository::class)]
class Profil
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?bool $notifUrgencies = null;

    #[ORM\Column]
    private ?bool $notifWeather = null;

    #[ORM\Column]
    private ?bool $notifTransport = null;

    #[ORM\Column]
    private ?bool $notifEvents = null;

    #[ORM\Column]
    private ?bool $microphoneAccess = null;

    #[ORM\Column]
    private ?bool $cameraAccess = null;

    #[ORM\Column]
    private ?bool $locationAccess = null;

    #[ORM\Column(length: 10)]
    private ?string $language = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isNotifUrgencies(): ?bool
    {
        return $this->notifUrgencies;
    }

    public function setNotifUrgencies(bool $notifUrgencies): static
    {
        $this->notifUrgencies = $notifUrgencies;

        return $this;
    }

    public function isNotifWeather(): ?bool
    {
        return $this->notifWeather;
    }

    public function setNotifWeather(bool $notifWeather): static
    {
        $this->notifWeather = $notifWeather;

        return $this;
    }

    public function isNotifTransport(): ?bool
    {
        return $this->notifTransport;
    }

    public function setNotifTransport(bool $notifTransport): static
    {
        $this->notifTransport = $notifTransport;

        return $this;
    }

    public function isNotifEvents(): ?bool
    {
        return $this->notifEvents;
    }

    public function setNotifEvents(bool $notifEvents): static
    {
        $this->notifEvents = $notifEvents;

        return $this;
    }

    public function isMicrophoneAccess(): ?bool
    {
        return $this->microphoneAccess;
    }

    public function setMicrophoneAccess(bool $microphoneAccess): static
    {
        $this->microphoneAccess = $microphoneAccess;

        return $this;
    }

    public function isCameraAccess(): ?bool
    {
        return $this->cameraAccess;
    }

    public function setCameraAccess(bool $cameraAccess): static
    {
        $this->cameraAccess = $cameraAccess;

        return $this;
    }

    public function isLocationAccess(): ?bool
    {
        return $this->locationAccess;
    }

    public function setLocationAccess(bool $locationAccess): static
    {
        $this->locationAccess = $locationAccess;

        return $this;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(string $language): static
    {
        $this->language = $language;

        return $this;
    }
}
