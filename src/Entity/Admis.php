<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity()]
class Admis
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\OneToOne(targetEntity: Candidature::class, inversedBy: 'admis')]
    #[ORM\JoinColumn(nullable: false)]
    private $candidature;

    #[ORM\ManyToOne(targetEntity: Etablissement::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $etablissement;

    #[ORM\ManyToOne(targetEntity: Metier::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $metier;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $user;

    #[ORM\Column(type: 'string', length: 255)]
    private $numeroInscription;

    #[ORM\Column(type: 'datetime')]
    private $dateInscription;

    #[ORM\Column(type: 'boolean')]
    private $confirme;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $modification;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $fichierContrat;

    #[ORM\Column(type: 'date', nullable: true)]
    private $dateContrat;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $fichierLivret;

    #[ORM\Column(type: 'date', nullable: true)]
    private $dateLivret;

    public function __construct()
    {
        $this->dateInscription = new \DateTime();
        $this->confirme = false;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCandidature(): ?Candidature
    {
        return $this->candidature;
    }

    public function setCandidature(?Candidature $candidature): self
    {
        $this->candidature = $candidature;
        return $this;
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getNumeroInscription(): ?string
    {
        return $this->numeroInscription;
    }

    public function setNumeroInscription(string $numeroInscription): self
    {
        $this->numeroInscription = $numeroInscription;
        return $this;
    }

    public function getDateInscription(): ?\DateTimeInterface
    {
        return $this->dateInscription;
    }

    public function setDateInscription(\DateTimeInterface $dateInscription): self
    {
        $this->dateInscription = $dateInscription;
        return $this;
    }

    public function getConfirme(): ?bool
    {
        return $this->confirme;
    }

    public function setConfirme(bool $confirme): self
    {
        $this->confirme = $confirme;
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

    public function getFichierContrat(): ?string
    {
        return $this->fichierContrat;
    }

    public function setFichierContrat(?string $fichierContrat): self
    {
        $this->fichierContrat = $fichierContrat;
        return $this;
    }

    public function getDateContrat(): ?\DateTimeInterface
    {
        return $this->dateContrat;
    }

    public function setDateContrat(?\DateTimeInterface $dateContrat): self
    {
        $this->dateContrat = $dateContrat;
        return $this;
    }

    public function getFichierLivret(): ?string
    {
        return $this->fichierLivret;
    }

    public function setFichierLivret(?string $fichierLivret): self
    {
        $this->fichierLivret = $fichierLivret;
        return $this;
    }

    public function getDateLivret(): ?\DateTimeInterface
    {
        return $this->dateLivret;
    }

    public function setDateLivret(?\DateTimeInterface $dateLivret): self
    {
        $this->dateLivret = $dateLivret;
        return $this;
    }
}
