<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\SessionRecrutement;

#[ORM\Entity()]
class Candidature
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: Etablissement::class, inversedBy: 'candidatures')]
    #[ORM\JoinColumn(nullable: true)]
    private $etablissement;

    #[ORM\ManyToOne(targetEntity: SessionRecrutement::class, inversedBy: 'candidatures')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?SessionRecrutement $session = null;

    #[ORM\ManyToOne(targetEntity: Metier::class, inversedBy: 'candidatures')]
    #[ORM\JoinColumn(nullable: true)]
    private $metier;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $numero;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $piece;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $numpiece;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $diplome;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $niveau;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $fphoto;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $fpiece;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $fextrait;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $fniveau;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $fautre;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $ficheEngagement;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $etustatut;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $etucom;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $jury;

    /** Créneau de l'entretien du candidat (1 = matin, 2 = après-midi). À ne pas confondre avec SessionRecrutement::$numeroVague. */
    #[ORM\Column(type: 'integer', nullable: true)]
    private $creneauEntretien;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $entdate;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $entlieu;

    #[ORM\Column(type: 'float', nullable: true)]
    private $note1;

    #[ORM\Column(type: 'float', nullable: true)]
    private $note2;

    #[ORM\Column(type: 'float', nullable: true)]
    private $note3;

    #[ORM\Column(type: 'float', nullable: true)]
    private $note4;

    #[ORM\Column(type: 'float', nullable: true)]
    private $note5;

    #[ORM\Column(type: 'float', nullable: true)]
    private $note6;

    #[ORM\Column(type: 'float', nullable: true)]
    private $note7;

    #[ORM\Column(type: 'float', nullable: true)]
    private $note8;

    #[ORM\Column(type: 'float', nullable: true)]
    private $note9;

    #[ORM\Column(type: 'float', nullable: true)]
    private $note10;

    #[ORM\Column(type: 'float', nullable: true)]
    private $note11;

    #[ORM\Column(type: 'float', nullable: true)]
    private $note12;

    #[ORM\Column(type: 'float', nullable: true)]
    private $note13;

    /** Réservé à un usage futur : aucune logique métier ne l'utilise. */
    #[ORM\Column(type: 'float', nullable: true)]
    private $noteEpreuveEcrite;

    /** Réservé à un usage futur : aucune logique métier ne l'utilise. */
    #[ORM\Column(type: 'float', nullable: true)]
    private $noteEpreuveOrale;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $entstatut;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $decisionJury;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $entcom;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $visdate;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $vislieu;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $visstatut;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $viscom;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $resultat;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $cohorte = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $creation;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $modification;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private $user;

    #[ORM\OneToOne(targetEntity: Admis::class, mappedBy: 'candidature')]
    private $admis;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private $selectionnePourVisite = null;

    public function __construct()
    {
        $this->creation = new \DateTime();
        $this->modification = new \DateTime();
    }

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

    public function getSession(): ?SessionRecrutement
    {
        return $this->session;
    }

    public function setSession(?SessionRecrutement $session): self
    {
        $this->session = $session;
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

    public function getNumero(): ?string
    {
        return $this->numero;
    }

    public function setNumero(?string $numero): self
    {
        $this->numero = $numero;
        return $this;
    }


    public function getPiece(): ?string
    {
        return $this->piece;
    }

    public function setPiece(?string $piece): self
    {
        $this->piece = $piece;
        return $this;
    }

    public function getNumpiece(): ?string
    {
        return $this->numpiece;
    }

    public function setNumpiece(?string $numpiece): self
    {
        $this->numpiece = $numpiece;
        return $this;
    }

    public function getDiplome(): ?string
    {
        return $this->diplome;
    }

    public function setDiplome(?string $diplome): self
    {
        $this->diplome = $diplome;
        return $this;
    }

    public function getNiveau(): ?string
    {
        return $this->niveau;
    }

    public function setNiveau(?string $niveau): self
    {
        $this->niveau = $niveau;
        return $this;
    }

    public function getFphoto(): ?string
    {
        return $this->fphoto;
    }

    public function setFphoto(?string $fphoto): self
    {
        $this->fphoto = $fphoto;
        return $this;
    }

    public function getFpiece(): ?string
    {
        return $this->fpiece;
    }

    public function setFpiece(?string $fpiece): self
    {
        $this->fpiece = $fpiece;
        return $this;
    }

    public function getFextrait(): ?string
    {
        return $this->fextrait;
    }

    public function setFextrait(?string $fextrait): self
    {
        $this->fextrait = $fextrait;
        return $this;
    }

    public function getNoteEpreuveEcrite(): ?float
    {
        return $this->noteEpreuveEcrite;
    }

    public function setNoteEpreuveEcrite(?float $noteEpreuveEcrite): self
    {
        $this->noteEpreuveEcrite = $noteEpreuveEcrite;
        return $this;
    }

    public function getNoteEpreuveOrale(): ?float
    {
        return $this->noteEpreuveOrale;
    }

    public function setNoteEpreuveOrale(?float $noteEpreuveOrale): self
    {
        $this->noteEpreuveOrale = $noteEpreuveOrale;
        return $this;
    }

    public function getFniveau(): ?string
    {
        return $this->fniveau;
    }

    public function setFniveau(?string $fniveau): self
    {
        $this->fniveau = $fniveau;
        return $this;
    }

    public function getFautre(): ?string
    {
        return $this->fautre;
    }

    public function setFautre(?string $fautre): self
    {
        $this->fautre = $fautre;
        return $this;
    }

    public function getEtustatut(): ?int
    {
        return $this->etustatut;
    }

    public function setEtustatut(?int $etustatut): self
    {
        $this->etustatut = $etustatut;
        return $this;
    }

    public function getEtucom(): ?string
    {
        return $this->etucom;
    }

    public function setEtucom(?string $etucom): self
    {
        $this->etucom = $etucom;
        return $this;
    }

    public function getJury(): ?int
    {
        return $this->jury;
    }

    public function setJury(?int $jury): self
    {
        $this->jury = $jury;
        return $this;
    }

    public function getCreneauEntretien(): ?int
    {
        return $this->creneauEntretien;
    }

    public function setCreneauEntretien(?int $creneauEntretien): self
    {
        $this->creneauEntretien = $creneauEntretien;
        return $this;
    }

    public function getEntdate(): ?\DateTimeInterface
    {
        return $this->entdate;
    }

    public function setEntdate(?\DateTimeInterface $entdate): self
    {
        $this->entdate = $entdate;
        return $this;
    }

    public function getEntlieu(): ?string
    {
        return $this->entlieu;
    }

    public function setEntlieu(?string $entlieu): self
    {
        $this->entlieu = $entlieu;
        return $this;
    }

    public function getNote1(): ?float
    {
        return $this->note1;
    }

    public function setNote1(?float $note1): self
    {
        $this->note1 = $note1;
        return $this;
    }

    public function getNote2(): ?float
    {
        return $this->note2;
    }

    public function setNote2(?float $note2): self
    {
        $this->note2 = $note2;
        return $this;
    }

    public function getNote3(): ?float
    {
        return $this->note3;
    }

    public function setNote3(?float $note3): self
    {
        $this->note3 = $note3;
        return $this;
    }

    public function getNote4(): ?float
    {
        return $this->note4;
    }

    public function setNote4(?float $note4): self
    {
        $this->note4 = $note4;
        return $this;
    }

    public function getNote5(): ?float
    {
        return $this->note5;
    }

    public function setNote5(?float $note5): self
    {
        $this->note5 = $note5;
        return $this;
    }

    public function getNote6(): ?float
    {
        return $this->note6;
    }

    public function setNote6(?float $note6): self
    {
        $this->note6 = $note6;
        return $this;
    }

    public function getNote7(): ?float
    {
        return $this->note7;
    }

    public function setNote7(?float $note7): self
    {
        $this->note7 = $note7;
        return $this;
    }

    public function getNote8(): ?float
    {
        return $this->note8;
    }

    public function setNote8(?float $note8): self
    {
        $this->note8 = $note8;
        return $this;
    }

    public function getNote9(): ?float
    {
        return $this->note9;
    }

    public function setNote9(?float $note9): self
    {
        $this->note9 = $note9;
        return $this;
    }

    public function getNote10(): ?float
    {
        return $this->note10;
    }

    public function setNote10(?float $note10): self
    {
        $this->note10 = $note10;
        return $this;
    }

    public function getNote11(): ?float
    {
        return $this->note11;
    }

    public function setNote11(?float $note11): self
    {
        $this->note11 = $note11;
        return $this;
    }

    public function getNote12(): ?float
    {
        return $this->note12;
    }

    public function setNote12(?float $note12): self
    {
        $this->note12 = $note12;
        return $this;
    }

    public function getNote13(): ?float
    {
        return $this->note13;
    }

    public function setNote13(?float $note13): self
    {
        $this->note13 = $note13;
        return $this;
    }

    public function isSelectionnePourVisite(): ?bool
    {
        return $this->selectionnePourVisite;
    }

    public function setSelectionnePourVisite(?bool $selectionnePourVisite): self
    {
        $this->selectionnePourVisite = $selectionnePourVisite;
        return $this;
    }

    public function getEntstatut(): ?int
    {
        return $this->entstatut;
    }

    public function setEntstatut(?int $entstatut): self
    {
        $this->entstatut = $entstatut;
        return $this;
    }

    public function getDecisionJury(): ?int
    {
        return $this->decisionJury;
    }

    public function setDecisionJury(?int $decisionJury): self
    {
        $this->decisionJury = $decisionJury;
        return $this;
    }

    public function getEntcom(): ?string
    {
        return $this->entcom;
    }

    public function setEntcom(?string $entcom): self
    {
        $this->entcom = $entcom;
        return $this;
    }

    public function getVisdate(): ?\DateTimeInterface
    {
        return $this->visdate;
    }

    public function setVisdate(?\DateTimeInterface $visdate): self
    {
        $this->visdate = $visdate;
        return $this;
    }

    public function getVislieu(): ?string
    {
        return $this->vislieu;
    }

    public function setVislieu(?string $vislieu): self
    {
        $this->vislieu = $vislieu;
        return $this;
    }

    public function getVisstatut(): ?int
    {
        return $this->visstatut;
    }

    public function setVisstatut(?int $visstatut): self
    {
        $this->visstatut = $visstatut;
        return $this;
    }

    public function getViscom(): ?string
    {
        return $this->viscom;
    }

    public function setViscom(?string $viscom): self
    {
        $this->viscom = $viscom;
        return $this;
    }

    public function getResultat(): ?int
    {
        return $this->resultat;
    }

    public function setResultat(?int $resultat): self
    {
        $this->resultat = $resultat;
        return $this;
    }

    public function getCohorte(): ?int
    {
        return $this->cohorte;
    }

    public function setCohorte(?int $cohorte): self
    {
        $this->cohorte = $cohorte;
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getFicheEngagement(): ?string
    {
        return $this->ficheEngagement;
    }

    public function setFicheEngagement(?string $ficheEngagement): self
    {
        $this->ficheEngagement = $ficheEngagement;
        return $this;
    }

    public function getAdmis(): ?Admis
    {
        return $this->admis;
    }

    public function setAdmis(?Admis $admis): self
    {
        $this->admis = $admis;
        return $this;
    }

    #[ORM\PreUpdate]
    public function updateModificationDate(): void
    {
        $this->modification = new \DateTime();
    }

}
