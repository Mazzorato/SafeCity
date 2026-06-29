<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $first_name = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $password = null;

    #[ORM\Column]
    private ?\DateTime $register_date = null;

    #[ORM\Column]
    private ?bool $cgu_accepted = null;

    #[ORM\Column]
    private ?bool $compte_actived = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): ?string
    {
        return $this->first_name;
    }

    public function setFirstName(string $first_name): static
    {
        $this->first_name = $first_name;

        return $this;
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

    public function getRegisterDate(): ?\DateTime
    {
        return $this->register_date;
    }

    public function setRegisterDate(\DateTime $register_date): static
    {
        $this->register_date = $register_date;

        return $this;
    }

    public function isCguAccepted(): ?bool
    {
        return $this->cgu_accepted;
    }

    public function setCguAccepted(bool $cgu_accepted): static
    {
        $this->cgu_accepted = $cgu_accepted;

        return $this;
    }

    public function isCompteActived(): ?bool
    {
        return $this->compte_actived;
    }

    public function setCompteActived(bool $compte_actived): static
    {
        $this->compte_actived = $compte_actived;

        return $this;
    }
}
