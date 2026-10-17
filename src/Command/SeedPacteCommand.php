<?php

namespace App\Command;

use App\Entity\Etablissement;
use App\Entity\EtablissementMetier;
use App\Entity\Localite;
use App\Entity\Metier;
use App\Entity\SessionRecrutement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed-pacte', description: 'Charge les données initiales PACTE 2026 - Vague 1 (idempotent)')]
class SeedPacteCommand extends Command
{
    private const METIER_PV = 'Installateur de systèmes solaires photovoltaïques';
    private const METIER_EE = 'Technicien en efficacité énergétique';
    private const SESSION_NOM = 'PACTE 2026 — Vague 1';

    /** Ordre significatif : EtablissementsActifsConfig::ACTIFS_IDS = [1, 2, 3]. */
    private const ETABLISSEMENTS = [
        1 => ['nom' => 'Lycée Professionnel des Métiers de l\'Énergie Renouvelable', 'localite' => null, 'places' => 90],
        2 => ['nom' => 'Lycée Professionnel de Jacqueville', 'localite' => 'Jacqueville', 'places' => 80],
        3 => ['nom' => 'Lycée Professionnel de Korhogo', 'localite' => 'Korhogo', 'places' => 80],
    ];

    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $conn = $this->em->getConnection();
        $conn->beginTransaction();

        try {
            // Localités
            $localites = [];
            foreach (['Jacqueville', 'Korhogo'] as $nom) {
                $localite = $this->em->getRepository(Localite::class)->findOneBy(['nom' => $nom]);
                if (!$localite) {
                    $localite = (new Localite())->setNom($nom)->setType('ville');
                    $this->em->persist($localite);
                    $this->em->flush();
                    $io->text("Localité créée : $nom");
                }
                $localites[$nom] = $localite;
            }

            // Établissements (dans l'ordre, IDs attendus 1, 2, 3)
            $etablissements = [];
            foreach (self::ETABLISSEMENTS as $idAttendu => $def) {
                $etab = $this->em->getRepository(Etablissement::class)->findOneBy(['nom' => $def['nom']]);
                if (!$etab) {
                    $etab = (new Etablissement())->setNom($def['nom']);
                    if ($def['localite']) {
                        $etab->setLocalite($localites[$def['localite']]);
                    }
                    $this->em->persist($etab);
                    $this->em->flush();
                    $io->text('Établissement créé : ' . $def['nom']);
                }
                if ($etab->getId() !== $idAttendu) {
                    throw new \RuntimeException(sprintf(
                        'ID inattendu pour « %s » : %d obtenu, %d attendu. La base n\'est pas vide/neuve ; EtablissementsActifsConfig::ACTIFS_IDS suppose les IDs 1, 2, 3. Aucune donnée enregistrée.',
                        $def['nom'],
                        $etab->getId(),
                        $idAttendu
                    ));
                }
                $etablissements[$idAttendu] = $etab;
            }

            // Métiers
            $metiers = [];
            foreach ([self::METIER_PV, self::METIER_EE] as $nom) {
                $metier = $this->em->getRepository(Metier::class)->findOneBy(['nom' => $nom]);
                if (!$metier) {
                    $metier = (new Metier())->setNom($nom);
                    $this->em->persist($metier);
                    $io->text("Métier créé : $nom");
                }
                $metiers[$nom] = $metier;
            }
            $this->em->flush();

            // Session
            $session = $this->em->getRepository(SessionRecrutement::class)->findOneBy(['annee' => 2026, 'numeroVague' => 1]);
            if (!$session) {
                $session = (new SessionRecrutement())
                    ->setNom(self::SESSION_NOM)
                    ->setAnnee(2026)
                    ->setNumeroVague(1)
                    ->setDateOuverture(new \DateTime('2026-10-01 00:00:00'))
                    ->setDateCloture(new \DateTime('2026-12-31 23:59:59'))
                    ->setDateReferenceAge(new \DateTime('2026-12-31'))
                    ->setAgeMin(18)
                    ->setAgeMax(35)
                    ->setStatut(SessionRecrutement::STATUT_OUVERTE)
                    ->setActive(true);
                $this->em->persist($session);
                $io->text('Session créée (OUVERTE) : ' . self::SESSION_NOM);
            }

            // Associations du premier métier uniquement
            foreach (self::ETABLISSEMENTS as $id => $def) {
                $existe = $this->em->getRepository(EtablissementMetier::class)
                    ->findOneBy(['etablissement' => $etablissements[$id], 'metier' => $metiers[self::METIER_PV]]);
                if (!$existe) {
                    $em = (new EtablissementMetier())
                        ->setEtablissement($etablissements[$id])
                        ->setMetier($metiers[self::METIER_PV])
                        ->setNbrplace($def['places'])
                        ->setDureeFormation('6 mois')
                        ->setEstActif(true);
                    $this->em->persist($em);
                    $io->text('Association créée : ' . $def['nom'] . ' (' . $def['places'] . ' places)');
                }
            }

            $this->em->flush();
            $conn->commit();
        } catch (\Throwable $e) {
            if ($conn->isTransactionActive()) {
                $conn->rollBack();
            }
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $io->success('Données PACTE chargées. La session reste en BROUILLON : l\'ouvrir depuis l\'administration.');
        $io->note('Aucun compte n\'est créé (utiliser app:user:create ; ne jamais mettre de mot de passe en dur).');

        return Command::SUCCESS;
    }
}
