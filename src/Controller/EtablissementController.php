<?php

namespace App\Controller;

use App\Entity\Etablissement;
use App\Entity\Candidature;
use App\Entity\Metier;
use App\Entity\Admis;
use App\Form\EtablissementType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[Route('/etablissement')]
class EtablissementController extends AbstractController
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/', name: 'app_etablissement_index', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function index(): Response
    {
        $etablissements = $this->entityManager->getRepository(Etablissement::class)
            ->findBy([], ['nom' => 'ASC']);
        
        return $this->render('etablissement/index.html.twig', [
            'etablissements' => $etablissements
        ]);
    }

    #[Route('/new', name: 'app_etablissement_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request): Response
    {
        $etablissement = new Etablissement();
        $etablissement->setUser($this->getUser());
        $etablissement->setCreation(new \DateTime());

        $form = $this->createForm(EtablissementType::class, $etablissement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($etablissement);
            $this->entityManager->flush();

            $this->addFlash('success', 'Établissement créé avec succès.');
            return $this->redirectToRoute('app_etablissement_index');
        }

        return $this->render('etablissement/form.html.twig', [
            'etablissement' => $etablissement,
            'form' => $form
        ]);
    }

    #[Route('/{id}/edit', name: 'app_etablissement_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Etablissement $etablissement): Response
    {
        // Allow ADMIN to edit any establishment, ENT can only edit their own
        if (!$this->isGranted('ROLE_ADMIN')) {
            if ($etablissement->getUser() !== $this->getUser()) {
                throw $this->createAccessDeniedException('Vous ne pouvez pas modifier cet établissement.');
            }
        }

        $etablissement->setModification(new \DateTime());

        $form = $this->createForm(EtablissementType::class, $etablissement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Établissement modifié avec succès.');
            
            if ($this->isGranted('ROLE_ADMIN')) {
                return $this->redirectToRoute('app_etablissement_index');
            } else {
                return $this->redirectToRoute('app_dashboard');
            }
        }

        return $this->render('etablissement/form.html.twig', [
            'etablissement' => $etablissement,
            'form' => $form
        ]);
    }

    #[Route('/{id}', name: 'app_etablissement_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, Etablissement $etablissement): Response
    {
        if ($this->isCsrfTokenValid('delete' . $etablissement->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($etablissement);
            $this->entityManager->flush();
            $this->addFlash('success', 'Établissement supprimé avec succès.');
        }

        return $this->redirectToRoute('app_etablissement_index');
    }

    #[Route('/{id}/show', name: 'app_etablissement_show', methods: ['GET'])]
    #[IsGranted('ROLE_ENT')]
    public function show(Etablissement $etablissement): Response
    {
        // Vérifier que l'établissement appartient bien à l'utilisateur connecté
        if ($etablissement->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas voir cet établissement.');
        }

        // Statistiques des candidatures pour cet établissement
        $stats = [
            'total' => 0,
            'aujourdhui' => 0,
            'cette_semaine' => 0,
            'ce_mois' => 0,
            'par_statut' => [
                'en_attente' => 0,
                'dossier_ok' => 0,
                'admissible' => 0,
                'admis' => 0,
                'rejete' => 0,
                'admis_definitifs' => 0
            ],
            'par_metier' => [],
            'par_metier_genre' => [],
            'recentes' => []
        ];

        $candidatures = $this->entityManager->getRepository(Candidature::class)
            ->createQueryBuilder('c')
            ->leftJoin('c.metier', 'm')
            ->leftJoin('c.user', 'u')
            ->addSelect('c', 'm', 'u')
            ->where('c.etablissement = :etablissement')
            ->setParameter('etablissement', $etablissement)
            ->orderBy('c.creation', 'DESC')
            ->getQuery()
            ->getResult();

        $aujourdhui = new \DateTime('today 00:00:00');
        $cetteSemaine = new \DateTime('monday this week 00:00:00');
        $ceMois = new \DateTime('first day of this month 00:00:00');

        foreach ($candidatures as $candidature) {
            $stats['total']++;
            
            // Périodes
            if ($candidature->getCreation() >= $aujourdhui) {
                $stats['aujourdhui']++;
            }
            if ($candidature->getCreation() >= $cetteSemaine) {
                $stats['cette_semaine']++;
            }
            if ($candidature->getCreation() >= $ceMois) {
                $stats['ce_mois']++;
            }

            // Statistiques par statut
            $etustatut = $candidature->getEtustatut();
            $resultat = $candidature->getResultat();
            
            // Priorité au résultat final
            if ($resultat === 2) {
                $stats['par_statut']['admis']++;
            } elseif ($resultat === 1) {
                $stats['par_statut']['rejete']++;
            } elseif ($etustatut === 2) {
                $stats['par_statut']['dossier_ok']++;
            } elseif ($etustatut === 1) {
                $stats['par_statut']['rejete']++;
            } else {
                $stats['par_statut']['en_attente']++;
            }

            // Statistiques par métier
            $metierNom = $candidature->getMetier() ? $candidature->getMetier()->getNom() : 'Non défini';
            if (!isset($stats['par_metier'][$metierNom])) {
                $stats['par_metier'][$metierNom] = 0;
                $stats['par_metier_genre'][$metierNom] = ['masculin' => 0, 'feminin' => 0];
            }
            $stats['par_metier'][$metierNom]++;

            // Statistiques par genre pour chaque métier
            if ($candidature->getUser()) {
                $genre = strtolower($candidature->getUser()->getSexe() ?? 'autre');
                if ($genre === 'masculin') {
                    $stats['par_metier_genre'][$metierNom]['masculin']++;
                } elseif ($genre === 'féminin' || $genre === 'feminin') {
                    $stats['par_metier_genre'][$metierNom]['feminin']++;
                }
            }

            // 5 plus récentes
            if (count($stats['recentes']) < 5) {
                $stats['recentes'][] = $candidature;
            }
        }

        // Trier par nombre de candidatures par métier
        arsort($stats['par_metier']);

        // Compter les admis définitifs
        $admisDefinitifsCount = $this->entityManager->getRepository(Admis::class)
            ->createQueryBuilder('a')
            ->leftJoin('a.candidature', 'c')
            ->where('c.etablissement = :etablissement')
            ->setParameter('etablissement', $etablissement)
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();
        
        $stats['par_statut']['admis_definitifs'] = $admisDefinitifsCount;

        // Récupérer les admis (candidatures avec resultat = 2)
        $admis = $this->entityManager->getRepository(Candidature::class)
            ->createQueryBuilder('c')
            ->leftJoin('c.metier', 'm')
            ->leftJoin('c.user', 'u')
            ->addSelect('c', 'm', 'u')
            ->where('c.etablissement = :etablissement')
            ->andWhere('c.resultat = 2')
            ->setParameter('etablissement', $etablissement)
            ->orderBy('c.modification', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        // Récupérer les admis définitifs
        $admisDefinitifs = $this->entityManager->getRepository(Admis::class)
            ->createQueryBuilder('a')
            ->leftJoin('a.metier', 'm')
            ->leftJoin('a.user', 'u')
            ->addSelect('a', 'm', 'u')
            ->where('a.etablissement = :etablissement')
            ->setParameter('etablissement', $etablissement)
            ->orderBy('a.dateInscription', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        return $this->render('etablissement/show.html.twig', [
            'etablissement' => $etablissement,
            'stats' => $stats,
            'admis' => $admis,
            'admisDefinitifs' => $admisDefinitifs
        ]);
    }
}
