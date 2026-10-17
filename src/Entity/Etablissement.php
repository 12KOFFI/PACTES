<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity()]
class Etablissement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    private $nom;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $definition;

    #[ORM\ManyToOne(targetEntity: Localite::class, inversedBy: 'etablissements')]
    #[ORM\JoinColumn(nullable: true)]
    private $localite;


    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private $user;

    #[ORM\Column(type: 'date', nullable: true)]
    private $creation;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $modification;

    #[ORM\OneToMany(mappedBy: 'etablissement', targetEntity: EtablissementMetier::class)]
    private $etablissementMetiers;

    #[ORM\OneToMany(mappedBy: 'etablissement', targetEntity: Candidature::class)]
    private $candidatures;

    public function __construct()
    {
        $this->etablissementMetiers = new ArrayCollection();
        $this->candidatures = new ArrayCollection();
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

    public function getDefinition(): ?string
    {
        return $this->definition;
    }

    public function setDefinition(?string $definition): self
    {
        $this->definition = $definition;
        return $this;
    }

    public function getLocalite(): ?Localite
    {
        return $this->localite;
    }

    public function setLocalite(?Localite $localite): self
    {
        $this->localite = $localite;
        return $this;
    }


    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getCreation(): ?\DateTimeInterface
    {
        return $this->creation;
    }

    public function setCreation(?\DateTimeInterface $creation): self
    {
        $this->creation = $creation;
        return $this;
    }

    public function getModification(): ?\DateTimeInterface
    {
        return $this->modification;
    }

    public function setModification(?\DateTimeInterface $modification): self
    {
        $this->modification = $modification;
        return $this;
    }

    /**
     * @return Collection<int, EtablissementMetier>
     */
    public function getEtablissementMetiers(): Collection
    {
        return $this->etablissementMetiers;
    }

    public function addEtablissementMetier(EtablissementMetier $etablissementMetier): self
    {
        if (!$this->etablissementMetiers->contains($etablissementMetier)) {
            $this->etablissementMetiers[] = $etablissementMetier;
            $etablissementMetier->setEtablissement($this);
        }
        return $this;
    }

    public function removeEtablissementMetier(EtablissementMetier $etablissementMetier): self
    {
        if ($this->etablissementMetiers->removeElement($etablissementMetier)) {
            if ($etablissementMetier->getEtablissement() === $this) {
                $etablissementMetier->setEtablissement(null);
            }
        }
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
            $this->candidatures[] = $candidature;
            $candidature->setEtablissement($this);
        }
        return $this;
    }

    public function removeCandidature(Candidature $candidature): self
    {
        if ($this->candidatures->removeElement($candidature)) {
            if ($candidature->getEtablissement() === $this) {
                $candidature->setEtablissement(null);
            }
        }
        return $this;
    }

    public function __toString(): string
    {
        return $this->definition + ' (' + $this->nom + ')' ?? '';
    }
}
