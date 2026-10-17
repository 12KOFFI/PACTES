<?php

namespace App\Controller;

use App\Entity\Candidature;
use App\Entity\Etablissement;
use App\Entity\Metier;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/programmation-entretien')]
#[IsGranted('ROLE_USER')]
class ProgrammationEntretienController extends AbstractController
{
    /**
     * Pourcentage de la capacité quotidienne d'un jury affecté au Créneau 1 (matin, 08h-12h).
     * Le reste va au Créneau 2 (après-midi, 13h-15h).
     * Valeur fixée en dur selon la directive du chef de projet (ex: 25/40 = 62,5%).
     * À modifier ici directement si la répartition doit changer.
     */
    private const CRENEAU1_PERCENT = 62.5;

    #[Route('/programmer', name: 'app_programmation_entretien', methods: ['GET', 'POST'])]
    public function programmer(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_ENT') && !$this->isGranted('ROLE_JURY')) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        $resultats = null;

        if ($request->isMethod('POST')) {
            $file = $request->files->get('fichier_excel');
            $nbJurys = (int) $request->request->get('nb_jury', 1);
            $capaciteParJuryParJour = (int) $request->request->get('capacite_jury_jour', 0);
            $dateDebutInput = $request->request->get('date_debut');
            $dateFinInput = $request->request->get('date_fin');
            $metierId = $request->request->get('metier_id');

            // Détermination de l'établissement et du lieu
            $user = $this->getUser();
            if ($this->isGranted('ROLE_ENT')) {
                $etablissement = $entityManager->getRepository(Etablissement::class)->findOneBy(['user' => $user]);
                $lieu = $etablissement ? $etablissement->getNom() : '';
            } else {
                $etablissementId = $request->request->get('etablissement_id');
                $etablissement = $etablissementId ? $entityManager->getRepository(Etablissement::class)->find($etablissementId) : null;
                $lieu = $etablissement ? $etablissement->getNom() : trim($request->request->get('lieu', ''));
            }

            if (!$file || !$dateDebutInput || !$dateFinInput || $nbJurys <= 0 || $capaciteParJuryParJour <= 0 || empty($lieu) || !$metierId) {
                $this->addFlash('error', 'Veuillez remplir tous les champs obligatoires.');
                return $this->redirectToRoute('app_programmation_entretien');
            }

            // Génération des dates
            $datesList = [];
            try {
                $start = new \DateTime($dateDebutInput);
                $end = new \DateTime($dateFinInput);
                $end->modify('+1 day');

                $period = new \DatePeriod($start, new \DateInterval('P1D'), $end);
                foreach ($period as $dt) {
                    $datesList[] = $dt->format('Y-m-d');
                }
            } catch (\Exception $e) {
                $this->addFlash('error', 'Les dates spécifiées ne sont pas valides.');
                return $this->redirectToRoute('app_programmation_entretien');
            }

            if (empty($datesList)) {
                $this->addFlash('error', 'La plage de dates sélectionnée est invalide.');
                return $this->redirectToRoute('app_programmation_entretien');
            }

            try {
                $spreadsheet = IOFactory::load($file->getPathname());
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray();

                // ✅ Détection intelligente du numéro DK dans n'importe quelle colonne
                $numeros = [];
                foreach ($rows as $index => $row) {
                    if ($index === 0) continue;

                    $num = '';
                    foreach ($row as $cell) {
                        $cellStr = preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', (string)$cell);
                        $cellStr = trim(strtoupper($cellStr));

                        if (preg_match('/^DK\d+/', $cellStr)) {
                            $num = $cellStr;
                            break;
                        }
                    }

                    if ($num !== '') {
                        $numeros[] = $num;
                    }
                }

                if (empty($numeros)) {
                    $this->addFlash('error', 'Aucun numéro de candidature trouvé dans le fichier.');
                    return $this->redirectToRoute('app_programmation_entretien');
                }

                // ✅ Recherche des candidatures éligibles (etustatut = 2) + métier + établissement
                $qb = $entityManager->getRepository(Candidature::class)
                    ->createQueryBuilder('c')
                    ->where('UPPER(TRIM(c.numero)) IN (:numeros)')
                    ->andWhere('c.etustatut = 2')
                    ->andWhere('c.metier = :metierId')
                    ->setParameter('numeros', $numeros)
                    ->setParameter('metierId', $metierId);

                if ($etablissement) {
                    $qb->andWhere('c.etablissement = :etablissement')
                       ->setParameter('etablissement', $etablissement);
                }

                $candidatures = $qb->getQuery()->getResult();

                $trouvesNumeros = array_map(fn($c) => strtoupper(trim($c->getNumero())), $candidatures);
                $introuvables = array_values(array_diff($numeros, $trouvesNumeros));

                if (empty($candidatures)) {
                    $this->addFlash('error', sprintf(
                        'Aucune candidature éligible trouvée. %d numéro(s) reçu(s), 0 correspondance.',
                        count($numeros)
                    ));
                    return $this->redirectToRoute('app_programmation_entretien');
                }

                // ✅ RÉPARTITION :
                // - La capacité par jury et par jour est saisie manuellement (le nombre de candidats
                //   peut varier d'une session à l'autre).
                // - Elle est répartie entre les deux créneaux selon le pourcentage fixé en dur dans le code
                //   (self::CRENEAU1_PERCENT, ex: 62,5% → 25 le matin / 15 l'après-midi sur 40).
                $nbJours = count($datesList);

                $capaciteCreneau1 = (int) round($capaciteParJuryParJour * self::CRENEAU1_PERCENT / 100);
                $capaciteCreneau2 = $capaciteParJuryParJour - $capaciteCreneau1;

                $creneaux = [
                    1 => ['heure_debut' => 8,  'horaire' => '08h00 - 12h00', 'capacite' => $capaciteCreneau1],
                    2 => ['heure_debut' => 13, 'horaire' => '13h00 - 15h00', 'capacite' => $capaciteCreneau2],
                ];

                $planifies = [];
                $sansCreneau = [];
                $dejaProgrammes = [];

                // Détection des candidats déjà programmés AVANT d'écraser leurs données
                foreach ($candidatures as $candidature) {
                    if ($candidature->getEntdate() && $candidature->getJury()) {
                        $dejaProgrammes[] = $candidature->getNumero();
                    }
                }

                // On consomme la liste des candidats dans l'ordre : pour chaque date, pour chaque jury,
                // on remplit d'abord le Créneau 1 (capaciteCreneau1 candidats) puis le Créneau 2 (capaciteCreneau2).
                $candidatIndex = 0;
                $totalCandidats = count($candidatures);

                foreach ($datesList as $dateCourante) {
                    for ($jury = 1; $jury <= $nbJurys; $jury++) {
                        foreach ($creneaux as $numCreneau => $config) {
                            for ($i = 0; $i < $config['capacite'] && $candidatIndex < $totalCandidats; $i++) {
                                $candidature = $candidatures[$candidatIndex];
                                $candidatIndex++;

                                $dateTime = new \DateTime($dateCourante . ' ' . str_pad((string)$config['heure_debut'], 2, '0', STR_PAD_LEFT) . ':00:00');

                                $candidature->setEntdate($dateTime);
                                $candidature->setJury($jury);
                                $candidature->setCreneauEntretien($numCreneau);
                                $candidature->setEntlieu($lieu);

                                $entityManager->persist($candidature);

                                $planifies[] = [
                                    'candidature' => $candidature,
                                    'date' => $dateCourante,
                                    'creneau' => $numCreneau,
                                    'jury' => $jury,
                                    'reprogramme' => in_array($candidature->getNumero(), $dejaProgrammes, true)
                                ];
                            }
                        }
                    }
                }

                // Candidats restants au-delà de la capacité totale
                while ($candidatIndex < $totalCandidats) {
                    $sansCreneau[] = $candidatures[$candidatIndex]->getNumero();
                    $candidatIndex++;
                }

                // ✅ Flush unique après la boucle
                $entityManager->flush();

                // ✅ Logs
                error_log(sprintf(
                    '[PROGRAMMATION] Fichier: %d | Trouvés: %d | Planifiés: %d | Introuvables: %d | Sans créneau: %d',
                    count($numeros),
                    count($candidatures),
                    count($planifies),
                    count($introuvables),
                    count($sansCreneau)
                ));

                $page = $request->query->getInt('page', 1);
                $perPage = 10;
                $totalItems = count($planifies);
                $totalPages = (int)ceil($totalItems / $perPage);
                $offset = ($page - 1) * $perPage;
                $paginatedPlanifies = array_slice($planifies, $offset, $perPage, true);

                if ($request->isXmlHttpRequest()) {
                    $html = $this->renderView('programmation_entretien/_planifiques_table_body.html.twig', [
                        'planifies' => $paginatedPlanifies,
                    ]);
                    return new JsonResponse([
                        'html' => $html,
                        'pagination' => [
                            'current' => $page,
                            'perPage' => $perPage,
                            'totalItems' => $totalItems,
                            'totalPages' => $totalPages,
                        ]
                    ]);
                }

                $resultats = [
                    'total_excel' => count($numeros),
                    'nb_nouveaux' => count($planifies),
                    'nb_reprogrammes' => count($dejaProgrammes),
                    'lieu' => $lieu,
                    'introuvables' => $introuvables,
                    'non_admissibles' => [],
                    'sans_creneau' => $sansCreneau,
                    'deja_programmes' => $dejaProgrammes,
                    'creneaux_disponibles' => count($datesList) * $nbJurys * $capaciteParJuryParJour,
                    'capacite_jury_jour' => $capaciteParJuryParJour,
                    'capacite_creneau1' => $capaciteCreneau1,
                    'capacite_creneau2' => $capaciteCreneau2,
                    'nb_jours' => $nbJours,
                    'date_debut' => $dateDebutInput,
                    'date_fin' => $dateFinInput,
                    'metier_id' => $metierId,
                    'etablissement_id' => $etablissement ? $etablissement->getId() : null,
                    'planifies' => $paginatedPlanifies,
                    'pagination' => [
                        'current' => $page,
                        'perPage' => $perPage,
                        'totalItems' => $totalItems,
                        'totalPages' => $totalPages,
                    ]
                ];

                $this->addFlash('success', sprintf(
                    'Répartition effectuée : %d programmé(s), %d introuvable(s), %d sans créneau.',
                    count($planifies),
                    count($introuvables),
                    count($sansCreneau)
                ));

            } catch (FileException $e) {
                $this->addFlash('error', 'Erreur lors du traitement du fichier Excel.');
                error_log('[PROGRAMMATION] Erreur fichier : ' . $e->getMessage());
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la programmation : ' . $e->getMessage());
                error_log('[PROGRAMMATION] Erreur : ' . $e->getMessage());
            }
        }

        $etablissements = $entityManager->getRepository(Etablissement::class)->findAll();
        $metiers = $entityManager->getRepository(Metier::class)->findAll();

        $etablissementConnecte = null;
        if ($this->isGranted('ROLE_ENT')) {
            $etablissementConnecte = $entityManager->getRepository(Etablissement::class)->findOneBy(['user' => $this->getUser()]);
        }

        return $this->render('programmation_entretien/index.html.twig', [
            'title' => 'Programmation des tests d\'entretien',
            'resultats' => $resultats,
            'etablissements' => $etablissements,
            'metiers' => $metiers,
            'etablissementConnecte' => $etablissementConnecte,
        ]);
    }

    /**
     * Exporte le planning des entretiens en Excel, avec une feuille par jury.
     * Se base sur les candidatures déjà programmées (entlieu + entdate) correspondant
     * aux critères de la dernière programmation (lieu, période, métier, établissement).
     *
     * Chaque feuille contient des bandeaux de séparation affichant le JOUR et le CRÉNEAU
     * (ex: "MARDI 15/09/2026 | CRÉNEAU 1 — MATIN (08h00 - 12h00)").
     */
    #[Route('/export-planning', name: 'app_programmation_entretien_export', methods: ['GET'])]
    public function exportPlanning(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_ENT') && !$this->isGranted('ROLE_JURY')) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        $lieu = trim($request->query->get('lieu', ''));
        $dateDebutInput = $request->query->get('date_debut');
        $dateFinInput = $request->query->get('date_fin');
        $metierId = $request->query->get('metier_id');
        $etablissementId = $request->query->get('etablissement_id');

        if ($lieu === '' || !$dateDebutInput || !$dateFinInput) {
            $this->addFlash('error', 'Impossible de générer l\'export : critères de programmation manquants.');
            return $this->redirectToRoute('app_programmation_entretien');
        }

        try {
            $debut = new \DateTime($dateDebutInput . ' 00:00:00');
            $fin = new \DateTime($dateFinInput . ' 23:59:59');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Dates invalides pour l\'export.');
            return $this->redirectToRoute('app_programmation_entretien');
        }

        $qb = $entityManager->getRepository(Candidature::class)->createQueryBuilder('c')
            ->andWhere('c.entlieu = :lieu')
            ->andWhere('c.entdate BETWEEN :debut AND :fin')
            ->andWhere('c.jury IS NOT NULL')
            ->setParameter('lieu', $lieu)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->orderBy('c.jury', 'ASC')
            ->addOrderBy('c.entdate', 'ASC');

        if ($metierId) {
            $qb->andWhere('c.metier = :metierId')->setParameter('metierId', $metierId);
        }
        if ($etablissementId) {
            $qb->andWhere('c.etablissement = :etablissement')->setParameter('etablissement', $etablissementId);
        }

        $candidatures = $qb->getQuery()->getResult();

        if (empty($candidatures)) {
            $this->addFlash('error', 'Aucun candidat programmé ne correspond à ces critères.');
            return $this->redirectToRoute('app_programmation_entretien');
        }

        // Regroupement par numéro de jury
        $parJury = [];
        foreach ($candidatures as $candidature) {
            $parJury[$candidature->getJury()][] = $candidature;
        }
        ksort($parJury);

        // ✅ Tri de chaque liste de jury par (JOUR, CRÉNEAU, NOM, PRÉNOM)
        foreach ($parJury as $numJury => $candidatsJury) {
            usort($candidatsJury, static function (Candidature $a, Candidature $b): int {
                // 1) Comparaison sur le JOUR (date)
                $dateA = $a->getEntdate() ? $a->getEntdate()->format('Y-m-d') : '9999-12-31';
                $dateB = $b->getEntdate() ? $b->getEntdate()->format('Y-m-d') : '9999-12-31';
                if ($dateA !== $dateB) {
                    return strcmp($dateA, $dateB);
                }

                // 2) Comparaison sur le CRÉNEAU (Créneau 1 avant Créneau 2)
                $creneauA = $a->getCreneauEntretien() ?? 999;
                $creneauB = $b->getCreneauEntretien() ?? 999;
                if ($creneauA !== $creneauB) {
                    return $creneauA <=> $creneauB;
                }

                // 3) Comparaison sur le NOM
                $nomA = $a->getUser() ? $a->getUser()->getNom() : '';
                $nomB = $b->getUser() ? $b->getUser()->getNom() : '';
                $cmpNom = strcasecmp((string) $nomA, (string) $nomB);
                if ($cmpNom !== 0) {
                    return $cmpNom;
                }

                // 4) Comparaison sur le PRÉNOM
                $prenomA = $a->getUser() ? $a->getUser()->getPrenoms() : '';
                $prenomB = $b->getUser() ? $b->getUser()->getPrenoms() : '';
                return strcasecmp((string) $prenomA, (string) $prenomB);
            });
            $parJury[$numJury] = $candidatsJury;
        }

        $headers = [
            'No', 'No Candidature', 'Nom', 'Prénom', 'Email', 'Sexe', 'Contact', 'Établissement', 'Métier',
            'Date de naissance', 'Résidence', 'Date entretien', 'Créneau entretien', 'Heure entretien',
        ];

        // Tableau associatif pour convertir le nom du jour anglais en français
        $joursFR = [
            'Monday'    => 'Lundi',
            'Tuesday'   => 'Mardi',
            'Wednesday' => 'Mercredi',
            'Thursday'  => 'Jeudi',
            'Friday'    => 'Vendredi',
            'Saturday'  => 'Samedi',
            'Sunday'    => 'Dimanche',
        ];

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $sheetIndex = 0;
        foreach ($parJury as $numJury => $candidatsJury) {
            $sheet = $spreadsheet->createSheet($sheetIndex);
            $sheet->setTitle('Jury ' . $numJury);

            // En-tête
            $sheet->fromArray($headers, null, 'A1');
            $sheet->getStyle('A1:M1')->getFont()->setBold(true);
            $sheet->getStyle('A1:M1')->getFont()->getColor()->setRGB('FFFFFF');
            $sheet->getStyle('A1:M1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4F46E5');

            $row = 2;
            $no = 1;
            $jourCourant = null;
            $creneauCourant = null;

            foreach ($candidatsJury as $candidature) {
                $creneau = $candidature->getCreneauEntretien();
                $entdate = $candidature->getEntdate();
                $jour = $entdate ? $entdate->format('Y-m-d') : null;

                // ✅ Nouveau bandeau : dès qu'on change de JOUR ou de CRÉNEAU
                if ($jour !== $jourCourant || $creneau !== $creneauCourant) {
                    $jourCourant = $jour;
                    $creneauCourant = $creneau;

                    // Ligne vide pour aérer
                    $row++;

                    // Construction du libellé : "MARDI 15/09/2026 | CRÉNEAU 1 — MATIN (08h00 - 12h00)"
                    if ($entdate) {
                        $jourSemaine = $joursFR[$entdate->format('l')] ?? $entdate->format('l');
                        $libelleJour = strtoupper($jourSemaine) . ' ' . $entdate->format('d/m/Y');
                    } else {
                        $libelleJour = 'DATE NON DÉFINIE';
                    }

                    $libelleCreneau = $creneau === 1
                        ? 'CRÉNEAU 1 — MATIN (08h00 - 12h00)'
                        : 'CRÉNEAU 2 — APRÈS-MIDI (13h00 - 15h00)';

                    $titreBandeau = $libelleJour . '   |   ' . $libelleCreneau;

                    // Fusion de A à M pour le bandeau
                    $sheet->mergeCells('A' . $row . ':M' . $row);
                    $sheet->setCellValue('A' . $row, $titreBandeau);

                    // Style du bandeau
                    $sheet->getStyle('A' . $row . ':M' . $row)->getFont()->setBold(true);
                    $sheet->getStyle('A' . $row . ':M' . $row)->getFont()->getColor()->setRGB('FFFFFF');
                    $sheet->getStyle('A' . $row . ':M' . $row)->getFont()->setSize(12);
                    $sheet->getStyle('A' . $row . ':M' . $row)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB($creneau === 1 ? '0EA5E9' : 'F59E0B');

                    // Centrer le texte
                    $sheet->getStyle('A' . $row . ':M' . $row)->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle('A' . $row . ':M' . $row)->getAlignment()
                        ->setVertical(Alignment::VERTICAL_CENTER);

                    $row++;
                }

                $user = $candidature->getUser();

                $sheet->fromArray([
                    $no,
                    $candidature->getNumero(),
                    $user ? $user->getNom() : '',
                    $user ? $user->getPrenoms() : '',
                    $user ? $user->getEmail() : '',
                    $user ? $user->getContact() : '',
                    $user ? $user->getSexe() : '',
                    $candidature->getEtablissement() ? $candidature->getEtablissement()->getNom() : '',
                    $candidature->getMetier() ? $candidature->getMetier()->getNom() : '',
                    ($user && $user->getDatenaissance()) ? $user->getDatenaissance()->format('d/m/Y') : '',
                    $user ? $user->getResidence() : '',
                    $entdate ? $entdate->format('d/m/Y') : '',
                    $creneau ? 'Créneau ' . $creneau : '',
                    $entdate ? $entdate->format('H:i') : '',
                ], null, 'A' . $row);

                $row++;
                $no++;
            }

            foreach (range('A', 'M') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $sheetIndex++;
        }

        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'planning_entretiens_' . (new \DateTime())->format('Ymd_His') . '.xlsx';

        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }
}
