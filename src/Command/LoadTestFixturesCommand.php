<?php

namespace App\Command;

use App\Entity\Candidature;
use App\Entity\Etablissement;
use App\Entity\EtablissementMetier;
use App\Entity\Localite;
use App\Entity\Metier;
use App\Entity\SessionRecrutement;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:load-test-fixtures',
    description: 'Charge les fixtures isolées de test : 20 candidats, 3 utilisateurs ENT et 1 administrateur'
)]
class LoadTestFixturesCommand extends Command
{
    public const ADMIN_EMAIL = 'admin@pacte.ci';
    public const ADMIN_PASSWORD = 'Admin123!';

    public const ENT_EMAILS = [
        1 => 'ent.energies@pacte.ci',
        2 => 'ent.jacqueville@pacte.ci',
        3 => 'ent.korhogo@pacte.ci',
    ];
    public const ENT_PASSWORD = 'Ent123!';

    public const CANDIDAT_PASSWORD = 'Candidat123!';

    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $conn = $this->em->getConnection();

        // 1. Purge propre des tables
        $io->section('Purge des données existantes...');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        $tables = ['admis', 'candidature', 'etablissement_metier', 'etablissement', 'metier', 'session_recrutement', 'localite', 'user'];
        foreach ($tables as $table) {
            $conn->executeStatement("TRUNCATE TABLE `{$table}`");
        }
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

        // 2. Localités
        $io->section('Création des localités...');
        $localites = [];
        foreach (['Jacqueville', 'Korhogo', 'Yopougon'] as $nom) {
            $loc = (new Localite())->setNom($nom)->setType('ville');
            $this->em->persist($loc);
            $localites[$nom] = $loc;
        }
        $this->em->flush();

        // 3. Établissements
        $io->section('Création des établissements...');
        $etablissements = [];
        $etabDefs = [
            1 => ['nom' => 'Lycée Professionnel des Métiers de l\'Énergie Renouvelable', 'loc' => 'Yopougon', 'places' => 90],
            2 => ['nom' => 'Lycée Professionnel de Jacqueville', 'loc' => 'Jacqueville', 'places' => 80],
            3 => ['nom' => 'Lycée Professionnel de Korhogo', 'loc' => 'Korhogo', 'places' => 80],
        ];

        foreach ($etabDefs as $id => $def) {
            $etab = (new Etablissement())
                ->setNom($def['nom'])
                ->setLocalite($localites[$def['loc']])
                ->setCreation(new \DateTime('2026-01-01'));
            $this->em->persist($etab);
            $etablissements[$id] = $etab;
        }
        $this->em->flush();

        // 4. Métiers
        $io->section('Création des métiers...');
        $metiers = [];
        $metierNoms = [
            'PV' => 'Installateur de systèmes solaires photovoltaïques',
            'EE' => 'Technicien en efficacité énergétique',
        ];
        foreach ($metierNoms as $code => $nom) {
            $m = (new Metier())->setNom($nom);
            $this->em->persist($m);
            $metiers[$code] = $m;
        }
        $this->em->flush();

        // 5. Relations Etablissement <-> Metier
        foreach ($etablissements as $id => $etab) {
            foreach ($metiers as $m) {
                $em = (new EtablissementMetier())
                    ->setEtablissement($etab)
                    ->setMetier($m)
                    ->setNbrplace($etabDefs[$id]['places'])
                    ->setNiveauRequis('CAP - BT - BTS')
                    ->setDureeFormation('2 ans')
                    ->setEstActif(true);
                $this->em->persist($em);
            }
        }

        // 6. Session de recrutement
        $session = (new SessionRecrutement())
            ->setNom('PACTE 2026 — Vague 1')
            ->setAnnee(2026)
            ->setNumeroVague(1)
            ->setDateOuverture(new \DateTime('2026-01-01 00:00:00'))
            ->setDateCloture(new \DateTime('2026-12-31 23:59:59'))
            ->setDateReferenceAge(new \DateTime('2026-12-31'))
            ->setAgeMin(18)
            ->setAgeMax(35)
            ->setStatut(SessionRecrutement::STATUT_OUVERTE)
            ->setActive(true);
        $this->em->persist($session);
        $this->em->flush();

        // 7. Administrateur (1)
        $io->section('Création de l\'administrateur...');
        $admin = (new User())
            ->setEmail(self::ADMIN_EMAIL)
            ->setNom('ADMIN')
            ->setPrenoms('Superviseur Général')
            ->setContact('0102030405')
            ->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        $admin->setPassword($this->hasher->hashPassword($admin, self::ADMIN_PASSWORD));
        $this->em->persist($admin);

        // 8. Utilisateurs ENT (3 rattachés à leurs établissements)
        $io->section('Création des 3 utilisateurs ENT...');
        $entUsers = [];
        $entNames = [
            1 => ['nom' => 'KOUAME', 'prenoms' => 'Responsable Énergies'],
            2 => ['nom' => 'DIOMANDE', 'prenoms' => 'Directeur Jacqueville'],
            3 => ['nom' => 'SORO', 'prenoms' => 'Coordonnateur Korhogo'],
        ];

        foreach (self::ENT_EMAILS as $etabId => $email) {
            $ent = (new User())
                ->setEmail($email)
                ->setNom($entNames[$etabId]['nom'])
                ->setPrenoms($entNames[$etabId]['prenoms'])
                ->setContact('07080910' . str_pad((string)$etabId, 2, '0', STR_PAD_LEFT))
                ->setRoles(['ROLE_ENT', 'ROLE_USER']);
            $ent->setPassword($this->hasher->hashPassword($ent, self::ENT_PASSWORD));
            $this->em->persist($ent);
            $this->em->flush(); // Pour obtenir l'ID

            // Liaison de l'utilisateur ENT à son établissement
            $etablissements[$etabId]->setUser($ent);
            $this->em->persist($etablissements[$etabId]);
            $entUsers[$etabId] = $ent;
        }
        $this->em->flush();

        // 9. Candidats (20) avec candidatures complètes
        $io->section('Création des 20 candidats et de leurs candidatures...');
        $patronymes = [
            1 => ['nom' => 'KOUASSI', 'prenoms' => 'Jean-Luc', 'sexe' => 'M', 'annee' => 2002],
            2 => ['nom' => 'KOFFI', 'prenoms' => 'Aya Marie', 'sexe' => 'F', 'annee' => 2001],
            3 => ['nom' => 'YAO', 'prenoms' => 'Kouadio Paul', 'sexe' => 'M', 'annee' => 2000],
            4 => ['nom' => 'KONE', 'prenoms' => 'Fatoumata', 'sexe' => 'F', 'annee' => 2003],
            5 => ['nom' => 'OUATTARA', 'prenoms' => 'Ibrahim', 'sexe' => 'M', 'annee' => 2002],
            6 => ['nom' => 'TRAORE', 'prenoms' => 'Aminata', 'sexe' => 'F', 'annee' => 2004],
            7 => ['nom' => 'DIABATE', 'prenoms' => 'Sekou', 'sexe' => 'M', 'annee' => 2001],
            8 => ['nom' => 'BAMBA', 'prenoms' => 'Mariam', 'sexe' => 'F', 'annee' => 2002],
            9 => ['nom' => 'TOURE', 'prenoms' => 'Bakary', 'sexe' => 'M', 'annee' => 2000],
            10 => ['nom' => 'CISSE', 'prenoms' => 'Awa', 'sexe' => 'F', 'annee' => 2003],
            11 => ['nom' => 'N\'GUESSAN', 'prenoms' => 'Alain', 'sexe' => 'M', 'annee' => 2001],
            12 => ['nom' => 'BROU', 'prenoms' => 'Estelle', 'sexe' => 'F', 'annee' => 2002],
            13 => ['nom' => 'SANGARE', 'prenoms' => 'Mamadou', 'sexe' => 'M', 'annee' => 2003],
            14 => ['nom' => 'FOFANA', 'prenoms' => 'Salimata', 'sexe' => 'F', 'annee' => 2001],
            15 => ['nom' => 'KOUAME', 'prenoms' => 'Franck', 'sexe' => 'M', 'annee' => 2000],
            16 => ['nom' => 'SYLLA', 'prenoms' => 'Adama', 'sexe' => 'M', 'annee' => 2002],
            17 => ['nom' => 'CAMARA', 'prenoms' => 'Ousmane', 'sexe' => 'M', 'annee' => 2001],
            18 => ['nom' => 'DOSSO', 'prenoms' => 'Mawa', 'sexe' => 'F', 'annee' => 2003],
            19 => ['nom' => 'BAKAYOKO', 'prenoms' => 'Lamine', 'sexe' => 'M', 'annee' => 2002],
            20 => ['nom' => 'DIARRASSOUBA', 'prenoms' => 'Rokiatou', 'sexe' => 'F', 'annee' => 2000],
        ];

        for ($i = 1; $i <= 20; $i++) {
            $p = $patronymes[$i];
            $email = sprintf('candidat%d@pacte.ci', $i);
            $candUser = (new User())
                ->setEmail($email)
                ->setNom($p['nom'])
                ->setPrenoms($p['prenoms'])
                ->setSexe($p['sexe'])
                ->setDatenaissance(new \DateTime(sprintf('%d-05-15', $p['annee'])))
                ->setLieunaissance('Abidjan')
                ->setContact(sprintf('050607%04d', $i))
                ->setNationalite('CI')
                ->setRoles(['ROLE_CANDIDAT', 'ROLE_USER']);
            $candUser->setPassword($this->hasher->hashPassword($candUser, self::CANDIDAT_PASSWORD));
            $this->em->persist($candUser);

            // Répartition équilibrée entre les 3 établissements
            $etabId = (($i - 1) % 3) + 1;
            $metierCode = ($i % 2 === 0) ? 'EE' : 'PV';

            $candidature = (new Candidature())
                ->setUser($candUser)
                ->setSession($session)
                ->setEtablissement($etablissements[$etabId])
                ->setMetier($metiers[$metierCode])
                ->setNumero(sprintf('PA2601%04d', $i))
                ->setDiplome('BAC')
                ->setNiveau('BAC')
                ->setPiece('CNI')
                ->setNumpiece(sprintf('CI%08d', 10000000 + $i))
                ->setCreation(new \DateTime(sprintf('2026-01-%02d 10:00:00', ($i % 25) + 1)));

            // Simulation réaliste de progression selon l'index
            if ($i === 1) {
                // Candidat 1 : État frais (pour le test du parcours complet d'admission de bout en bout)
                $candidature->setEtustatut(null);
                $candidature->setEntstatut(null);
                $candidature->setVisstatut(null);
                $candidature->setResultat(null);
            } elseif ($i >= 2 && $i <= 5) {
                // Candidats 2-5 : Dossier validé, en attente d'entretien
                $candidature->setEtustatut(2);
                $candidature->setEtucom('Dossier complet et pièces conformes');
            } elseif ($i >= 6 && $i <= 8) {
                // Candidats 6-8 : Dossier rejeté
                $candidature->setEtustatut(1);
                $candidature->setEtucom('Pièces justificatives incomplètes');
            } elseif ($i >= 9 && $i <= 12) {
                // Candidats 9-12 : Entretien passé avec succès (admissible)
                $candidature->setEtustatut(2);
                $candidature->setEtucom('Dossier validé');
                $candidature->setJury(1);
                $candidature->setCreneauEntretien(1);
                $candidature->setEntdate(new \DateTime('2026-02-10 09:00:00'));
                $candidature->setEntlieu('Salle 102');
                $candidature->setEntstatut(1); // présent
                $candidature->setDecisionJury(2); // Admissible
                $candidature->setEntcom('Très bon profil technique');
            } elseif ($i >= 13 && $i <= 14) {
                // Candidats 13-14 : Entretien non retenu
                $candidature->setEtustatut(2);
                $candidature->setEntstatut(1);
                $candidature->setDecisionJury(1); // Non retenu
                $candidature->setEntcom('Compétences insuffisantes');
            } elseif ($i >= 15 && $i <= 18) {
                // Candidats 15-18 : Visite médicale effectuée (apte)
                $candidature->setEtustatut(2);
                $candidature->setDecisionJury(2);
                $candidature->setVisdate(new \DateTime('2026-03-05 08:30:00'));
                $candidature->setVislieu('Centre Médical Régional');
                $candidature->setVisstatut(2); // Visite effectuée / apte
                $candidature->setViscom('Apte sans réserve');
            } elseif ($i >= 19 && $i <= 20) {
                // Candidats 19-20 : Admis définitivement
                $candidature->setEtustatut(2);
                $candidature->setDecisionJury(2);
                $candidature->setVisstatut(2);
                $candidature->setViscom('Apte');
                $candidature->setResultat(2); // Admis
            }

            $this->em->persist($candidature);
        }

        $this->em->flush();

        $io->success('Fixtures de test chargées avec succès :');
        $io->listing([
            '1 Administrateur : ' . self::ADMIN_EMAIL . ' (Mdp: ' . self::ADMIN_PASSWORD . ')',
            '3 Utilisateurs ENT rattachés aux établissements 1, 2, 3 (Mdp: ' . self::ENT_PASSWORD . ')',
            '20 Candidats enregistrés avec candidatures (candidat1@pacte.ci à candidat20@pacte.ci, Mdp: ' . self::CANDIDAT_PASSWORD . ')',
            'Candidat 1 préparé à l\'état neuf pour les tests de bout en bout (Numéro PA26010001)',
        ]);

        return Command::SUCCESS;
    }
}
