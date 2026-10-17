<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity()]
class EtablissementMetier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: Etablissement::class, inversedBy: 'etablissementMetiers')]
    #[ORM\JoinColumn(nullable: true)]
    private $etablissement;

    #[ORM\ManyToOne(targetEntity: Metier::class, inversedBy: 'etablissementMetiers')]
    #[ORM\JoinColumn(nullable: true)]
    private $metier;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $nbrplace;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private $niveauRequis;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private $dureeFormation;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private $estActif = true;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEtablissement(): ?Etablissement
    {
        return $this->etablissement;
    }

    public function setEtablissement(?Etablissement $etablissement): self
    {
        $this->etablissement = $etablissement;
        return $this;
    }

    public function getMetier(): ?Metier
    {
        return $this->metier;
    }

    public function setMetier(?Metier $metier): self
    {
        $this->metier = $metier;
        return $this;
    }

    public function getNbrplace(): ?int
    {
        return $this->nbrplace;
    }

    public function setNbrplace(?int $nbrplace): self
    {
        $this->nbrplace = $nbrplace;
        return $this;
    }

    public function getNiveauRequis(): ?string
    {
        return $this->niveauRequis;
    }

    public function setNiveauRequis(?string $niveauRequis): self
    {
        $this->niveauRequis = $niveauRequis;
        return $this;
    }

    public function getDureeFormation(): ?string
    {
        return $this->dureeFormation;
    }

    public function setDureeFormation(?string $dureeFormation): self
    {
        $this->dureeFormation = $dureeFormation;
        return $this;
    }

    public function getEstActif(): ?bool
    {
        return $this->estActif;
    }

    public function setEstActif(bool $estActif): self
    {
        $this->estActif = $estActif;
        return $this;
    }

}
