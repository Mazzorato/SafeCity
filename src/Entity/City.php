<?php

namespace App\Entity;

use App\Repository\CityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CityRepository::class)]
class City
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 10)]
    private ?string $postalCode = null;

    #[ORM\Column(length: 100)]
    private ?string $department = null;

    #[ORM\Column]
    private ?bool $available = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable:true)]
    private ?string $latitude = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable:true)]
    private ?string $longitude = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'city')]
    private Collection $users;

    /**
     * @var Collection<int, LocalService>
     */
    #[ORM\OneToMany(targetEntity: LocalService::class, mappedBy: 'city')]
    private Collection $localServices;

    /**
     * @var Collection<int, Transport>
     */
    #[ORM\OneToMany(targetEntity: Transport::class, mappedBy: 'city')]
    private Collection $transports;

    /**
     * @var Collection<int, Parking>
     */
    #[ORM\OneToMany(targetEntity: Parking::class, mappedBy: 'city')]
    private Collection $parkings;

    /**
     * @var Collection<int, WeatherAlert>
     */
    #[ORM\OneToMany(targetEntity: WeatherAlert::class, mappedBy: 'city', orphanRemoval: true)]
    private Collection $weatherAlerts;

    /**
     * @var Collection<int, Event>
     */
    #[ORM\OneToMany(targetEntity: Event::class, mappedBy: 'city')]
    private Collection $events;

    /**
     * @var Collection<int, News>
     */
    #[ORM\OneToMany(targetEntity: News::class, mappedBy: 'city')]
    private Collection $news;

    /** 
     * @var Collection<int, Report>
     */
    #[ORM\OneToMany(targetEntity: Report::class, mappedBy: 'city')]
    private Collection $reports;

    public function __construct()
    {
        $this->users = new ArrayCollection();
        $this->localServices = new ArrayCollection();
        $this->transports = new ArrayCollection();
        $this->parkings = new ArrayCollection();
        $this->weatherAlerts = new ArrayCollection();
        $this->events = new ArrayCollection();
        $this->news = new ArrayCollection();
        $this->reports = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(string $postalCode): static
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    public function getDepartment(): ?string
    {
        return $this->department;
    }

    public function setDepartment(string $department): static
    {
        $this->department = $department;

        return $this;
    }

    public function isAvailable(): ?bool
    {
        return $this->available;
    }

    public function setAvailable(bool $available): static
    {
        $this->available = $available;

        return $this;
    }

    public function getLatitude(): ?string
    {
        return $this->latitude;
    }
    
    public function setLatitude(?string $latitude): static
    {
        $this->latitude = $latitude;
        return $this;
    }

    public function getLongitude(): ?string
    {
        return $this->longitude;
    }

    public function setLongitude(?string $longitude): static
    {
        $this->longitude = $longitude;
        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): static
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            $user->setCity($this);
        }

        return $this;
    }

    public function removeUser(User $user): static
    {
        if ($this->users->removeElement($user)) {
            // set the owning side to null (unless already changed)
            if ($user->getCity() === $this) {
                $user->setCity(null);
            }
        }

        return $this;
    }

    /** 
     * @return Collection<int, Report>
     */
    public function getReports(): Collection
    {
        return $this->reports;
    }
    
    public function addReport(Report $report): static
    {
        if (!$this->reports->contains($report)) {
            $this->reports->add($report);
            $report->setCity($this);
        }
        return $this;
    }

    public function removeReport(Report $report): static
    {
        if (!$this->reports->removeElement($report)) {
            if ($report->getCity() === $this) {
                $report->setCity(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, LocalService>
     */
    public function getLocalServices(): Collection
    {
        return $this->localServices;
    }

    public function addLocalService(LocalService $localService): static
    {
        if (!$this->localServices->contains($localService)) {
            $this->localServices->add($localService);
            $localService->setCity($this);
        }

        return $this;
    }

    public function removeLocalService(LocalService $localService): static
    {
        if ($this->localServices->removeElement($localService)) {
            // set the owning side to null (unless already changed)
            if ($localService->getCity() === $this) {
                $localService->setCity(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Transport>
     */
    public function getTransports(): Collection
    {
        return $this->transports;
    }

    public function addTransport(Transport $transport): static
    {
        if (!$this->transports->contains($transport)) {
            $this->transports->add($transport);
            $transport->setCity($this);
        }

        return $this;
    }

    public function removeTransport(Transport $transport): static
    {
        if ($this->transports->removeElement($transport)) {
            // set the owning side to null (unless already changed)
            if ($transport->getCity() === $this) {
                $transport->setCity(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Parking>
     */
    public function getParkings(): Collection
    {
        return $this->parkings;
    }

    public function addParking(Parking $parking): static
    {
        if (!$this->parkings->contains($parking)) {
            $this->parkings->add($parking);
            $parking->setCity($this);
        }

        return $this;
    }

    public function removeParking(Parking $parking): static
    {
        if ($this->parkings->removeElement($parking)) {
            // set the owning side to null (unless already changed)
            if ($parking->getCity() === $this) {
                $parking->setCity(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, WeatherAlert>
     */
    public function getWeatherAlerts(): Collection
    {
        return $this->weatherAlerts;
    }

    public function addWeatherAlert(WeatherAlert $weatherAlert): static
    {
        if (!$this->weatherAlerts->contains($weatherAlert)) {
            $this->weatherAlerts->add($weatherAlert);
            $weatherAlert->setCity($this);
        }

        return $this;
    }

    public function removeWeatherAlert(WeatherAlert $weatherAlert): static
    {
        if ($this->weatherAlerts->removeElement($weatherAlert)) {
            // set the owning side to null (unless already changed)
            if ($weatherAlert->getCity() === $this) {
                $weatherAlert->setCity(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Event>
     */
    public function getEvents(): Collection
    {
        return $this->events;
    }

    public function addEvent(Event $event): static
    {
        if (!$this->events->contains($event)) {
            $this->events->add($event);
            $event->setCity($this);
        }

        return $this;
    }

    public function removeEvent(Event $event): static
    {
        if ($this->events->removeElement($event)) {
            // set the owning side to null (unless already changed)
            if ($event->getCity() === $this) {
                $event->setCity(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, News>
     */
    public function getNews(): Collection
    {
        return $this->news;
    }

    public function addNews(News $news): static
    {
        if (!$this->news->contains($news)) {
            $this->news->add($news);
            $news->setCity($this);
        }

        return $this;
    }

    public function removeNews(News $news): static
    {
        if ($this->news->removeElement($news)) {
            // set the owning side to null (unless already changed)
            if ($news->getCity() === $this) {
                $news->setCity(null);
            }
        }

        return $this;
    }
}
