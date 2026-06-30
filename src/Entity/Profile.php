<?php

namespace App\Entity;

use App\Repository\ProfileRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProfileRepository::class)]
class Profile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?bool $emergencyNotifications = null;

    #[ORM\Column]
    private ?bool $weatherNotifications = null;

    #[ORM\Column]
    private ?bool $transportNotifications = null;

    #[ORM\Column]
    private ?bool $eventNotifications = null;

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

    public function isEmergencyNotifications(): ?bool
    {
        return $this->emergencyNotifications;
    }

    public function setEmergencyNotifications(bool $emergencyNotifications): static
    {
        $this->emergencyNotifications = $emergencyNotifications;

        return $this;
    }

    public function isWeatherNotifications(): ?bool
    {
        return $this->weatherNotifications;
    }

    public function setWeatherNotifications(bool $weatherNotifications): static
    {
        $this->weatherNotifications = $weatherNotifications;

        return $this;
    }

    public function isTransportNotifications(): ?bool
    {
        return $this->transportNotifications;
    }

    public function setTransportNotifications(bool $transportNotifications): static
    {
        $this->transportNotifications = $transportNotifications;

        return $this;
    }

    public function isEventNotifications(): ?bool
    {
        return $this->eventNotifications;
    }

    public function setEventNotifications(bool $eventNotifications): static
    {
        $this->eventNotifications = $eventNotifications;

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
