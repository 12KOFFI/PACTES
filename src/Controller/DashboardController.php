<?php

namespace App\Controller;

use App\Config\AdmisAbandonsConfig;
use App\Config\PacteConfig;
use App\Service\SessionContext;
use App\Entity\Candidature;
use App\Entity\Metier;
use App\Entity\Etablissement;
use App\Entity\EtablissementMetier;
use App\Entity\User;
use App\Entity\Admis;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[Route('/dashboard')]
class DashboardController extends AbstractController
{
    private $entityManager;

    private SessionContext $sessionContext;

    public function __construct(EntityManagerInterface $entityManager, SessionContext $sessionContext)
    {
        $this->entityManager = $entityManager;
        $this->sessionContext = $sessionContext;
    }

    #[Route('/', name: 'app_dashboard')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function index(): Response
    {
        $user = $this->getUser();

        $roles = $user->getRoles();
        $isAdmin = in_array('ROLE_ADMIN', $roles);
        $isCandidat = in_array('ROLE_CANDIDAT', $roles);
        $isEnt = in_array('ROLE_ENT', $roles);
        $isJury = in_array('ROLE_JURY', $roles);

        $data = [
            'is_admin' => $isAdmin,
            'is_candidat' => $isCandidat,
            'is_ent' => $isEnt,
            'is_jury' => $isJury,
            'etablissement' => null,
        ];

        if ($isAdmin) {
            $data['stats'] = $this->getAdminStats();
            $data['dernieres_candidatures'] = $this->entityManager
                ->getRepository(Candidature::class)
                ->findBy([], ['id' => 'DESC'], 10);
            $data['derniers_inscrits'] = $this->entityManager
                ->getRepository(User::class)
                ->findBy([], ['id' => 'DESC'], 10);
            $qbDerniersAdmis = $this->entityManager
                ->getRepository(Admis::class)
                ->createQueryBuilder('a')
                ->orderBy('a.dateInscription', 'DESC')
                ->setMaxResults(5);
            AdmisAbandonsConfig::applyFilter($qbDerniersAdmis);
            $data['derniers_admis'] = $qbDerniersAdmis->getQuery()->getResult();
            $data['stats']['admis'] = $this->getStatsAdmis();
            $data['admis_par_etablissement'] = $this->getAdmisParEtablissement();
            $data['participation_feminine'] = $this->getParticipationFeminine(null);
        }
        elseif ($isCandidat) {
            $data['mes_candidatures'] = $this->entityManager
                ->getRepository(Candidature::class)
                ->findBy(['user' => $user], ['id' => 'DESC']);
        }
        elseif ($isEnt || $isJury) {
            $etablissement = $this->entityManager
                ->getRepository(Etablissement::class)
                ->findOneBy(['user' => $user]);

            $data['etablissement'] = $etablissement;

            if ($etablissement) {
                $data['stats'] = [
                    'candidatures' => (int) $this->entityManager
                        ->getRepository(Candidature::class)
                        ->count(['etablissement' => $etablissement]),
                    'candidatures_aujourdhui' => $this->getCandidaturesDuJourByEtablissement($etablissement),
                    'etablissements' => 1,
                    'metiers' => $this->entityManager
                        ->getRepository(EtablissementMetier::class)
                        ->count(['etablissement' => $etablissement]),
                    'utilisateurs' => $this->getNombreCandidatsByEtablissement($etablissement),
                    'utilisateurs_candidats' => $this->getNombreCandidatsByEtablissement($etablissement),
                ];

                $data['dernieres_candidatures'] = $this->entityManager
                    ->getRepository(Candidature::class)
                    ->findBy(['etablissement' => $etablissement], ['id' => 'DESC'], 10);
                $data['derniers_inscrits'] = $this->getDerniersCandidatsByEtablissement($etablissement, 10);
                $qbDerniersAdmisEnt = $this->entityManager
                    ->getRepository(Admis::class)
                    ->createQueryBuilder('a')
                    ->where('a.etablissement = :etab_admis')
                    ->setParameter('etab_admis', $etablissement)
                    ->orderBy('a.dateInscription', 'DESC')
                    ->setMaxResults(5);
                AdmisAbandonsConfig::applyFilter($qbDerniersAdmisEnt);
                $data['derniers_admis'] = $qbDerniersAdmisEnt->getQuery()->getResult();
                $data['stats']['admis'] = $this->getStatsAdmisByEtablissement($etablissement);
                $data['candidatures_a_evaluer'] = $data['dernieres_candidatures'];
                $data['participation_feminine'] = $this->getParticipationFeminine($etablissement);
            } else {
                $data['stats'] = [
                    'candidatures' => 0,
                    'candidatures_aujourdhui' => 0,
                    'etablissements' => 0,
                    'metiers' => 0,
                    'utilisateurs' => 0,
                    'utilisateurs_candidats' => 0
                ];
                $data['dernieres_candidatures'] = [];
                $data['derniers_inscrits'] = [];
                $data['derniers_admis'] = [];
                $data['candidatures_a_evaluer'] = [];

                $this->addFlash('warning', 'Votre compte n\'est pas associé à un établissement. Veuillez contacter l\'administrateur.');
            }
        }

        $data['session_consultee'] = $this->sessionContext->getCurrentSession();
        $data['objectif_feminin'] = PacteConfig::OBJECTIF_FEMININ_POURCENT;

        return $this->render('dashboard.html.twig', $data);
    }

    private function getCandidaturesDuJourByEtablissement(Etablissement $etablissement): int
    {
        $today = new \DateTime('today');
        $tomorrow = new \DateTime('tomorrow');

        return $this->entityManager
            ->getRepository(Candidature::class)
            ->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.etablissement = :etablissement')
            ->andWhere('c.creation >= :today')
            ->andWhere('c.creation < :tomorrow')
            ->setParameter('etablissement', $etablissement)
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function getNombreCandidatsByEtablissement(Etablissement $etablissement): int
    {
        return $this->entityManager
            ->getRepository(Candidature::class)
            ->createQueryBuilder('c')
            ->select('COUNT(DISTINCT c.user)')
            ->where('c.etablissement = :etablissement')
            ->setParameter('etablissement', $etablissement)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function getDerniersCandidatsByEtablissement(Etablissement $etablissement, int $limit = 10): array
    {
        $candidatures = $this->entityManager
            ->getRepository(Candidature::class)
            ->createQueryBuilder('c')
            ->leftJoin('c.user', 'u')
            ->addSelect('u')
            ->where('c.etablissement = :etablissement')
            ->setParameter('etablissement', $etablissement)
            ->orderBy('c.id', 'DESC')
            ->setMaxResults($limit * 2)
            ->getQuery()
            ->getResult();

        $users = [];
        $seenUserIds = [];

        foreach ($candidatures as $candidature) {
            $user = $candidature->getUser();
            if ($user && !in_array($user->getId(), $seenUserIds)) {
                $users[] = $user;
                $seenUserIds[] = $user->getId();
            }

            if (count($users) >= $limit) {
                break;
            }
        }

        return $users;
    }

    private function getAdminStats(): array
    {
        $candidatureRepo = $this->entityManager->getRepository(Candidature::class);

        $today = new \DateTime('today');
        $tomorrow = new \DateTime('tomorrow');

        $candidaturesAujourdhui = $candidatureRepo->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.creation >= :today')
            ->andWhere('c.creation < :tomorrow')
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'candidatures' => $candidatureRepo->count([]),
            'candidatures_aujourdhui' => $candidaturesAujourdhui,
            'etablissements' => $this->entityManager->getRepository(Etablissement::class)->count([]),
            'metiers' => $this->entityManager->getRepository(Metier::class)->count([]),
            'utilisateurs' => $this->entityManager->getRepository(User::class)->count([]),
            'utilisateurs_candidats' => $this->entityManager->getRepository(User::class)
                ->createQueryBuilder('u')
                ->select('count(u.id)')
                ->where('u.roles LIKE :role')
                ->setParameter('role', '%"ROLE_CANDIDAT"%')
                ->getQuery()
                ->getSingleScalarResult(),
        ];
    }

    private function getStatsAdmis(): array
    {
        $candidaturesAdmises = $this->entityManager
            ->getRepository(Candidature::class)
            ->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.resultat = 2')
            ->getQuery()
            ->getSingleScalarResult();

        $qbAdmissions = $this->entityManager
            ->getRepository(Admis::class)
            ->createQueryBuilder('a')
            ->select('COUNT(a.id)');
        AdmisAbandonsConfig::applyFilter($qbAdmissions);
        $admissionsDefinitives = $qbAdmissions->getQuery()->getSingleScalarResult();

        return [
            'admis' => $candidaturesAdmises,
            'admis_definitifs' => $admissionsDefinitives
        ];
    }

    private function getStatsAdmisByEtablissement(Etablissement $etablissement): array
    {
        $candidaturesAdmises = $this->entityManager
            ->getRepository(Candidature::class)
            ->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.resultat = 2')
            ->andWhere('c.etablissement = :etablissement')
            ->setParameter('etablissement', $etablissement)
            ->getQuery()
            ->getSingleScalarResult();

        $qbAdmissionsEtab = $this->entityManager
            ->getRepository(Admis::class)
            ->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.etablissement = :etablissement')
            ->setParameter('etablissement', $etablissement);
        AdmisAbandonsConfig::applyFilter($qbAdmissionsEtab);
        $admissionsDefinitives = $qbAdmissionsEtab->getQuery()->getSingleScalarResult();

        return [
            'admis' => $candidaturesAdmises,
            'admis_definitifs' => $admissionsDefinitives
        ];
    }
    /**
     * Admis (résultat final = 2) par établissement pour la session consultée.
     * Information seule : aucune comparaison avec le nombre de places.
     */
    private function getAdmisParEtablissement(): array
    {
        return $this->entityManager->createQuery(
            'SELECT e.nom AS etablissement, COUNT(c.id) AS admis
             FROM App\Entity\Candidature c JOIN c.etablissement e
             WHERE c.resultat = 2
             GROUP BY e.id, e.nom
             ORDER BY e.nom ASC'
        )->getArrayResult();
    }

    /**
     * Indicateur statistique (jamais bloquant) : part de candidates sur quatre étapes distinctes,
     * pour la session consultée (filtre de session actif).
     */
    private function getParticipationFeminine(?Etablissement $etablissement): array
    {
        $etapes = [
            ['libelle' => 'Candidatures', 'condition' => null],
            ['libelle' => 'Admissibles à l\'entretien (entstatut = 2)', 'condition' => 'c.entstatut = 2'],
            ['libelle' => 'Retenus par le jury (decisionJury = 2)', 'condition' => 'c.decisionJury = 2'],
            ['libelle' => 'Admis (résultat final = 2)', 'condition' => 'c.resultat = 2'],
        ];

        $resultat = [];
        foreach ($etapes as $etape) {
            $qb = $this->entityManager->getRepository(Candidature::class)->createQueryBuilder('c')
                ->select('COUNT(c.id) AS total', "SUM(CASE WHEN u.sexe = 'FEMININ' THEN 1 ELSE 0 END) AS feminin")
                ->join('c.user', 'u');
            if ($etape['condition']) {
                $qb->andWhere($etape['condition']);
            }
            if ($etablissement) {
                $qb->andWhere('c.etablissement = :etab')->setParameter('etab', $etablissement);
            }
            $row = $qb->getQuery()->getSingleResult();
            $total = (int) $row['total'];
            $feminin = (int) $row['feminin'];
            $resultat[] = [
                'libelle' => $etape['libelle'],
                'total' => $total,
                'feminin' => $feminin,
                'pourcentage' => $total > 0 ? round($feminin * 100 / $total, 1) : null,
            ];
        }

        return $resultat;
    }
}