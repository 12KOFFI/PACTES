<?php

namespace App\Controller;

use App\Entity\Candidature;
use App\Entity\Etablissement;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

#[Route('/programmation-visite-medicale')]
#[IsGranted('ROLE_USER')]
class ProgrammationVisiteMedicaleController extends AbstractController
{
    /**
     * Page unifiée : Délibération + Programmation des visites médicales.
     *
     * Étapes :
     *  1. Import Excel de la liste des admissibles (fichier du jury).
     *  2. Délibération : les DK présents → decisionJury = 2.
     *                    Les DK absents mais évalués (entstatut = 2 + notes) → decisionJury = 1.
     *  3. Programmation : les DK présents dans le fichier sont répartis sur les jours/capacités.
     *
     * Tout se fait en une seule transaction.
     */
    #[Route('/', name: 'app_programmation_visite', methods: ['GET', 'POST'])]
    public function gerer(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_ENT') && !$this->isGranted('ROLE_JURY')) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        $resultats = null;

        // ---------- Compteur prévisionnel (admissibles actuels en base) ----------
        $qbCount = $entityManager->getRepository(Candidature::class)
            ->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.decisionJury = 2');

        if (!$this->isGranted('ROLE_ADMIN')) {
            $etab = $entityManager->getRepository(Etablissement::class)->findOneBy(['user' => $this->getUser()]);
            if ($etab) {
                $qbCount->andWhere('c.etablissement = :etab')->setParameter('etab', $etab);
            }
        }
        $nombreAdmissibles = (int) $qbCount->getQuery()->getSingleScalarResult();

        if ($request->isMethod('POST')) {
            $file         = $request->files->get('fichier_excel');
            $lieu         = trim($request->request->get('lieu', ''));
            $heureDebut   = $request->request->get('heure_debut', '08:00');
            $jours        = $request->request->all('jours', []);

            // ---------- Validation ----------
            if (!$file || empty($lieu) || empty($heureDebut) || empty($jours)) {
                $this->addFlash('error', 'Veuillez remplir tous les champs obligatoires (fichier, lieu, heure, jours).');
                return $this->redirectToRoute('app_programmation_visite');
            }

            $joursValides = [];
            foreach ($jours as $jour) {
                $date = $jour['date'] ?? null;
                $capacite = (int) ($jour['capacite'] ?? 0);
                if ($date && $capacite > 0) {
                    $joursValides[] = ['date' => $date, 'capacite' => $capacite];
                }
            }
            if (empty($joursValides)) {
                $this->addFlash('error', 'Veuillez définir au moins un jour avec une capacité > 0.');
                return $this->redirectToRoute('app_programmation_visite');
            }

            try {
                // ============================================================
                // 1) LECTURE DU FICHIER EXCEL
                // ============================================================
                $spreadsheet = IOFactory::load($file->getPathname());
                $rows = $spreadsheet->getActiveSheet()->toArray();

                $numeros = [];
                foreach ($rows as $index => $row) {
                    if ($index === 0) continue; // en-tête
                    foreach ($row as $cell) {
                        $cellStr = preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', (string) $cell);
                        $cellStr = trim(strtoupper($cellStr));
                        if (preg_match('/^DK\d+/', $cellStr)) {
                            $numeros[] = $cellStr;
                            break;
                        }
                    }
                }

                if (empty($numeros)) {
                    $this->addFlash('error', 'Aucun numéro de candidature (DK…) trouvé dans le fichier.');
                    return $this->redirectToRoute('app_programmation_visite');
                }

                $numerosSet = array_values(array_unique($numeros));

                // ============================================================
                // 2) DÉLIBÉRATION
                // ============================================================
                $qb = $entityManager->getRepository(Candidature::class)
                    ->createQueryBuilder('c')
                    ->leftJoin('c.user', 'u')->addSelect('u')
                    ->leftJoin('c.etablissement', 'e')->addSelect('e')
                    ->leftJoin('c.metier', 'm')->addSelect('m')
                    ->where('c.etustatut = 2')
                    ->orderBy('u.nom', 'ASC')
                    ->addOrderBy('u.prenoms', 'ASC');

                if (!$this->isGranted('ROLE_ADMIN')) {
                    $etab = $entityManager->getRepository(Etablissement::class)->findOneBy(['user' => $this->getUser()]);
                    if ($etab) {
                        $qb->andWhere('c.etablissement = :etab')->setParameter('etab', $etab);
                    }
                }

                /** @var Candidature[] $candidatsEligibles */
                $candidatsEligibles = $qb->getQuery()->getResult();

                $admissibles          = [];
                $nonAdmissibles       = [];
                $numerosUtilises      = [];
                $candidatsAProgrammer = [];
                $dejaProgrammes       = [];

                foreach ($candidatsEligibles as $candidature) {
                    $numero = strtoupper(trim((string) $candidature->getNumero()));

                    if (in_array($numero, $numerosSet, true)) {
                        // ✅ Retenu pour la visite médicale
                        $candidature->setDecisionJury(2);
                        $admissibles[]        = $candidature;
                        $numerosUtilises[]    = $numero;
                        $candidatsAProgrammer[] = $candidature;

                        if ($candidature->getVisdate()) {
                            $dejaProgrammes[] = $candidature->getNumero();
                        }
                    } else {
                        // ❌ Non retenu — mais on ne "rétrograde" que ceux qui ont été évalués
                        //    ET qui ont au moins une note saisie.
                        if ($candidature->getEntstatut() === 2 && $this->aDesNotes($candidature)) {
                            $candidature->setDecisionJury(1);
                            $candidature->setVisdate(null);
                            $candidature->setVislieu(null);
                            $nonAdmissibles[] = $candidature;
                        }
                    }

                    $entityManager->persist($candidature);
                }

                // ============================================================
                // 3) NUMÉROS DU FICHIER NON UTILISÉS (introuvables ou non éligibles)
                // ============================================================
                $numerosNonUtilises = array_values(array_diff($numerosSet, $numerosUtilises));
                $introuvables = [];
                $nonEligiblesDossier = [];

                foreach ($numerosNonUtilises as $numero) {
                    $existe = $entityManager->getRepository(Candidature::class)
                        ->createQueryBuilder('c')
                        ->where('UPPER(TRIM(c.numero)) = :numero')
                        ->setParameter('numero', $numero)
                        ->setMaxResults(1)
                        ->getQuery()
                        ->getOneOrNullResult();

                    if ($existe) {
                        $nonEligiblesDossier[] = $numero;
                    } else {
                        $introuvables[] = $numero;
                    }
                }

                // ============================================================
                // 4) PROGRAMMATION
                // ============================================================
                $planifies = [];
                $sansCreneau = [];

                if (!empty($candidatsAProgrammer)) {
                    // Réinitialiser les visites pour ceux qu'on (re)programme
                    foreach ($candidatsAProgrammer as $c) {
                        $c->setVisdate(null);
                        $c->setVislieu(null);
                    }

                    $index = 0;
                    $total = count($candidatsAProgrammer);

                    foreach ($joursValides as $jour) {
                        try {
                            $visiteTime = new \DateTime($jour['date'] . ' ' . $heureDebut . ':00');
                        } catch (\Exception $e) {
                            continue;
                        }

                        for ($i = 0; $i < $jour['capacite'] && $index < $total; $i++) {
                            $candidat = $candidatsAProgrammer[$index++];
                            $candidat->setVisdate(clone $visiteTime);
                            $candidat->setVislieu($lieu);

                            $planifies[] = [
                                'candidature' => $candidat,
                                'date'        => $jour['date'],
                                'heure'       => $visiteTime->format('H:i'),
                                'reprogramme' => in_array($candidat->getNumero(), $dejaProgrammes, true),
                            ];
                        }
                    }

                    while ($index < $total) {
                        $sansCreneau[] = $candidatsAProgrammer[$index++]->getNumero();
                    }
                }

                // ============================================================
                // 5) FLUSH UNIQUE — ATOMICITÉ
                // ============================================================
                $entityManager->flush();

                error_log(sprintf(
                    '[VISITE_MED] Fichier=%d | Admissibles=%d | NonAdmissibles=%d | Programmés=%d | Introuvables=%d | Sans créneau=%d',
                    count($numerosSet),
                    count($admissibles),
                    count($nonAdmissibles),
                    count($planifies),
                    count($introuvables),
                    count($sansCreneau)
                ));

                $this->addFlash('success', sprintf(
                    'Délibération + programmation effectuées : %d admissible(s), %d non admissible(s), %d programmé(s), %d sans créneau.',
                    count($admissibles),
                    count($nonAdmissibles),
                    count($planifies),
                    count($sansCreneau)
                ));

                $resultats = [
                    // Délibération
                    'total_fichier'         => count($numerosSet),
                    'admissibles'           => $admissibles,
                    'non_admissibles'       => $nonAdmissibles,
                    'introuvables'          => $introuvables,
                    'non_eligibles_dossier' => $nonEligiblesDossier,
                    // Programmation
                    'lieu'                  => $lieu,
                    'heure_debut'           => $heureDebut,
                    'jours'                 => $joursValides,
                    'total_candidats'       => count($admissibles),
                    'planifies'             => $planifies,
                    'sans_creneau'          => $sansCreneau,
                    'deja_programmes'       => $dejaProgrammes,
                ];

            } catch (FileException $e) {
                $this->addFlash('error', 'Erreur lors du traitement du fichier Excel.');
                error_log('[VISITE_MED] Erreur fichier : ' . $e->getMessage());
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur : ' . $e->getMessage());
                error_log('[VISITE_MED] Erreur : ' . $e->getMessage());
            }
        }

        return $this->render('programmation_visite/index.html.twig', [
            'resultats'          => $resultats,
            'nombre_admissibles' => $nombreAdmissibles,
        ]);
    }

    /**
     * Retourne true si au moins une note (note1..note13) est renseignée sur la candidature.
     */
    private function aDesNotes(Candidature $candidature): bool
    {
        for ($i = 1; $i <= 13; $i++) {
            $getter = 'getNote' . $i;
            if ($candidature->$getter() !== null) {
                return true;
            }
        }
        return false;
    }

    // ==================================================================
    // EXPORT PLANNING (inchangé — inchangé par rapport à la version existante)
    // ==================================================================
    #[Route('/export-planning', name: 'app_programmation_visite_export', methods: ['GET'])]
    public function exportPlanning(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_ENT') && !$this->isGranted('ROLE_JURY')) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        $lieu = trim($request->query->get('lieu', ''));
        if ($lieu === '') {
            $this->addFlash('error', 'Impossible de générer l\'export : lieu manquant.');
            return $this->redirectToRoute('app_programmation_visite');
        }

        $qb = $entityManager->getRepository(Candidature::class)
            ->createQueryBuilder('c')
            ->leftJoin('c.user', 'u')->addSelect('u')
            ->leftJoin('c.etablissement', 'e')->addSelect('e')
            ->leftJoin('c.metier', 'm')->addSelect('m')
            ->where('c.vislieu = :lieu')
            ->andWhere('c.visdate IS NOT NULL')
            ->setParameter('lieu', $lieu)
            ->orderBy('c.visdate', 'ASC')
            ->addOrderBy('u.nom', 'ASC')
            ->addOrderBy('u.prenoms', 'ASC');

        if (!$this->isGranted('ROLE_ADMIN')) {
            $etab = $entityManager->getRepository(Etablissement::class)->findOneBy(['user' => $this->getUser()]);
            if ($etab) {
                $qb->andWhere('c.etablissement = :etab')->setParameter('etab', $etab);
            }
        }

        $candidatures = $qb->getQuery()->getResult();
        if (empty($candidatures)) {
            $this->addFlash('error', 'Aucun candidat programmé pour ce lieu.');
            return $this->redirectToRoute('app_programmation_visite');
        }

        $parJour = [];
        foreach ($candidatures as $c) {
            $parJour[$c->getVisdate()->format('Y-m-d')][] = $c;
        }
        ksort($parJour);

        $headers = ['N°', 'N° Candidature', 'Nom', 'Prénoms', 'Sexe', 'Contact',
                    'Établissement', 'Métier', 'Date visite', 'Heure visite', 'Lieu visite'];

        $joursFR = [
            'Monday' => 'Lundi', 'Tuesday' => 'Mardi', 'Wednesday' => 'Mercredi',
            'Thursday' => 'Jeudi', 'Friday' => 'Vendredi', 'Saturday' => 'Samedi', 'Sunday' => 'Dimanche',
        ];

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);
        $sheetIndex = 0;

        foreach ($parJour as $jour => $candidatsJour) {
            $dateObj = new \DateTime($jour);
            $jourSemaine = $joursFR[$dateObj->format('l')] ?? $dateObj->format('l');
            $sheetTitle = $jourSemaine . ' ' . $dateObj->format('d-m-Y');
            if (mb_strlen($sheetTitle) > 31) $sheetTitle = mb_substr($sheetTitle, 0, 31);

            $sheet = $spreadsheet->createSheet($sheetIndex);
            $sheet->setTitle($sheetTitle);

            $sheet->mergeCells('A1:K1');
            $sheet->setCellValue('A1', strtoupper($jourSemaine) . ' ' . $dateObj->format('d/m/Y') . '  —  ' . $lieu);
            $sheet->getStyle('A1:K1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle('A1:K1')->getFont()->setSize(13);
            $sheet->getStyle('A1:K1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0D9488');
            $sheet->getStyle('A1:K1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A1:K1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getRowDimension(1)->setRowHeight(28);

            $sheet->fromArray($headers, null, 'A2');
            $sheet->getStyle('A2:K2')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle('A2:K2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('14B8A6');
            $sheet->getStyle('A2:K2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $row = 3;
            foreach ($candidatsJour as $index => $candidature) {
                $user = $candidature->getUser();
                $sheet->fromArray([
                    $index + 1,
                    $candidature->getNumero(),
                    $user ? $user->getNom() : '',
                    $user ? $user->getPrenoms() : '',
                    $user ? $user->getSexe() : '',
                    $user ? $user->getContact() : '',
                    $candidature->getEtablissement()?->getNom() ?? '',
                    $candidature->getMetier()?->getNom() ?? '',
                    $candidature->getVisdate()?->format('d/m/Y') ?? '',
                    $candidature->getVisdate()?->format('H:i') ?? '',
                    $candidature->getVislieu() ?? '',
                ], null, 'A' . $row);
                $row++;
            }

            foreach (range('A', 'K') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            $sheetIndex++;
        }

        $spreadsheet->setActiveSheetIndex(0);
        $filename = 'planning_visites_medicales_' . date('Ymd_His') . '.xlsx';

        $response = new \Symfony\Component\HttpFoundation\StreamedResponse(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        });
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }
}
