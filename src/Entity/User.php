<?php

namespace App\Entity;

use App\Enum\RoleEnum;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    private ?string $lastName = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $password = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $googleId = null;

    #[ORM\Column]
    private ?\DateTime $registrationDate = null;

    #[ORM\Column(enumType: RoleEnum::class)]
    private ?RoleEnum $role = null;

    #[ORM\Column]
    private ?bool $cguAccepted = null;

    #[ORM\Column]
    private ?bool $accountActive = null;

    #[ORM\ManyToOne(inversedBy: 'users')]
    private ?City $city = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    private ?Profile $profile = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deleteRequestedAt = null;

    /**
     * @var Collection<int, Report>
     */
    #[ORM\OneToMany(targetEntity: Report::class, mappedBy: 'reporter', cascade: ['remove'])]
    private Collection $reports;

    /**
     * @var Collection<int, Comment>
     */
    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'author', cascade: ['remove'])]
    private Collection $comments;

    /**
     * @var Collection<int, Photo>
     */
    #[ORM\OneToMany(targetEntity: Photo::class, mappedBy: 'uploader', cascade: ['remove'])]
    private Collection $photos;

    /**
     * @var Collection<int, Notification>
     */
    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'recipient')]
    private Collection $notifications;

    /**
     * @var Collection<int, Event>
     */
    #[ORM\ManyToMany(targetEntity: Event::class, inversedBy: 'favoritedBy')]
    private Collection $favoriteEvents;

    #[ORM\Column]
    private bool $isVerified = false;

    public function __construct()
    {
        $this->reports = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->photos = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->favoriteEvents = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function getGoogleId(): ?string
    {
        return $this->googleId;
    }

    public function setGoogleId(?string $googleId): static
    {
        $this->googleId = $googleId;
        return $this;
    }

    public function getRegistrationDate(): ?\DateTime
    {
        return $this->registrationDate;
    }

    public function setRegistrationDate(\DateTime $registrationDate): static
    {
        $this->registrationDate = $registrationDate;
        return $this;
    }

    public function getRole(): ?RoleEnum
    {
        return $this->role;
    }

    public function setRole(RoleEnum $role): static
    {
        $this->role = $role;
        return $this;
    }

    public function isCguAccepted(): ?bool
    {
        return $this->cguAccepted;
    }

    public function setCguAccepted(bool $cguAccepted): static
    {
        $this->cguAccepted = $cguAccepted;
        return $this;
    }

    public function isAccountActive(): ?bool
    {
        return $this->accountActive;
    }

    public function setAccountActive(bool $accountActive): static
    {
        $this->accountActive = $accountActive;
        return $this;
    }

    public function getCity(): ?City
    {
        return $this->city;
    }

    public function setCity(?City $city): static
    {
        $this->city = $city;
        return $this;
    }

    public function getProfile(): ?Profile
    {
        return $this->profile;
    }

    public function setProfile(?Profile $profile): static
    {
        $this->profile = $profile;
        return $this;
    }

    public function getDeleteRequestedAt(): ?\DateTimeImmutable
    {
        return $this->deleteRequestedAt;
    }

    public function setDeleteRequestedAt(?\DateTimeImmutable $deleteRequestedAt): static
    {
        $this->deleteRequestedAt = $deleteRequestedAt;
        return $this;
    }

    public function getReports(): Collection
    {
        return $this->reports;
    }

    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function getPhotos(): Collection
    {
        return $this->photos;
    }

    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    /**
     * @return Collection<int, Event>
     */
    public function getFavoriteEvents(): Collection
    {
        return $this->favoriteEvents;
    }

    public function addFavoriteEvent(Event $event): static
    {
        if (!$this->favoriteEvents->contains($event)) {
            $this->favoriteEvents->add($event);
        }

        return $this;
    }

    public function removeFavoriteEvent(Event $event): static
    {
        $this->favoriteEvents->removeElement($event);

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = [$this->role?->value ?? 'ROLE_USER'];
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function eraseCredentials(): void {}

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;
        return $this;
    }
}