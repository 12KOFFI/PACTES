<?php

namespace App\Entity;

use App\Repository\SessionRecrutementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SessionRecrutementRepository::class)]
#[ORM\Table(name: 'session_recrutement')]
#[ORM\UniqueConstraint(name: 'uniq_session_annee_vague', columns: ['annee', 'numero_vague'])]
class SessionRecrutement
{
    public const STATUT_BROUILLON = 'BROUILLON';
    public const STATUT_OUVERTE = 'OUVERTE';
    public const STATUT_FERMEE = 'FERMEE';
    public const STATUT_CLOTUREE = 'CLOTUREE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 150)]
    private ?string $nom = null;

    #[ORM\Column(type: 'integer')]
    private ?int $annee = null;

    #[ORM\Column(name: 'numero_vague', type: 'integer')]
    private ?int $numeroVague = null;

    #[ORM\Column(name: 'date_ouverture', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateOuverture = null;

    #[ORM\Column(name: 'date_cloture', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateCloture = null;

    #[ORM\Column(name: 'date_reference_age', type: 'date')]
    private ?\DateTimeInterface $dateReferenceAge = null;

    #[ORM\Column(name: 'age_min', type: 'smallint')]
    private ?int $ageMin = 18;

    #[ORM\Column(name: 'age_max', type: 'smallint')]
    private ?int $ageMax = 35;

    #[ORM\Column(type: 'string', length: 30)]
    private ?string $statut = self::STATUT_BROUILLON;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'boolean')]
    private bool $active = false;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    /**
     * @var Collection<int, Candidature>
     */
    #[ORM\OneToMany(mappedBy: 'session', targetEntity: Candidature::class)]
    private Collection $candidatures;

    public function __construct()
    {
        $this->candidatures = new ArrayCollection();
        $this->annee = (int) date('Y');
        $this->statut = self::STATUT_BROUILLON;
        $this->active = false;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getAnnee(): ?int
    {
        return $this->annee;
    }

    public function setAnnee(?int $annee): self
    {
        $this->annee = $annee;
        return $this;
    }

    public function getNumeroVague(): ?int
    {
        return $this->numeroVague;
    }

    public function setNumeroVague(?int $numeroVague): self
    {
        $this->numeroVague = $numeroVague;
        return $this;
    }

    public function getDateOuverture(): ?\DateTimeInterface
    {
        return $this->dateOuverture;
    }

    public function setDateOuverture(?\DateTimeInterface $dateOuverture): self
    {
        $this->dateOuverture = $dateOuverture;
        return $this;
    }

    public function getDateCloture(): ?\DateTimeInterface
    {
        return $this->dateCloture;
    }

    public function setDateCloture(?\DateTimeInterface $dateCloture): self
    {
        $this->dateCloture = $dateCloture;
        return $this;
    }

    public function getDateReferenceAge(): ?\DateTimeInterface
    {
        return $this->dateReferenceAge;
    }

    public function setDateReferenceAge(?\DateTimeInterface $dateReferenceAge): self
    {
        $this->dateReferenceAge = $dateReferenceAge;
        return $this;
    }

    public function getAgeMin(): ?int
    {
        return $this->ageMin;
    }

    public function setAgeMin(?int $ageMin): self
    {
        $this->ageMin = $ageMin;
        return $this;
    }

    public function getAgeMax(): ?int
    {
        return $this->ageMax;
    }

    public function setAgeMax(?int $ageMax): self
    {
        $this->ageMax = $ageMax;
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(?string $statut): self
    {
        $this->statut = $statut;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;
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

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    /**
     * @return Collection<int, Candidature>
     */
    public function getCandidatures(): Collection
    {
        return $this->candidatures;
    }

    public function addCandidature(Candidature $candidature): self
    {
        if (!$this->candidatures->contains($candidature)) {
            $this->candidatures->add($candidature);
            $candidature->setSession($this);
        }

        return $this;
    }

    public function removeCandidature(Candidature $candidature): self
    {
        if ($this->candidatures->removeElement($candidature)) {
            if ($candidature->getSession() === $this) {
                $candidature->setSession(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->nom ?? '';
    }
}
