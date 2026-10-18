<?php

namespace App\Command;

use App\Entity\Candidature;
use App\Entity\Etablissement;
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
    name: 'app:add-detailed-test-candidatures',
    description: 'Ajoute 60 candidatures réalistes et complètes (30 hommes et 30 femmes) avec toutes les colonnes remplies'
)]
class AddDetailedTestCandidaturesCommand extends Command
{
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
        $io->title('Insertion de 60 candidatures détaillées (30 Hommes & 30 Femmes)');

        $session = $this->em->getRepository(SessionRecrutement::class)->findOneBy(['active' => true]);
        if (!$session) {
            $session = $this->em->getRepository(SessionRecrutement::class)->find(1);
        }
        if (!$session) {
            $io->error('Aucune session de recrutement trouvée.');
            return Command::FAILURE;
        }

        $etablissements = $this->em->getRepository(Etablissement::class)->findAll();
        $metiers = $this->em->getRepository(Metier::class)->findAll();

        if (empty($etablissements) || empty($metiers)) {
            $io->error('Veuillez vous assurer que les établissements et métiers existent.');
            return Command::FAILURE;
        }

        // Trouver le dernier numéro de candidature pour incrémenter
        $lastCand = $this->em->createQueryBuilder()
            ->select('c.numero')
            ->from(Candidature::class, 'c')
            ->orderBy('c.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $startIdx = 21;
        if ($lastCand && preg_match('/PA2601(\d{4})/', $lastCand['numero'], $matches)) {
            $startIdx = max($startIdx, ((int)$matches[1]) + 1);
        }

        $villes = ['Abidjan', 'Bouaké', 'Yamoussoukro', 'Daloa', 'Korhogo', 'San-Pédro', 'Man', 'Gagnoa', 'Divo', 'Abengourou'];
        $communes = ['Cocody', 'Yopougon', 'Plateau', 'Koumassi', 'Marcory', 'Port-Bouët', 'Adjamé', 'Treichville', 'Attécoubé', 'Abobo'];
        $pieces = ['CNI', 'ATTESTATION D\'IDENTITE', 'PASSEPORT'];
        $diplomes = ['BAC', 'BTS', 'BT', 'CAP'];
        $niveaux = ['Terminale', 'BAC', 'BAC+1', 'BAC+2', 'BAC+3'];
        $situations = ['CELIBATAIRE', 'MARIE(E)', 'UNION LIBRE'];

        // 30 Hommes avec patronymes réalistes
        $hommes = [
            ['nom' => 'YAO', 'prenoms' => 'Jean-Baptiste Kouassi', 'annee' => 1999, 'pere' => 'YAO Kouadio', 'mere' => 'KOUAME Akissi'],
            ['nom' => 'KOUASSI', 'prenoms' => 'Marc-Antoine N\'Dri', 'annee' => 2001, 'pere' => 'KOUASSI N\'Goran', 'mere' => 'KONAN Ahou'],
            ['nom' => 'KONE', 'prenoms' => 'Bakary Sekou', 'annee' => 2000, 'pere' => 'KONE Mamadou', 'mere' => 'TRAORE Fanta'],
            ['nom' => 'OUATTARA', 'prenoms' => 'Souleymane Ibrahim', 'annee' => 2002, 'pere' => 'OUATTARA Yacouba', 'mere' => 'COULIBALY Salimata'],
            ['nom' => 'TRAORE', 'prenoms' => 'Cheick Oumar', 'annee' => 1998, 'pere' => 'TRAORE Lassina', 'mere' => 'DIABATE Mariam'],
            ['nom' => 'BAMBA', 'prenoms' => 'Vassiriki Amadou', 'annee' => 2001, 'pere' => 'BAMBA Siaka', 'mere' => 'CISSE Minata'],
            ['nom' => 'DIABATE', 'prenoms' => 'Mamadou Lamine', 'annee' => 2003, 'pere' => 'DIABATE Moussa', 'mere' => 'BAMBA Fatoumata'],
            ['nom' => 'COULIBALY', 'prenoms' => 'Drissa Kalilou', 'annee' => 2000, 'pere' => 'COULIBALY Tiémoko', 'mere' => 'SANGARE Kadiatou'],
            ['nom' => 'CISSE', 'prenoms' => 'Abdoulaye Karim', 'annee' => 2002, 'pere' => 'CISSE Daouda', 'mere' => 'TOURE Rokiatou'],
            ['nom' => 'N\'GUESSAN', 'prenoms' => 'Koffi Serge Pacôme', 'annee' => 1999, 'pere' => 'N\'GUESSAN Yao', 'mere' => 'BROU Amoin'],
            ['nom' => 'KOUAME', 'prenoms' => 'Yao Franck Olivier', 'annee' => 2001, 'pere' => 'KOUAME Kouassi', 'mere' => 'KRA Adjoua'],
            ['nom' => 'KOFFI', 'prenoms' => 'Kouadio Thierry Fabrice', 'annee' => 2000, 'pere' => 'KOFFI Konan', 'mere' => 'YAO Affoué'],
            ['nom' => 'GOHORE', 'prenoms' => 'Bi Vanié Stéphane', 'annee' => 2002, 'pere' => 'GOHORE Bi Tra', 'mere' => 'LOU Kouamé'],
            ['nom' => 'TIECOURA', 'prenoms' => 'Patrick Arnaud', 'annee' => 1999, 'pere' => 'TIECOURA Kouassi', 'mere' => 'ASSI Bernadette'],
            ['nom' => 'ASSALE', 'prenoms' => 'Yves-Roland Tanoh', 'annee' => 2001, 'pere' => 'ASSALE Jean-Luc', 'mere' => 'ADOU Chantal'],
            ['nom' => 'MEITE', 'prenoms' => 'Losseni Sindou', 'annee' => 2000, 'pere' => 'MEITE Boiké', 'mere' => 'FOFANA Mawa'],
            ['nom' => 'DIARRASSOUBA', 'prenoms' => 'Brahima Zoumana', 'annee' => 2003, 'pere' => 'DIARRASSOUBA Salif', 'mere' => 'SYLLA Awa'],
            ['nom' => 'SANGARE', 'prenoms' => 'Yacouba Adama', 'annee' => 2001, 'pere' => 'SANGARE Seydou', 'mere' => 'KONE Sali'],
            ['nom' => 'TOURE', 'prenoms' => 'Lacina Gaoussou', 'annee' => 1998, 'pere' => 'TOURE Bakary', 'mere' => 'DIOMANDE Maténin'],
            ['nom' => 'SYLLA', 'prenoms' => 'Moussa Fodé', 'annee' => 2002, 'pere' => 'SYLLA Boubakar', 'mere' => 'BAMBA Massandjé'],
            ['nom' => 'FOFANA', 'prenoms' => 'Seydou Ousmane', 'annee' => 2000, 'pere' => 'FOFANA Kassoum', 'mere' => 'TRAORE Nabintou'],
            ['nom' => 'DIOMANDE', 'prenoms' => 'Vamé Charles', 'annee' => 2001, 'pere' => 'DIOMANDE Tia', 'mere' => 'DOHO Lou Solange'],
            ['nom' => 'BROU', 'prenoms' => 'Konan Eric Landry', 'annee' => 2002, 'pere' => 'BROU N\'Goran', 'mere' => 'KOUASSI Akissi'],
            ['nom' => 'KRA', 'prenoms' => 'Kouamé Daniel Junior', 'annee' => 1999, 'pere' => 'KRA Kobenan', 'mere' => 'YAO Amenan'],
            ['nom' => 'DIBY', 'prenoms' => 'N\'Goran Fabrice Guy', 'annee' => 2001, 'pere' => 'DIBY Kouakou', 'mere' => 'KONAN Ahou'],
            ['nom' => 'AKE', 'prenoms' => 'Ake Jean-Jaurès', 'annee' => 2000, 'pere' => 'AKE Mel Gabriel', 'mere' => 'GNAGNE Marie'],
            ['nom' => 'GNAGNE', 'prenoms' => 'Mel Ange Rodrigue', 'annee' => 2003, 'pere' => 'GNAGNE Essis', 'mere' => 'LOBOGNON Reine'],
            ['nom' => 'SEKA', 'prenoms' => 'Chiadon Wilfried Boris', 'annee' => 2001, 'pere' => 'SEKA Yapo', 'mere' => 'APO Chantal'],
            ['nom' => 'DOHO', 'prenoms' => 'Sylvain Ghislain', 'annee' => 1998, 'pere' => 'DOHO Gaston', 'mere' => 'BAHI Viviane'],
            ['nom' => 'YAPI', 'prenoms' => 'Yapi Christian Narcisse', 'annee' => 2002, 'pere' => 'YAPI N\'Cho', 'mere' => 'CHIADON Estelle'],
        ];

        // 30 Femmes avec patronymes réalistes
        $femmes = [
            ['nom' => 'KOUASSI', 'prenoms' => 'Aya Chantal Marie', 'annee' => 2001, 'pere' => 'KOUASSI Konan', 'mere' => 'YAO Akissi', 'jeunefille' => null],
            ['nom' => 'KOFFI', 'prenoms' => 'Akissi Marie-Josée', 'annee' => 2002, 'pere' => 'KOFFI Kouamé', 'mere' => 'BROU Amoin', 'jeunefille' => null],
            ['nom' => 'YAO', 'prenoms' => 'Affoué Estelle Nadège', 'annee' => 2000, 'pere' => 'YAO Kouadio', 'mere' => 'KONAN Ahou', 'jeunefille' => null],
            ['nom' => 'KONE', 'prenoms' => 'Mariam Fatou', 'annee' => 2003, 'pere' => 'KONE Daouda', 'mere' => 'TRAORE Fanta', 'jeunefille' => null],
            ['nom' => 'OUATTARA', 'prenoms' => 'Fatoumata Bintou', 'annee' => 1999, 'pere' => 'OUATTARA Lassina', 'mere' => 'COULIBALY Minata', 'jeunefille' => 'COULIBALY'],
            ['nom' => 'TRAORE', 'prenoms' => 'Aminata Sitan', 'annee' => 2001, 'pere' => 'TRAORE Oumar', 'mere' => 'DIABATE Salimata', 'jeunefille' => null],
            ['nom' => 'BAMBA', 'prenoms' => 'Salimata Korotoum', 'annee' => 2002, 'pere' => 'BAMBA Vassiriki', 'mere' => 'CISSE Rokiatou', 'jeunefille' => null],
            ['nom' => 'COULIBALY', 'prenoms' => 'Rokiatou Assétou', 'annee' => 2000, 'pere' => 'COULIBALY Sekou', 'mere' => 'SANGARE Kadiatou', 'jeunefille' => null],
            ['nom' => 'CISSE', 'prenoms' => 'Nabintou Alima', 'annee' => 2002, 'pere' => 'CISSE Mamadou', 'mere' => 'TOURE Fatoumata', 'jeunefille' => null],
            ['nom' => 'N\'GUESSAN', 'prenoms' => 'Amoin Nadège Prisca', 'annee' => 2001, 'pere' => 'N\'GUESSAN Koffi', 'mere' => 'KRA Adjoua', 'jeunefille' => null],
            ['nom' => 'KOUAME', 'prenoms' => 'Ahou Brigitte Fleur', 'annee' => 2003, 'pere' => 'KOUAME Kouadio', 'mere' => 'YAO Affoué', 'jeunefille' => null],
            ['nom' => 'KOFFI', 'prenoms' => 'Amenan Viviane Carine', 'annee' => 1999, 'pere' => 'KOFFI Yao', 'mere' => 'KONAN Akissi', 'jeunefille' => 'KONAN'],
            ['nom' => 'GBAHOUE', 'prenoms' => 'Lou Irié Solange', 'annee' => 2001, 'pere' => 'GBAHOUE Bi Tra', 'mere' => 'LOU Kouamé', 'jeunefille' => null],
            ['nom' => 'ASSI', 'prenoms' => 'Yaba Florence Danielle', 'annee' => 2000, 'pere' => 'ASSI Jean-Baptiste', 'mere' => 'ADOU Chantal', 'jeunefille' => null],
            ['nom' => 'ADOU', 'prenoms' => 'Chiadon Prisca Edwige', 'annee' => 2002, 'pere' => 'ADOU Gabriel', 'mere' => 'APO Bernadette', 'jeunefille' => null],
            ['nom' => 'MEITE', 'prenoms' => 'Massandjé Mariame', 'annee' => 2003, 'pere' => 'MEITE Losseni', 'mere' => 'FOFANA Mawa', 'jeunefille' => null],
            ['nom' => 'DIARRASSOUBA', 'prenoms' => 'Kadiatou Maimouna', 'annee' => 2001, 'pere' => 'DIARRASSOUBA Zoumana', 'mere' => 'SYLLA Awa', 'jeunefille' => null],
            ['nom' => 'SANGARE', 'prenoms' => 'Makaridja Sali', 'annee' => 2000, 'pere' => 'SANGARE Adama', 'mere' => 'KONE Fanta', 'jeunefille' => null],
            ['nom' => 'TOURE', 'prenoms' => 'Minata Assétou', 'annee' => 2002, 'pere' => 'TOURE Gaoussou', 'mere' => 'DIOMANDE Maténin', 'jeunefille' => null],
            ['nom' => 'SYLLA', 'prenoms' => 'Mawa Alimata', 'annee' => 1998, 'pere' => 'SYLLA Moussa', 'mere' => 'BAMBA Salimata', 'jeunefille' => 'BAMBA'],
            ['nom' => 'FOFANA', 'prenoms' => 'Sali Djeneba', 'annee' => 2001, 'pere' => 'FOFANA Seydou', 'mere' => 'TRAORE Aminata', 'jeunefille' => null],
            ['nom' => 'DIOMANDE', 'prenoms' => 'Maténin Clarisse', 'annee' => 2003, 'pere' => 'DIOMANDE Tia', 'mere' => 'DOHO Lou Solange', 'jeunefille' => null],
            ['nom' => 'BROU', 'prenoms' => 'Affia Henriette Paule', 'annee' => 2000, 'pere' => 'BROU Konan', 'mere' => 'KOUASSI Akissi', 'jeunefille' => null],
            ['nom' => 'KRA', 'prenoms' => 'Adjoua Bernadette Cynthia', 'annee' => 2002, 'pere' => 'KRA Kobenan', 'mere' => 'YAO Amenan', 'jeunefille' => null],
            ['nom' => 'DIBY', 'prenoms' => 'Ahou Patricia Michèle', 'annee' => 2001, 'pere' => 'DIBY N\'Goran', 'mere' => 'KONAN Ahou', 'jeunefille' => null],
            ['nom' => 'AKE', 'prenoms' => 'Ogou Reine Emmanuelle', 'annee' => 1999, 'pere' => 'AKE Jean-Jaurès', 'mere' => 'GNAGNE Marie', 'jeunefille' => 'GNAGNE'],
            ['nom' => 'GNAGNE', 'prenoms' => 'Essoh Christelle Valérie', 'annee' => 2002, 'pere' => 'GNAGNE Mel Gabriel', 'mere' => 'LOBOGNON Reine', 'jeunefille' => null],
            ['nom' => 'SEKA', 'prenoms' => 'Apo Eléonore Sonia', 'annee' => 2000, 'pere' => 'SEKA Yapo', 'mere' => 'APO Chantal', 'jeunefille' => null],
            ['nom' => 'DOHO', 'prenoms' => 'Grâce Emmanuelle Noëlle', 'annee' => 2003, 'pere' => 'DOHO Gaston', 'mere' => 'BAHI Viviane', 'jeunefille' => null],
            ['nom' => 'YAPI', 'prenoms' => 'Yesso Laurette Sandra', 'annee' => 2001, 'pere' => 'YAPI N\'Cho', 'mere' => 'CHIADON Estelle', 'jeunefille' => null],
        ];

        $allData = [];
        foreach ($hommes as $h) {
            $h['sexe'] = 'MASCULIN';
            $allData[] = $h;
        }
        foreach ($femmes as $f) {
            $f['sexe'] = 'FEMININ';
            $allData[] = $f;
        }

        $currentNumeroIdx = $startIdx;
        $createdCount = 0;

        foreach ($allData as $idx => $person) {
            $numSuffix = str_pad((string)$currentNumeroIdx, 4, '0', STR_PAD_LEFT);
            $numeroCandidature = 'PA2601' . $numSuffix;
            $currentNumeroIdx++;

            // Génération de l'email unique propre
            $slugNom = strtolower(preg_replace('/[^a-zA-Z]/', '', $person['nom']));
            $firstPrenom = explode(' ', trim($person['prenoms']))[0];
            $slugPrenom = strtolower(preg_replace('/[^a-zA-Z]/', '', $firstPrenom));
            $email = sprintf('%s.%s%d@pacte.ci', $slugPrenom, $slugNom, $currentNumeroIdx);

            // Vérifier si l'utilisateur existe déjà
            $existingUser = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($existingUser) {
                $email = sprintf('candidat%d@pacte.ci', $currentNumeroIdx);
            }

            $user = (new User())
                ->setEmail($email)
                ->setNom($person['nom'])
                ->setPrenoms($person['prenoms'])
                ->setSexe($person['sexe'])
                ->setNomjeunefille($person['jeunefille'] ?? null)
                ->setDatenaissance(new \DateTime(sprintf('%d-%02d-%02d', $person['annee'], ($idx % 12) + 1, ($idx % 27) + 1)))
                ->setLieunaissance($villes[$idx % count($villes)])
                ->setResidence($villes[$idx % count($villes)] . ' - ' . $communes[$idx % count($communes)])
                ->setSituationmat($situations[$idx % count($situations)])
                ->setContact(sprintf('07%02d%02d%02d%02d', ($idx * 3) % 90 + 10, ($idx * 7) % 90 + 10, ($idx * 11) % 90 + 10, ($idx * 13) % 90 + 10))
                ->setNationalite('CI')
                ->setNompere($person['pere'])
                ->setNommere($person['mere'])
                ->setRoles(['ROLE_CANDIDAT', 'ROLE_USER']);

            $user->setPassword($this->hasher->hashPassword($user, self::CANDIDAT_PASSWORD));
            $this->em->persist($user);

            // Établissement et Métier équitablement répartis
            $etablissement = $etablissements[$idx % count($etablissements)];
            $metier = $metiers[$idx % count($metiers)];

            $diplome = $diplomes[$idx % count($diplomes)];
            $niveau = $niveaux[$idx % count($niveaux)];
            $piece = $pieces[$idx % count($pieces)];
            $numpiece = 'C' . sprintf('%09d', 100000000 + $currentNumeroIdx * 1234);

            $candidature = new Candidature();
            $candidature->setUser($user)
                ->setSession($session)
                ->setEtablissement($etablissement)
                ->setMetier($metier)
                ->setNumero($numeroCandidature)
                ->setPiece($piece)
                ->setNumpiece($numpiece)
                ->setDiplome($diplome)
                ->setNiveau($niveau)
                ->setFphoto('photo_' . strtolower($numeroCandidature) . '.jpg')
                ->setFpiece('piece_' . strtolower($numeroCandidature) . '.pdf')
                ->setFextrait('extrait_' . strtolower($numeroCandidature) . '.pdf')
                ->setFniveau('diplome_' . strtolower($numeroCandidature) . '.pdf')
                ->setFautre('cmu_' . strtolower($numeroCandidature) . '.pdf')
                ->setFicheEngagement('engagement_' . strtolower($numeroCandidature) . '.pdf');

            // Répartition des statuts réalistes : 80% acceptés, 20% rejetés
            $isAdmis = ($idx % 5 !== 0);

            if ($isAdmis) {
                $candidature->setEtustatut(2) // Accepté
                    ->setEtucom('Dossier complet, diplôme et pièce d\'identité conformes aux critères PACTE.')
                    ->setJury(($idx % 4) + 1)
                    ->setCreneauEntretien(($idx % 2) + 1)
                    ->setEntdate(new \DateTime(sprintf('2026-06-%02d 09:00:00', ($idx % 10) + 15)))
                    ->setEntlieu('Lycée Professionnel — Salle Jury ' . (($idx % 4) + 1))
                    ->setEntstatut(1) // Présent
                    ->setDecisionJury(2) // Admissible
                    ->setEntcom('Excellente prestation orale, projet professionnel cohérent et motivation affirmée.')
                    ->setNote1(3.5)
                    ->setNote2(3.0)
                    ->setNote3(4.0)
                    ->setNote4(3.5)
                    ->setNote5(4.0)
                    ->setNote6(3.5)
                    ->setNote7(3.0)
                    ->setNote8(3.5)
                    ->setNote9(4.0)
                    ->setNote10(3.5)
                    ->setNote11(3.5)
                    ->setNote12(3.0)
                    ->setNote13(3.5)
                    ->setNoteEpreuveEcrite(15.5)
                    ->setNoteEpreuveOrale(16.0)
                    ->setSelectionnePourVisite(true)
                    ->setVisdate(new \DateTime(sprintf('2026-07-%02d 08:30:00', ($idx % 8) + 5)))
                    ->setVislieu('Centre Médical Principal de Recrutement')
                    ->setVisstatut(2) // Visite effectuée
                    ->setViscom('Aptitude médicale validée sans réserve.')
                    ->setResultat(2) // Admis
                    ->setCohorte(1);
            } else {
                $candidature->setEtustatut(1) // Rejeté
                    ->setEtucom('Dossier incomplet : document d\'identité illisible ou diplôme non certifié.')
                    ->setJury(($idx % 4) + 1)
                    ->setCreneauEntretien(($idx % 2) + 1)
                    ->setEntdate(new \DateTime('2026-06-18 14:00:00'))
                    ->setEntlieu('Lycée Professionnel — Salle Jury 2')
                    ->setEntstatut(null)
                    ->setDecisionJury(1) // Non retenu
                    ->setEntcom('Critères d\'admissibilité non atteints lors de l\'examen préliminaire.')
                    ->setNote1(1.5)
                    ->setNote2(2.0)
                    ->setNote3(1.5)
                    ->setNote4(2.0)
                    ->setNote5(1.5)
                    ->setNote6(2.0)
                    ->setNote7(1.5)
                    ->setNote8(1.0)
                    ->setNote9(2.0)
                    ->setNote10(1.5)
                    ->setNote11(1.0)
                    ->setNote12(1.5)
                    ->setNote13(1.0)
                    ->setNoteEpreuveEcrite(8.5)
                    ->setNoteEpreuveOrale(9.0)
                    ->setSelectionnePourVisite(false)
                    ->setVisdate(null)
                    ->setVislieu(null)
                    ->setVisstatut(null)
                    ->setViscom(null)
                    ->setResultat(1) // Non admis
                    ->setCohorte(null);
            }

            $candidature->setCreation(new \DateTime(sprintf('2026-05-%02d %02d:%02d:00', ($idx % 20) + 1, ($idx % 12) + 8, ($idx * 3) % 60)))
                ->setModification(new \DateTime());

            $this->em->persist($candidature);
            $createdCount++;
        }

        $this->em->flush();

        $io->success(sprintf(
            '%d candidatures réelles et complètes (30 hommes et 30 femmes) ont été créées avec succès !',
            $createdCount
        ));

        return Command::SUCCESS;
    }
}
