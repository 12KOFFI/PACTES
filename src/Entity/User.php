<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity()]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private string $email;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(type: 'string')]
    private string $password;
    

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $nom;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $prenoms;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $nomjeunefille;

    #[ORM\Column(type: 'string', length: 10, nullable: true)]
    private ?string $sexe;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $datenaissance;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $lieunaissance;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $residence;

    #[ORM\Column(type: 'string', length: 15, nullable: true)]
    private ?string $situationmat;

    #[ORM\Column(type: 'string', length: 22, nullable: true)]
    private ?string $contact;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $numextrait;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $nationalite;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $nompere;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $nommere;


    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials()
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }
    
    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    public function getPrenoms(): ?string
    {
        return $this->prenoms;
    }

    public function setPrenoms(?string $prenoms): self
    {
        $this->prenoms = $prenoms;
        return $this;
    }

    public function getNomjeunefille(): ?string
    {
        return $this->nomjeunefille;
    }

    public function setNomjeunefille(?string $nomjeunefille): self
    {
        $this->nomjeunefille = $nomjeunefille;
        return $this;
    }

    public function getSexe(): ?string
    {
        return $this->sexe;
    }

    public function setSexe(?string $sexe): self
    {
        $this->sexe = $sexe;
        return $this;
    }

    public function getDatenaissance(): ?\DateTimeInterface
    {
        return $this->datenaissance;
    }

    public function setDatenaissance(?\DateTimeInterface $datenaissance): self
    {
        $this->datenaissance = $datenaissance;
        return $this;
    }

    public function getLieunaissance(): ?string
    {
        return $this->lieunaissance;
    }

    public function setLieunaissance(?string $lieunaissance): self
    {
        $this->lieunaissance = $lieunaissance;
        return $this;
    }

    public function getResidence(): ?string
    {
        return $this->residence;
    }

    public function setResidence(?string $residence): self
    {
        $this->residence = $residence;
        return $this;
    }

    public function getSituationmat(): ?string
    {
        return $this->situationmat;
    }

    public function setSituationmat(?string $situationmat): self
    {
        $this->situationmat = $situationmat;
        return $this;
    }

    public function getContact(): ?string
    {
        return $this->contact;
    }

    public function setContact(?string $contact): self
    {
        $this->contact = $contact;
        return $this;
    }

    public function getNumextrait(): ?string
    {
        return $this->numextrait;
    }

    public function setNumextrait(?string $numextrait): self
    {
        $this->numextrait = $numextrait;
        return $this;
    }

    public function getNationalite(): ?string
    {
        return $this->nationalite;
    }

    public function setNationalite(?string $nationalite): self
    {
        $this->nationalite = $nationalite;
        return $this;
    }

    public function getNompere(): ?string
    {
        return $this->nompere;
    }

    public function setNompere(?string $nompere): self
    {
        $this->nompere = $nompere;
        return $this;
    }

    public function getNommere(): ?string
    {
        return $this->nommere;
    }

    public function setNommere(?string $nommere): self
    {
        $this->nommere = $nommere;
        return $this;
    }

    public function isIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeImmutable $lastLoginAt): self
    {
        $this->lastLoginAt = $lastLoginAt;
        return $this;
    }
}
