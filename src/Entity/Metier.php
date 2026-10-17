<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity()]
class Metier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;


    #[ORM\Column(type: 'string', length: 255)]
    private $nom;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private $user;

    #[ORM\Column(type: 'date', nullable: true)]
    private $creation;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $modification;

    #[ORM\OneToMany(mappedBy: 'metier', targetEntity: EtablissementMetier::class)]
    private $etablissementMetiers;

    #[ORM\OneToMany(mappedBy: 'metier', targetEntity: Candidature::class)]
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
            $etablissementMetier->setMetier($this);
        }
        return $this;
    }

    public function removeEtablissementMetier(EtablissementMetier $etablissementMetier): self
    {
        if ($this->etablissementMetiers->removeElement($etablissementMetier)) {
            if ($etablissementMetier->getMetier() === $this) {
                $etablissementMetier->setMetier(null);
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
            $candidature->setMetier($this);
        }
        return $this;
    }

    public function removeCandidature(Candidature $candidature): self
    {
        if ($this->candidatures->removeElement($candidature)) {
            if ($candidature->getMetier() === $this) {
                $candidature->setMetier(null);
            }
        }
        return $this;
    }

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $image = null;

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;
        return $this;
    }

}
