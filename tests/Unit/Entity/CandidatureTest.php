<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Candidature;
use App\Entity\Etablissement;
use App\Entity\Metier;
use App\Entity\SessionRecrutement;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class CandidatureTest extends TestCase
{
    private Candidature $candidature;

    protected function setUp(): void
    {
        $this->candidature = new Candidature();
    }

    public function testInitialState(): void
    {
        $this->assertNull($this->candidature->getId());
        $this->assertNull($this->candidature->getEtustatut(), 'Initial etustatut must be null (En attente)');
        $this->assertNull($this->candidature->getVisstatut(), 'Initial visstatut must be null');
        $this->assertNull($this->candidature->getResultat(), 'Initial resultat must be null');
        $this->assertNull($this->candidature->getDecisionJury(), 'Initial decisionJury must be null');
        $this->assertInstanceOf(\DateTime::class, $this->candidature->getCreation());
        $this->assertInstanceOf(\DateTime::class, $this->candidature->getModification());
    }

    public function testNumeroAndRelations(): void
    {
        $session = new SessionRecrutement();
        $etab = new Etablissement();
        $metier = new Metier();
        $user = new User();

        $this->candidature
            ->setNumero('PA26010001')
            ->setSession($session)
            ->setEtablissement($etab)
            ->setMetier($metier)
            ->setUser($user)
            ->setPiece('CNI')
            ->setNumpiece('C0123456789')
            ->setDiplome('BT')
            ->setNiveau('CAP - BT - BTS');

        $this->assertSame('PA26010001', $this->candidature->getNumero());
        $this->assertSame($session, $this->candidature->getSession());
        $this->assertSame($etab, $this->candidature->getEtablissement());
        $this->assertSame($metier, $this->candidature->getMetier());
        $this->assertSame($user, $this->candidature->getUser());
        $this->assertSame('CNI', $this->candidature->getPiece());
        $this->assertSame('C0123456789', $this->candidature->getNumpiece());
    }

    public function testStatusTransitions(): void
    {
        // 1. Dossier validation: 2 = Accepté, 1 = Rejeté
        $this->candidature->setEtustatut(2);
        $this->candidature->setEtucom('Dossier complet et conforme');
        $this->assertSame(2, $this->candidature->getEtustatut());
        $this->assertSame('Dossier complet et conforme', $this->candidature->getEtucom());

        // 2. Interview & Jury decision
        $this->candidature->setEntstatut(2);
        $this->candidature->setDecisionJury(2);
        $this->candidature->setJury(1);
        $this->assertSame(2, $this->candidature->getEntstatut());
        $this->assertSame(2, $this->candidature->getDecisionJury());

        // 3. Medical visit
        $this->candidature->setVisstatut(2);
        $this->candidature->setVislieu('Hôpital Militaire d\'Abidjan');
        $this->assertSame(2, $this->candidature->getVisstatut());
        $this->assertSame('Hôpital Militaire d\'Abidjan', $this->candidature->getVislieu());

        // 4. Final admission
        $this->candidature->setResultat(2);
        $this->assertSame(2, $this->candidature->getResultat(), 'Resultat = 2 corresponds to Admis');
    }

    public function testJuryNotesAggregation(): void
    {
        // 13 evaluation notes
        $notes = [
            1 => 3.5, // Lecture (max 4)
            2 => 2.5, // Écriture (max 3)
            3 => 4.0, // Calcul (max 5)
            4 => 5.0, // Freins sociaux (max 6)
            5 => 4.5, // Motivation (max 5)
            6 => 2.0, // Sens organisation (max 2)
            7 => 1.5, // Rapport hiérarchie (max 2)
            8 => 2.5, // Relations humaines (max 3)
            9 => 1.5, // Gestion conflits (max 2)
            10 => 2.0, // Connaissance métier (max 2)
            11 => 1.5, // Analyse synthèse (max 2)
            12 => 2.0, // Première impression (max 2)
            13 => 2.0, // Élocution fluide (max 2)
        ];

        $totalExpected = 0.0;
        foreach ($notes as $index => $val) {
            $setter = 'setNote' . $index;
            $this->candidature->$setter($val);
            $totalExpected += $val;
        }

        $totalCalculated = 0.0;
        for ($i = 1; $i <= 13; $i++) {
            $getter = 'getNote' . $i;
            $totalCalculated += $this->candidature->$getter();
        }

        $this->assertEqualsWithDelta($totalExpected, $totalCalculated, 0.001);
        $this->assertGreaterThanOrEqual(20.0, $totalCalculated, 'Total note should be >= 20/40 for admissibility');

        // Verify admissibility condition: (total / 40) * 4 >= 2
        $averageOn4 = ($totalCalculated / 40.0) * 4.0;
        $this->assertGreaterThanOrEqual(2.0, $averageOn4);
    }
}
