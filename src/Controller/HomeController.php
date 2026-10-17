<?php

namespace App\Controller;

use App\Config\EtablissementsActifsConfig;
use App\Entity\Metier;
use App\Entity\Etablissement;
use App\Entity\EtablissementMetier;
use App\Entity\Candidature;
use App\Entity\SessionRecrutement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{

    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/', name: 'app_home')]
    public function index(Request $request): Response
    {
        // Paramètres pour les métiers
        $pageMetier = $request->query->getInt('page-metier', 1);
        $limitMetier = 6;
        $rechercheMetier = $request->query->get('recherche-metier', '');

        // Paramètres pour les établissements
        $pageEtablissement = $request->query->getInt('page-etablissement', 1);
        $limitEtablissement = 6;
        $rechercheEtablissement = $request->query->get('recherche-etablissement', '');

        // ========== REQUÊTE POUR LES MÉTIERS ==========
        $metierQuery = $this->entityManager->getRepository(Metier::class)
            ->createQueryBuilder('m')
            ->innerJoin('m.etablissementMetiers', 'em')
            ->innerJoin('em.etablissement', 'e')
            ->addSelect('em', 'e')
            ->andWhere('em.estActif = true')
            ->andWhere(EtablissementsActifsConfig::getDqlWhereClause('e'));

        if (!empty($rechercheMetier)) {
            $metierQuery->andWhere('m.nom LIKE :recherche')
                ->setParameter('recherche', '%' . $rechercheMetier . '%');
        }

        // Compter le total des métiers
        $totalMetiers = (clone $metierQuery)
            ->select('COUNT(DISTINCT m.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Récupérer les métiers paginés
        $metiers = $metierQuery
            ->select('m', 'em', 'e')
            ->setFirstResult(($pageMetier - 1) * $limitMetier)
            ->setMaxResults($limitMetier)
            ->orderBy('m.nom', 'ASC')
            ->getQuery()
            ->getResult();

        // ========== REQUÊTE POUR LES ÉTABLISSEMENTS ==========
        $countQuery = $this->entityManager->getRepository(Etablissement::class)
            ->createQueryBuilder('e')
            ->select('COUNT(DISTINCT e.id)')
            ->where(EtablissementsActifsConfig::getDqlWhereClause('e'));

        if (!empty($rechercheEtablissement)) {
            $countQuery->andWhere('e.nom LIKE :recherche')
                ->setParameter('recherche', '%' . $rechercheEtablissement . '%');
        }
        $totalEtablissements = $countQuery->getQuery()->getSingleScalarResult();

        $idQuery = $this->entityManager->getRepository(Etablissement::class)
            ->createQueryBuilder('e')
            ->select('e.id')
            ->addSelect(EtablissementsActifsConfig::getSortPriorityCaseWhen('e'))
            ->where(EtablissementsActifsConfig::getDqlWhereClause('e'))
            ->orderBy('sort_priority', 'ASC')
            ->addOrderBy('e.nom', 'ASC');

        if (!empty($rechercheEtablissement)) {
            $idQuery->andWhere('e.nom LIKE :recherche')
                ->setParameter('recherche', '%' . $rechercheEtablissement . '%');
        }

        $ids = $idQuery->setFirstResult(($pageEtablissement - 1) * $limitEtablissement)
            ->setMaxResults($limitEtablissement)
            ->getQuery()
            ->getResult();
        $etablissementIds = array_column($ids, 'id');

        // Récupérer les établissements complets paginés
        $etablissements = [];
        if (!empty($etablissementIds)) {
            $etablissements = $this->entityManager->getRepository(Etablissement::class)
                ->createQueryBuilder('e')
                ->leftJoin('e.etablissementMetiers', 'em', 'WITH', 'em.estActif = true')
                ->leftJoin('em.metier', 'm')
                ->addSelect('em', 'm')
                ->where('e.id IN (:ids)')
                ->setParameter('ids', $etablissementIds)
                ->getQuery()
                ->getResult();

            // Re-trier selon l'ordre des IDs
            usort($etablissements, function ($a, $b) use ($etablissementIds) {
                return array_search($a->getId(), $etablissementIds) - array_search($b->getId(), $etablissementIds);
            });
        }

        $sessionOuverte = $this->entityManager->getRepository(SessionRecrutement::class)->findActive();
        $admissionsDisponibles = false;

        // Statistiques globales
        $stats = [
            'metiers' => $this->entityManager->getRepository(EtablissementMetier::class)
                ->createQueryBuilder('em')
                ->select('COUNT(DISTINCT em.metier)')
                ->where('em.estActif = true')
                ->getQuery()
                ->getSingleScalarResult(),
            'etablissements' => $totalEtablissements,
            'places' => $this->getTotalPlaces(),
            'candidatures' => $sessionOuverte ? $this->entityManager->getRepository(Candidature::class)->count(['session' => $sessionOuverte]) : 0
        ];

        $partenaires = $this->getPartenaires();

        return $this->render('index.html.twig', [
            // Données pour les métiers
            'metiers' => $this->buildMetierCardData($metiers),
            'totalMetiers' => $totalMetiers,
            'limitMetier' => $limitMetier,
            'pageMetierActuelle' => $pageMetier,
            'rechercheMetier' => $rechercheMetier,

            // Données pour les établissements
            'etablissements' => $etablissements,
            'totalEtablissements' => $totalEtablissements,
            'limitEtablissement' => $limitEtablissement,
            'pageEtablissementActuelle' => $pageEtablissement,
            'rechercheEtablissement' => $rechercheEtablissement,

            // Statistiques globales
            'stats' => $stats,
            'partenaires' => $partenaires,

            'admissions_disponibles' => $admissionsDisponibles,
        ]);
    }

    private function getTotalPlaces(): int
    {
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT SUM(nbrplace) as total FROM etablissement_metier WHERE est_actif = 1';
        $result = $conn->executeQuery($sql)->fetchAssociative();

        return (int) ($result['total'] ?? 0);
    }

    /**
     * Transforme une liste d'entités Metier (dont la collection etablissementMetiers
     * est déjà filtrée aux lignes actives) en tableaux prêts pour l'affichage carte :
     * - niveauRequis / dureeFormation : première valeur active trouvée
     * - nbrplace : somme des places sur tous les établissements actifs
     * - nbEtablissements : nombre d'établissements distincts proposant ce métier
     *
     * @param iterable<Metier> $metiers
     */
    private function buildMetierCardData(iterable $metiers): array
    {
        $result = [];

        foreach ($metiers as $metier) {
            $etablissementMetiers = $metier->getEtablissementMetiers();

            $totalPlaces = 0;
            $niveauRequis = null;
            $dureeFormation = null;

            foreach ($etablissementMetiers as $em) {
                $totalPlaces += $em->getNbrplace() ?? 0;

                if ($niveauRequis === null && $em->getNiveauRequis()) {
                    $niveauRequis = $em->getNiveauRequis();
                }
                if ($dureeFormation === null && $em->getDureeFormation()) {
                    $dureeFormation = $em->getDureeFormation();
                }
            }

            $result[] = [
                'id' => $metier->getId(),
                'nom' => $metier->getNom(),
                'niveauRequis' => $niveauRequis,
                'dureeFormation' => $dureeFormation,
                'nbrplace' => $totalPlaces,
                'nbEtablissements' => count($etablissementMetiers),
            ];
        }

        return $result;
    }

    #[Route('/load-metiers', name: 'app_load_metiers', methods: ['GET'])]
    public function loadMetiers(Request $request): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 6);
        $recherche = $request->query->get('recherche', '');

        $query = $this->entityManager->getRepository(Metier::class)
            ->createQueryBuilder('m')
            ->innerJoin('m.etablissementMetiers', 'em')
            ->innerJoin('em.etablissement', 'e')
            ->addSelect('em', 'e')
            ->andWhere('em.estActif = true')
            ->andWhere(EtablissementsActifsConfig::getDqlWhereClause('e'));

        if (!empty($recherche)) {
            $query->andWhere('m.nom LIKE :recherche')
                ->setParameter('recherche', '%' . $recherche . '%');
        }

        // Compter le total
        $total = (clone $query)
            ->select('COUNT(DISTINCT m.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Récupérer les IDs paginés
        $idQuery = clone $query;
        $ids = array_column(
            $idQuery->select('m.id')
                ->setFirstResult(($page - 1) * $limit)
                ->setMaxResults($limit)
                ->orderBy('m.nom', 'ASC')
                ->getQuery()
                ->getResult(),
            'id'
        );

        // Charger les entités complètes uniquement pour ces IDs
        $metiers = [];
        if (!empty($ids)) {
            $metiers = $this->entityManager->getRepository(Metier::class)
                ->createQueryBuilder('m')
                ->innerJoin('m.etablissementMetiers', 'em')
                ->innerJoin('em.etablissement', 'e')
                ->addSelect('em', 'e')
                ->andWhere('em.estActif = true')
                ->where('m.id IN (:ids)')
                ->setParameter('ids', $ids)
                ->orderBy('m.nom', 'ASC')
                ->getQuery()
                ->getResult();
        }

        return $this->json([
            'html' => $this->renderView('partials/_metiers_cards.html.twig', [
                'metiers' => $this->buildMetierCardData($metiers),
            ]),
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ]);
    }

    #[Route('/load-etablissements', name: 'app_load_etablissements', methods: ['GET'])]
    public function loadEtablissements(Request $request): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 6);
        $recherche = $request->query->get('recherche', '');

        $countQuery = $this->entityManager->getRepository(Etablissement::class)
            ->createQueryBuilder('e')
            ->select('COUNT(DISTINCT e.id)')
            ->where(EtablissementsActifsConfig::getDqlWhereClause('e'));

        if (!empty($recherche)) {
            $countQuery->andWhere('e.nom LIKE :recherche')
                ->setParameter('recherche', '%' . $recherche . '%');
        }
        $total = $countQuery->getQuery()->getSingleScalarResult();

        $idQuery = $this->entityManager->getRepository(Etablissement::class)
            ->createQueryBuilder('e')
            ->select('e.id')
            ->addSelect(EtablissementsActifsConfig::getSortPriorityCaseWhen('e'))
            ->where(EtablissementsActifsConfig::getDqlWhereClause('e'))
            ->orderBy('sort_priority', 'ASC')
            ->addOrderBy('e.nom', 'ASC');

        if (!empty($recherche)) {
            $idQuery->andWhere('e.nom LIKE :recherche')
                ->setParameter('recherche', '%' . $recherche . '%');
        }

        $ids = $idQuery->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
        $etablissementIds = array_column($ids, 'id');

        $etablissements = [];
        if (!empty($etablissementIds)) {
            $etablissements = $this->entityManager->getRepository(Etablissement::class)
                ->createQueryBuilder('e')
                ->leftJoin('e.etablissementMetiers', 'em')
                ->leftJoin('em.metier', 'm')
                ->addSelect('em', 'm')
                ->where('e.id IN (:ids)')
                ->setParameter('ids', $etablissementIds)
                ->getQuery()
                ->getResult();

            usort($etablissements, function ($a, $b) use ($etablissementIds) {
                return array_search($a->getId(), $etablissementIds) - array_search($b->getId(), $etablissementIds);
            });
        }

        return $this->json([
            'html' => $this->renderView('partials/_etablissements_cards.html.twig', [
                'etablissements' => $etablissements,
            ]),
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ]);
    }

    private function getPartenaires(): array
    {
        return [
            [
                'nom' => 'Projet PACTE',
                'signification' => 'Programme d’Action pour les Compétences en Transition Énergétique',
                'image' => '/image/pacte.png',
                'lien' => '#'
            ]
        ];
    }

    #[Route('/etablissements', name: 'app_etablissements_list')]
    public function etablissementsList(Request $request): Response
    {
        $recherche = $request->query->get('recherche', '');

        $idQuery = $this->entityManager->getRepository(Etablissement::class)
            ->createQueryBuilder('e')
            ->select('e.id')
            ->addSelect(EtablissementsActifsConfig::getSortPriorityCaseWhen('e'))
            ->where(EtablissementsActifsConfig::getDqlWhereClause('e'))
            ->orderBy('sort_priority', 'ASC')
            ->addOrderBy('e.nom', 'ASC');

        if (!empty($recherche)) {
            $idQuery->andWhere('e.nom LIKE :recherche')
                ->setParameter('recherche', '%' . $recherche . '%');
        }

        $ids = array_column($idQuery->getQuery()->getResult(), 'id');

        $etablissements = [];
        if (!empty($ids)) {
            $etablissements = $this->entityManager->getRepository(Etablissement::class)
                ->createQueryBuilder('e')
                ->leftJoin('e.etablissementMetiers', 'em')
                ->leftJoin('em.metier', 'm')
                ->addSelect('em', 'm')
                ->where('e.id IN (:ids)')
                ->setParameter('ids', $ids)
                ->orderBy('e.nom', 'ASC')
                ->getQuery()
                ->getResult();
        }

        return $this->render('etablissement/list.html.twig', [
            'etablissements' => $etablissements,
            'total' => count($ids),
            'recherche' => $recherche,
        ]);
    }

    #[Route('/metiers', name: 'app_metiers_list')]
    public function metiersList(Request $request): Response
    {
        $recherche = $request->query->get('recherche', '');

        // Step 1: get IDs only (no collection joins) to avoid Cartesian product in memory
        $idQuery = $this->entityManager->getRepository(Metier::class)
            ->createQueryBuilder('m')
            ->select('m.id')
            ->leftJoin('m.etablissementMetiers', 'em')
            ->leftJoin('em.etablissement', 'e')
            ->andWhere(EtablissementsActifsConfig::getDqlWhereClause('e'))
            ->orderBy('m.nom', 'ASC');

        if (!empty($recherche)) {
            $idQuery->andWhere('m.nom LIKE :recherche')
                ->setParameter('recherche', '%' . $recherche . '%');
        }

        $ids = array_column($idQuery->getQuery()->getResult(), 'id');

        // Step 2: load full entities with joins only for the matched IDs
        $metiers = [];
        if (!empty($ids)) {
            $metiers = $this->entityManager->getRepository(Metier::class)
                ->createQueryBuilder('m')
                ->leftJoin('m.etablissementMetiers', 'em')
                ->leftJoin('em.etablissement', 'e')
                ->addSelect('em', 'e')
                ->where('m.id IN (:ids)')
                ->setParameter('ids', $ids)
                ->orderBy('m.nom', 'ASC')
                ->getQuery()
                ->getResult();
        }

        return $this->render('metier/list.html.twig', [
            'metiers' => $this->buildMetierCardData($metiers),
            'total' => count($ids),
            'recherche' => $recherche,
        ]);
    }
}
