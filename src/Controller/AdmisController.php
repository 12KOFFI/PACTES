<?php

namespace App\Controller;

use App\Config\AdmisAbandonsConfig;
use App\Entity\Admis;
use App\Entity\Candidature;
use App\Entity\Etablissement;
use App\Entity\User;
use App\Entity\Metier;
use App\Form\AdmisType;
use App\Service\SpreadsheetGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
#[Route('/admis')]
class AdmisController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SpreadsheetGenerator $spreadsheetGenerator,
        private ParameterBagInterface $params
    ) {}

    #[Route('/etablissement/admis', name: 'app_admis_liste', methods: ['GET'])]
    #[IsGranted('ROLE_ENT')]
    public function listeAdmis(Request $request, \Knp\Component\Pager\PaginatorInterface $paginator): Response
    {
        $user = $this->getUser();
        $etablissement = $this->entityManager->getRepository(Etablissement::class)
            ->findOneBy(['user' => $user]);

        if (!$etablissement) {
            $this->addFlash('error', 'Aucun établissement associé à votre compte.');
            return $this->redirectToRoute('app_dashboard');
        }

        $qb = $this->entityManager->getRepository(Candidature::class)
            ->createQueryBuilder('c')
            ->innerJoin('c.user', 'u')
            ->innerJoin('c.metier', 'm')
            ->innerJoin('c.etablissement', 'e')
            ->leftJoin('c.admis', 'a')
            ->addSelect('u', 'm', 'e', 'a')
            ->where('c.resultat = :resultat')
            ->andWhere('c.etablissement = :etablissement')
            ->setParameter('resultat', 2)
            ->setParameter('etablissement', $etablissement);

        $countQb = clone $qb;
        $countQbFiltered = $countQb->select('COUNT(c.id)')
            ->andWhere('a.id IS NOT NULL');
        AdmisAbandonsConfig::applyFilter($countQbFiltered);
        $totalInscritsDefinitifs = (int) $countQbFiltered->getQuery()->getSingleScalarResult();

        $statsGenreQb = $this->entityManager->getRepository(Candidature::class)
            ->createQueryBuilder('c')
            ->innerJoin('c.user', 'u')
            ->select(
                'SUM(CASE WHEN u.sexe = :homme OR u.sexe = :homme_m THEN 1 ELSE 0 END) as total_hommes',
                'SUM(CASE WHEN u.sexe = :femme OR u.sexe = :femme_f THEN 1 ELSE 0 END) as total_femmes'
            )
            ->where('c.resultat = :resultat')
            ->andWhere('c.etablissement = :etablissement')
            ->setParameter('resultat', 2)
            ->setParameter('etablissement', $etablissement)
            ->setParameter('homme', 'MASCULIN')
            ->setParameter('homme_m', 'M')
            ->setParameter('femme', 'FEMININ')
            ->setParameter('femme_f', 'F');

        $searchTerm = $request->query->get('q');
        if ($searchTerm) {
            $qb->andWhere('c.numero LIKE :search OR u.nom LIKE :search OR u.prenoms LIKE :search OR m.nom LIKE :search OR e.nom LIKE :search')
               ->setParameter('search', '%' . $searchTerm . '%');
            $statsGenreQb->leftJoin('c.metier', 'm')
                ->leftJoin('c.etablissement', 'e')
                ->andWhere('c.numero LIKE :search OR u.nom LIKE :search OR u.prenoms LIKE :search OR m.nom LIKE :search OR e.nom LIKE :search')
                ->setParameter('search', '%' . $searchTerm . '%');
        }

        $statsGenre = $statsGenreQb->getQuery()->getOneOrNullResult();
        $totalHommes = (int) ($statsGenre['total_hommes'] ?? 0);
        $totalFemmes = (int) ($statsGenre['total_femmes'] ?? 0);

        $qb->orderBy('c.modification', 'DESC');
        $limit = $request->query->getInt('limit', 25);
        
        $candidaturesAdmises = $paginator->paginate($qb, $request->query->getInt('page', 1), $limit);

        return $this->render('admis/liste_admis.html.twig', [
            'candidatures' => $candidaturesAdmises,
            'etablissement' => $etablissement,
            'total_inscrits_definitifs' => $totalInscritsDefinitifs,
            'total_hommes' => $totalHommes,
            'total_femmes' => $totalFemmes,
            'limit' => $limit,
            'searchTerm' => $searchTerm
        ]);
    }

    #[Route('/admin/admis', name: 'app_admis_admin_liste', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function listeAdmisAdmin(Request $request, \Knp\Component\Pager\PaginatorInterface $paginator): Response
    {
        $qb = $this->entityManager->getRepository(Candidature::class)
            ->createQueryBuilder('c')
            ->innerJoin('c.user', 'u')
            ->innerJoin('c.metier', 'm')
            ->innerJoin('c.etablissement', 'e')
            ->leftJoin('c.admis', 'a')
            ->addSelect('u', 'm', 'e', 'a')
            ->where('c.resultat = :resultat')
            ->setParameter('resultat', 2);

        $countQb = clone $qb;
        $countQbFiltered = $countQb->select('COUNT(c.id)')
            ->andWhere('a.id IS NOT NULL');
        AdmisAbandonsConfig::applyFilter($countQbFiltered);
        $totalInscritsDefinitifs = (int) $countQbFiltered->getQuery()->getSingleScalarResult();

        $statsGenreQb = $this->entityManager->getRepository(Candidature::class)
            ->createQueryBuilder('c')
            ->innerJoin('c.user', 'u')
            ->select(
                'SUM(CASE WHEN u.sexe = :homme OR u.sexe = :homme_m THEN 1 ELSE 0 END) as total_hommes',
                'SUM(CASE WHEN u.sexe = :femme OR u.sexe = :femme_f THEN 1 ELSE 0 END) as total_femmes'
            )
            ->where('c.resultat = :resultat')
            ->setParameter('resultat', 2)
            ->setParameter('homme', 'MASCULIN')
            ->setParameter('homme_m', 'M')
            ->setParameter('femme', 'FEMININ')
            ->setParameter('femme_f', 'F');

        $searchTerm = $request->query->get('q');
        if ($searchTerm) {
            $qb->andWhere('c.numero LIKE :search OR u.nom LIKE :search OR u.prenoms LIKE :search OR m.nom LIKE :search OR e.nom LIKE :search')
               ->setParameter('search', '%' . $searchTerm . '%');
            $statsGenreQb->leftJoin('c.metier', 'm')
                ->leftJoin('c.etablissement', 'e')
                ->andWhere('c.numero LIKE :search OR u.nom LIKE :search OR u.prenoms LIKE :search OR m.nom LIKE :search OR e.nom LIKE :search')
                ->setParameter('search', '%' . $searchTerm . '%');
        }

        $statsGenre = $statsGenreQb->getQuery()->getOneOrNullResult();
        $totalHommes = (int) ($statsGenre['total_hommes'] ?? 0);
        $totalFemmes = (int) ($statsGenre['total_femmes'] ?? 0);

        $qb->orderBy('c.modification', 'DESC');
        $limit = $request->query->getInt('limit', 25);

        $candidaturesAdmises = $paginator->paginate($qb, $request->query->getInt('page', 1), $limit);

        return $this->render('admis/liste_admis.html.twig', [
            'candidatures' => $candidaturesAdmises,
            'etablissement' => null,
            'is_admin' => true,
            'total_inscrits_definitifs' => $totalInscritsDefinitifs,
            'total_hommes' => $totalHommes,
            'total_femmes' => $totalFemmes,
            'limit' => $limit,
            'searchTerm' => $searchTerm
        ]);
    }

    #[Route('/', name: 'app_admis_index', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function index(Request $request, \Knp\Component\Pager\PaginatorInterface $paginator): Response
    {
        $qb = $this->entityManager->getRepository(Admis::class)
            ->createQueryBuilder('a')
            ->innerJoin('a.user', 'u')
            ->innerJoin('a.etablissement', 'e')
            ->innerJoin('a.metier', 'm')
            ->leftJoin('a.candidature', 'c')
            ->addSelect('u', 'e', 'm', 'c');
        AdmisAbandonsConfig::applyFilter($qb);

        // Récupération des filtres depuis l'URL
        $etablissementId = $request->query->get('etablissement');
        $metierId = $request->query->get('metier');
        $contratFiltre = $request->query->get('contrat'); // 'oui' ou 'non'

        $etablissementFiltre = null;
        $metierFiltre = null;

        if ($etablissementId) {
            $qb->andWhere('e.id = :etablissementId')
               ->setParameter('etablissementId', $etablissementId);
            $etablissementFiltre = $this->entityManager->getRepository(Etablissement::class)->find($etablissementId);
        }

        if ($metierId) {
            $qb->andWhere('m.id = :metierId')
               ->setParameter('metierId', $metierId);
            $metierFiltre = $this->entityManager->getRepository(Metier::class)->find($metierId);
        }

        if ($contratFiltre === 'oui') {
            $qb->andWhere('a.fichierContrat IS NOT NULL');
        } elseif ($contratFiltre === 'non') {
            $qb->andWhere('a.fichierContrat IS NULL');
        }

        $searchTerm = $request->query->get('q');
        if ($searchTerm) {
            $qb->andWhere('u.nom LIKE :search OR u.prenoms LIKE :search OR CONCAT(u.nom, CONCAT(\' \', u.prenoms)) LIKE :search OR CONCAT(u.prenoms, CONCAT(\' \', u.nom)) LIKE :search OR u.email LIKE :search OR m.nom LIKE :search OR e.nom LIKE :search OR a.numeroInscription LIKE :search OR c.numero LIKE :search')
               ->setParameter('search', '%' . $searchTerm . '%');
        }

        $countQb = clone $qb;
        $statsGenre = (clone $countQb)
            ->select(
                'SUM(CASE WHEN u.sexe = :homme OR u.sexe = :homme_m THEN 1 ELSE 0 END) as total_hommes',
                'SUM(CASE WHEN u.sexe = :femme OR u.sexe = :femme_f THEN 1 ELSE 0 END) as total_femmes'
            )
            ->setParameter('homme', 'MASCULIN')
            ->setParameter('homme_m', 'M')
            ->setParameter('femme', 'FEMININ')
            ->setParameter('femme_f', 'F')
            ->getQuery()
            ->getOneOrNullResult();
        $totalHommes = (int) ($statsGenre['total_hommes'] ?? 0);
        $totalFemmes = (int) ($statsGenre['total_femmes'] ?? 0);

        // Calcul des admis provisoires non encore inscrits définitivement
        $qbNonInscrits = $this->entityManager->getRepository(Candidature::class)
            ->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->innerJoin('c.user', 'u')
            ->where('c.resultat = :resultat')
            ->andWhere('c.id NOT IN (
                SELECT IDENTITY(a2.candidature)
                FROM App\Entity\Admis a2
                WHERE a2.candidature IS NOT NULL
            )')
            ->setParameter('resultat', 2);

        if ($etablissementId) {
            $qbNonInscrits->andWhere('c.etablissement = :etabId')
                ->setParameter('etabId', $etablissementId);
        }
        if ($metierId) {
            $qbNonInscrits->andWhere('c.metier = :metierId')
                ->setParameter('metierId', $metierId);
        }
        if ($searchTerm) {
            $qbNonInscrits->leftJoin('c.metier', 'm')
                ->leftJoin('c.etablissement', 'e')
                ->andWhere('u.nom LIKE :search OR u.prenoms LIKE :search OR CONCAT(u.nom, CONCAT(\' \', u.prenoms)) LIKE :search OR CONCAT(u.prenoms, CONCAT(\' \', u.nom)) LIKE :search OR u.email LIKE :search OR m.nom LIKE :search OR e.nom LIKE :search OR c.numero LIKE :search')
                ->setParameter('search', '%' . $searchTerm . '%');
        }
        $totalAdmisNonInscrits = (int) $qbNonInscrits->getQuery()->getSingleScalarResult();

        // Calcul des abandons signalés
        $abandonsIds = AdmisAbandonsConfig::getIds();
        $totalAbandons = 0;
        if (!empty($abandonsIds)) {
            $qbAbandons = $this->entityManager->getRepository(Admis::class)
                ->createQueryBuilder('a')
                ->select('COUNT(a.id)')
                ->innerJoin('a.user', 'u')
                ->innerJoin('a.etablissement', 'e')
                ->innerJoin('a.metier', 'm')
                ->where('a.id IN (:abandons_ids)')
                ->setParameter('abandons_ids', $abandonsIds);

            if ($etablissementId) {
                $qbAbandons->andWhere('e.id = :etablissementId')
                    ->setParameter('etablissementId', $etablissementId);
            }
            if ($metierId) {
                $qbAbandons->andWhere('m.id = :metierId')
                    ->setParameter('metierId', $metierId);
            }
            if ($searchTerm) {
                $qbAbandons->andWhere('u.nom LIKE :search OR u.prenoms LIKE :search OR CONCAT(u.nom, CONCAT(\' \', u.prenoms)) LIKE :search OR CONCAT(u.prenoms, CONCAT(\' \', u.nom)) LIKE :search OR u.email LIKE :search OR m.nom LIKE :search OR e.nom LIKE :search OR a.numeroInscription LIKE :search')
                    ->setParameter('search', '%' . $searchTerm . '%');
            }
            $totalAbandons = (int) $qbAbandons->getQuery()->getSingleScalarResult();
        }

        $qb->orderBy('a.dateInscription', 'DESC');

        $limit = $request->query->getInt('limit', 25);
        $admis = $paginator->paginate($qb, $request->query->getInt('page', 1), $limit);

        // Récupérer les listes pour les select
        $etablissements = $this->entityManager->getRepository(Etablissement::class)->findBy([], ['nom' => 'ASC']);
        $metiers = $this->entityManager->getRepository(Metier::class)->findBy([], ['nom' => 'ASC']);

        return $this->render('admis/index.html.twig', [
            'admis' => $admis,
            'etablissements' => $etablissements,
            'metiers' => $metiers,
            'etablissement_filtre' => $etablissementFiltre,
            'metier_filtre' => $metierFiltre,
            'contrat_filtre' => $contratFiltre,
            'total_hommes' => $totalHommes,
            'total_femmes' => $totalFemmes,
            'total_admis_non_inscrits' => $totalAdmisNonInscrits,
            'total_abandons' => $totalAbandons,
            'limit' => $limit,
            'searchTerm' => $searchTerm
        ]);
    }

    /**
     * Upload de fiche d'engagement par chunks AJAX (contourne la limite LimitRequestBody d'Apache)
     */
    #[Route('/upload-fiche-chunk', name: 'app_admis_upload_chunk', methods: ['POST'])]
    #[IsGranted('ROLE_ENT')]
    public function uploadFicheChunk(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $etablissement = $this->entityManager->getRepository(Etablissement::class)
            ->findOneBy(['user' => $user]);

        if (!$etablissement) {
            return $this->json(['error' => 'Établissement non trouvé'], 403);
        }

        // Lire les données depuis les paramètres POST (x-www-form-urlencoded)
        $candidatureId = $request->request->get('candidature_id');
        $chunkData = $request->request->get('chunk');
        $chunkIndex = (int) $request->request->get('chunk_index', 0);
        $totalChunks = (int) $request->request->get('total_chunks', 1);
        $fileName = $request->request->get('file_name', 'fichier.pdf');
        $tempId = $request->request->get('temp_id', uniqid());

        if (!$candidatureId || !$chunkData) {
            return $this->json(['error' => 'Paramètres manquants'], 400);
        }

        // Vérifier la candidature
        $candidature = $this->entityManager->getRepository(Candidature::class)->find($candidatureId);
        if (!$candidature || $candidature->getEtablissement() !== $etablissement) {
            return $this->json(['error' => 'Candidature non valide'], 403);
        }

        // Décoder le chunk base64
        $decoded = base64_decode($chunkData);
        if ($decoded === false) {
            return $this->json(['error' => 'Données base64 invalides'], 400);
        }

        // Écrire le chunk dans un fichier temporaire
        $dirMedia = $this->params->get('dir_media');
        $candidatureDir = $dirMedia . $candidature->getNumero() . '/';
        if (!is_dir($candidatureDir)) {
            mkdir($candidatureDir, 0777, true);
        }

        $tempFile = $candidatureDir . 'temp_upload_' . $tempId;
        $mode = ($chunkIndex === 0) ? 'wb' : 'ab';
        $handle = fopen($tempFile, $mode);
        if ($handle) {
            fwrite($handle, $decoded);
            fclose($handle);
        } else {
            return $this->json(['error' => 'Impossible d\'écrire le fichier'], 500);
        }

        // Si c'est le dernier chunk, finaliser le fichier
        if ($chunkIndex === $totalChunks - 1) {
            // Vérifier la taille finale (max 5 Mo)
            $fileSize = filesize($tempFile);
            if ($fileSize > 5 * 1024 * 1024) {
                unlink($tempFile);
                return $this->json(['error' => 'Fichier trop volumineux (max 5 Mo)'], 413);
            }

            // Déterminer l'extension
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if (!in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'])) {
                unlink($tempFile);
                return $this->json(['error' => 'Format non autorisé'], 400);
            }

            $safeFilename = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($fileName, PATHINFO_FILENAME));
            $newFilename = 'fiche_engagement_' . $safeFilename . '_' . uniqid() . '.' . $extension;
            $finalPath = $candidatureDir . $newFilename;

            rename($tempFile, $finalPath);

            // Mettre à jour la candidature
            $candidature->setFicheEngagement($newFilename);
            $candidature->setModification(new \DateTime());
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'filename' => $newFilename,
                'message' => 'Fiche d\'engagement enregistrée'
            ]);
        }

        return $this->json([
            'success' => true,
            'chunk_received' => $chunkIndex,
            'temp_id' => $tempId
        ]);
    }

    #[Route('/search', name: 'app_admis_search', methods: ['GET'])]
    #[IsGranted('ROLE_ENT')]
    public function searchPage(): Response
    {
        return $this->render('admis/search.html.twig');
    }

    #[Route('/search/api', name: 'app_admis_search_api', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function search(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        $user = $this->getUser();

        if (strlen($query) < 2) {
            return $this->json([]);
        }

        $qb = $this->entityManager->getRepository(Candidature::class)
            ->createQueryBuilder('c')
            ->innerJoin('c.user', 'u')
            ->innerJoin('c.metier', 'm')
            ->innerJoin('c.etablissement', 'e')
            ->where('c.resultat = 2') // Seulement les candidats admis
            ->andWhere('c.numero LIKE :query OR u.nom LIKE :query OR u.prenoms LIKE :query OR u.contact LIKE :query OR u.email LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('c.numero', 'ASC')
            ->setMaxResults(10);

        // Exclure les candidats déjà inscrits
        $inscritsIds = $this->entityManager->getRepository(Admis::class)
            ->createQueryBuilder('a')
            ->select('a.user.id')
            ->where('a.user IS NOT NULL')
            ->getQuery()
            ->getResult();

        $userIds = array_column($inscritsIds, 'id');
        if (!empty($userIds)) {
            $qb->andWhere('u.id NOT IN (:inscrits)')
                ->setParameter('inscrits', $userIds);
        }

        // Filtrer selon le rôle
        if ($this->isGranted('ROLE_ENT') || $this->isGranted('ROLE_JURY')) {
            $etablissement = $this->entityManager->getRepository(Etablissement::class)
                ->findOneBy(['user' => $user]);
            if ($etablissement) {
                $qb->andWhere('c.etablissement = :etablissement')
                    ->setParameter('etablissement', $etablissement);
            }
        }

        $candidatures = $qb->getQuery()->getResult();

        $results = [];
        foreach ($candidatures as $candidature) {
            $user = $candidature->getUser();
            $results[] = [
                'id' => $candidature->getId(),
                'numero' => $candidature->getNumero(),
                'nom' => $user?->getNom() ?? '',
                'prenoms' => $user?->getPrenoms() ?? '',
                'contact' => $user?->getContact() ?? '',
                'email' => $user?->getEmail() ?? '',
                'metier' => $candidature->getMetier()?->getNom() ?? '',
                'etablissement' => $candidature->getEtablissement()?->getNom() ?? '',
                'display' => sprintf(
                    '%s - %s %s - %s',
                    $candidature->getNumero(),
                    $user?->getNom() ?? '',
                    $user?->getPrenoms() ?? '',
                    $candidature->getMetier()?->getNom() ?? ''
                )
            ];
        }

        return $this->json($results);
    }

    #[Route('/new', name: 'app_admis_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ENT')]
    public function new(Request $request): Response
    {
        $user = $this->getUser();
        $etablissement = $this->entityManager->getRepository(Etablissement::class)
            ->findOneBy(['user' => $user]);

        if (!$etablissement) {
            $this->addFlash('error', 'Votre compte n\'est pas associé à un établissement.');
            return $this->redirectToRoute('app_admis_index');
        }

        $admis = new Admis();
        $form = $this->createForm(AdmisType::class, $admis, [
            'etablissement' => $etablissement
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $candidature = $admis->getCandidature();

            // Vérifier que le candidat n'est pas déjà inscrit
            $existingAdmis = $this->entityManager->getRepository(Admis::class)
                ->findOneBy(['candidature' => $candidature]);

            if ($existingAdmis) {
                $this->addFlash('error', 'Ce candidat est déjà inscrit définitivement.');
                return $this->redirectToRoute('app_admis_index');
            }

            // Remplir les informations manquantes
            $admis->setEtablissement($candidature->getEtablissement());
            $admis->setMetier($candidature->getMetier());
            $admis->setUser($candidature->getUser());
            $admis->setNumeroInscription($candidature->getNumero());
            $admis->setModification(new \DateTime());

            $this->entityManager->persist($admis);
            $this->entityManager->flush();

            $this->addFlash('success', 'L\'inscription définitive a été enregistrée avec succès.');
            return $this->redirectToRoute('app_admis_index');
        }

        return $this->render('admis/new.html.twig', [
            'admis' => $admis,
            'form' => $form,
            'etablissement' => $etablissement
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admis_edit', methods: ['GET', 'POST'])]
    #[Security("(is_granted('ROLE_ADMIN') or (is_granted('ROLE_ENT') and admis.getEtablissement() and admis.getEtablissement().getUser() == user))")]
    public function edit(Request $request, Admis $admis, \App\Service\FileUploader $fileUploader): Response
    {
        $form = $this->createForm(AdmisType::class, $admis);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            
            $uploadError = false;
            $numero = $admis->getCandidature() ? $admis->getCandidature()->getNumero() : 'NOCAND';
            $idEtab = $admis->getEtablissement() ? $admis->getEtablissement()->getId() : '0';

            // Upload du contrat
            $contratFile = $form->get('contratFile')->getData();
            if ($contratFile) {
                $targetDirectory = $this->getParameter('kernel.project_dir').'/public/uploads/contrats/etablissement_'.$idEtab;
                $previousFile = $admis->getFichierContrat() ? basename($admis->getFichierContrat()) : null;

                try {
                    $fileName = $fileUploader->uploadWithName($contratFile, $targetDirectory, 'contrat_apprenti_' . $numero, $previousFile);
                    if ($fileName) {
                        $admis->setFichierContrat('etablissement_'.$idEtab.'/'.$fileName);
                        $admis->setDateContrat(new \DateTime());
                    }
                } catch (\Exception $e) {
                    $this->addFlash('error', $e->getMessage());
                    $uploadError = true;
                }
            }

            // Upload du livret
            $livretFile = $form->get('livretFile')->getData();
            if ($livretFile) {
                $targetDirectory = $this->getParameter('kernel.project_dir').'/public/uploads/livrets/etablissement_'.$idEtab;
                $previousFile = $admis->getFichierLivret() ? basename($admis->getFichierLivret()) : null;

                try {
                    $fileName = $fileUploader->uploadWithName($livretFile, $targetDirectory, 'livret_apprenti_' . $numero, $previousFile);
                    if ($fileName) {
                        $admis->setFichierLivret('etablissement_'.$idEtab.'/'.$fileName);
                        $admis->setDateLivret(new \DateTime());
                    }
                } catch (\Exception $e) {
                    $this->addFlash('error', $e->getMessage());
                    $uploadError = true;
                }
            }

            if (!$uploadError) {
                $admis->setModification(new \DateTime());
                $this->entityManager->flush();
                $this->addFlash('success', 'Les informations ont été mises à jour avec succès.');
            }
            
            if ($this->isGranted('ROLE_ENT') && !$this->isGranted('ROLE_ADMIN')) {
                return $this->redirectToRoute('app_dashboard');
            }
            return $this->redirectToRoute('app_admis_index');
        }

        return $this->render('admis/edit.html.twig', [
            'admis' => $admis,
            'form' => $form,
        ]);
    }


    #[Route('/{id}', name: 'app_admis_delete', methods: ['POST'])]
    #[Security("is_granted('ROLE_ADMIN')")]
    public function delete(Request $request, Admis $admis): Response
    {
        if ($this->isCsrfTokenValid('delete' . $admis->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($admis);
            $this->entityManager->flush();
            $this->addFlash('success', 'L\'inscription a été supprimée avec succès.');
        }
        return $this->redirectToRoute('app_admis_index');
    }

    #[Route('/export', name: 'app_admis_export', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function export(Request $request): Response
    {
        $etablissementId = $request->query->get('etablissement');
        $metierId = $request->query->get('metier');
        $confirmes = $request->query->get('confirmes');
        $stats = $request->query->get('stats');
        $contacts = $request->query->get('contacts');

        // Si c'est une requête POST pour afficher la page d'export
        if ($request->isMethod('POST')) {
            return $this->renderExportPage($request);
        }

        // Export Excel
        $qb = $this->entityManager->getRepository(Admis::class)
            ->createQueryBuilder('a')
            ->innerJoin('a.candidature', 'c')
            ->innerJoin('a.etablissement', 'e')
            ->innerJoin('a.metier', 'm')
            ->innerJoin('a.user', 'u')
            ->orderBy('e.nom', 'ASC')
            ->addOrderBy('m.nom', 'ASC')
            ->addOrderBy('a.numeroInscription', 'ASC');
        AdmisAbandonsConfig::applyFilter($qb);

        if ($etablissementId) {
            $qb->andWhere('e.id = :etablissement')
                ->setParameter('etablissement', $etablissementId);
        }

        if ($metierId) {
            $qb->andWhere('m.id = :metier')
                ->setParameter('metier', $metierId);
        }

        if ($confirmes) {
            $qb->andWhere('a.confirme = true');
        }

        $admis = $qb->getQuery()->getResult();

        if ($stats) {
            return $this->exportStats($admis);
        }

        if ($contacts) {
            return $this->exportContacts($admis);
        }

        // Export standard
        $headers = [
            'N°',
            'Numéro Inscription',
            'Numéro Candidature',
            'Nom',
            'Prénoms',
            'Contact',
            'Email',
            'Date Naissance',
            'Lieu Naissance',
            'Pièce Identité',
            'Numéro Pièce',
            'Diplôme',
            'Niveau Étude',
            'Métier',
            'Établissement',
            'Date Inscription',
            'Confirmé'
        ];

        $datas = [];
        foreach ($admis as $index => $a) {
            $user = $a->getUser();
            $datas[] = [
                $index + 1,
                $a->getNumeroInscription(),
                $a->getCandidature()->getNumero(),
                $user?->getNom() ?? '',
                $user?->getPrenoms() ?? '',
                $user?->getContact() ?? '',
                $user?->getEmail() ?? '',
                $user?->getDatenaissance() ? $user->getDatenaissance()->format('d/m/Y') : '',
                $user?->getLieunaissance() ?? '',
                $user?->getPiece() ?? '',
                $user?->getNumeropiece() ?? '',
                $user?->getDiplome() ?? '',
                $user?->getNiveau() ?? '',
                $a->getMetier()?->getNom() ?? '',
                $a->getEtablissement()?->getNom() ?? '',
                $a->getDateInscription()?->format('d/m/Y H:i') ?? '',
                $a->getConfirme() ? 'Oui' : 'Non'
            ];
        }

        $filename = 'ADMIS_DEFINITIFS';
        if ($etablissementId) {
            $etab = $this->entityManager->getRepository(Etablissement::class)->find($etablissementId);
            $filename .= '_' . $etab?->getNom();
        }
        if ($metierId) {
            $met = $this->entityManager->getRepository(Metier::class)->find($metierId);
            $filename .= '_' . $met?->getNom();
        }
        if ($confirmes) {
            $filename .= '_CONFIRMES';
        }
        $filename .= '_' . date('Ymd') . '.xlsx';

        $this->spreadsheetGenerator->generate($filename, $headers, $datas);
        return new Response();
    }

    #[Route('/exports', name: 'app_admis_exports', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function exports(Request $request): Response
    {
        return $this->renderExportPage($request);
    }

    private function renderExportPage(Request $request): Response
    {
        // Récupérer les statistiques
        $stats = $this->getStatistics();

        // Récupérer les établissements et métiers pour les filtres
        $etablissements = $this->entityManager->getRepository(Etablissement::class)
            ->findBy([], ['nom' => 'ASC']);
        $metiers = $this->entityManager->getRepository(Metier::class)
            ->findBy([], ['nom' => 'ASC']);

        // Résumé par établissement et métier
        $resume = $this->getResumeByEtablissementMetier();
        $totals = $this->calculateTotalsResume($resume);

        return $this->render('admis/export.html.twig', [
            'stats' => $stats,
            'etablissements' => $etablissements,
            'metiers' => $metiers,
            'resume' => $resume,
            'totals' => $totals
        ]);
    }

    private function getStatistics(): array
    {
        $qb = $this->entityManager->getRepository(Admis::class)
            ->createQueryBuilder('a')
            ->select('COUNT(a.id) as total')
            ->addSelect('SUM(CASE WHEN a.confirme = true THEN 1 ELSE 0 END) as confirmes')
            ->addSelect('COUNT(DISTINCT a.etablissement) as etablissements')
            ->addSelect('COUNT(DISTINCT a.metier) as metiers');
        AdmisAbandonsConfig::applyFilter($qb);

        $result = $qb->getQuery()->getSingleResult();

        return [
            'total' => (int) $result['total'],
            'confirmes' => (int) $result['confirmes'],
            'etablissements' => (int) $result['etablissements'],
            'metiers' => (int) $result['metiers']
        ];
    }

    private function getResumeByEtablissementMetier(): array
    {
        $qb = $this->entityManager->getRepository(Admis::class)
            ->createQueryBuilder('a')
            ->innerJoin('a.etablissement', 'e')
            ->innerJoin('a.metier', 'm')
            ->select('e.nom as etablissement_nom')
            ->addSelect('m.nom as metier_nom')
            ->addSelect('COUNT(a.id) as total')
            ->addSelect('SUM(CASE WHEN a.confirme = true THEN 1 ELSE 0 END) as confirmes')
            ->addSelect('SUM(CASE WHEN a.confirme = false THEN 1 ELSE 0 END) as non_confirmes')
            ->groupBy('e.id, m.id')
            ->orderBy('e.nom', 'ASC')
            ->addOrderBy('m.nom', 'ASC');
        AdmisAbandonsConfig::applyFilter($qb);

        return $qb->getQuery()->getResult();
    }

    private function calculateTotalsResume(array $resume): array
    {
        $totals = [
            'total' => 0,
            'confirmes' => 0,
            'non_confirmes' => 0
        ];

        foreach ($resume as $row) {
            $totals['total'] += $row['total'];
            $totals['confirmes'] += $row['confirmes'];
            $totals['non_confirmes'] += $row['non_confirmes'];
        }

        return $totals;
    }

    private function exportStats(array $admis): Response
    {
        // Grouper par établissement et métier pour les stats
        $stats = [];
        foreach ($admis as $a) {
            $etabNom = $a->getEtablissement()->getNom();
            $metNom = $a->getMetier()->getNom();

            if (!isset($stats[$etabNom])) {
                $stats[$etabNom] = [];
            }

            if (!isset($stats[$etabNom][$metNom])) {
                $stats[$etabNom][$metNom] = [
                    'total' => 0,
                    'confirmes' => 0,
                    'hommes' => 0,
                    'femmes' => 0
                ];
            }

            $stats[$etabNom][$metNom]['total']++;
            if ($a->getConfirme()) {
                $stats[$etabNom][$metNom]['confirmes']++;
            }

            // Compter par sexe (si disponible dans l'utilisateur)
            $user = $a->getUser();
            if ($user && $user->getSexe() === 'MASCULIN') {
                $stats[$etabNom][$metNom]['hommes']++;
            } elseif ($user && $user->getSexe() === 'FEMININ') {
                $stats[$etabNom][$metNom]['femmes']++;
            }
        }

        $headers = [
            'Établissement',
            'Métier',
            'Total Inscrits',
            'Confirmés',
            'Non Confirmés',
            'Hommes',
            'Femmes',
            'Taux Confirmation (%)'
        ];

        $datas = [];
        foreach ($stats as $etabNom => $metiers) {
            foreach ($metiers as $metNom => $data) {
                $taux = $data['total'] > 0 ? round(($data['confirmes'] / $data['total']) * 100, 1) : 0;
                $datas[] = [
                    $etabNom,
                    $metNom,
                    $data['total'],
                    $data['confirmes'],
                    $data['total'] - $data['confirmes'],
                    $data['hommes'],
                    $data['femmes'],
                    $taux . '%'
                ];
            }
        }

        $this->spreadsheetGenerator->generate('STATS_ADMIS_' . date('Ymd') . '.xlsx', $headers, $datas);
        return new Response();
    }

    private function exportContacts(array $admis): Response
    {
        $headers = [
            'N°',
            'Numéro Inscription',
            'Nom',
            'Prénoms',
            'Contact',
            'Email',
            'Métier',
            'Établissement',
            'Confirmé'
        ];

        $datas = [];
        foreach ($admis as $index => $a) {
            $user = $a->getUser();
            $datas[] = [
                $index + 1,
                $a->getNumeroInscription(),
                $user?->getNom() ?? '',
                $user?->getPrenoms() ?? '',
                $user?->getContact() ?? '',
                $user?->getEmail() ?? '',
                $a->getMetier()?->getNom() ?? '',
                $a->getEtablissement()?->getNom() ?? '',
                $a->getConfirme() ? 'Oui' : 'Non'
            ];
        }

        $this->spreadsheetGenerator->generate('CONTACTS_ADMIS_' . date('Ymd') . '.xlsx', $headers, $datas);
        return new Response();
    }

    #[Route('/{id}/valider', name: 'app_admis_valider', methods: ['POST'])]
    #[IsGranted('ROLE_ENT')]
    public function valider(Admis $admis): Response
    {
        // Vérifier que l'établissement est bien celui de l'admis
        $user = $this->getUser();
        $etablissementUser = $this->entityManager->getRepository(Etablissement::class)
            ->findOneBy(['user' => $user]);

        if (!$etablissementUser || $admis->getEtablissement()->getId() !== $etablissementUser->getId()) {
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à valider cette inscription.');
            return $this->redirectToRoute('app_dashboard');
        }

        $admis->setConfirme(true);
        $admis->setModification(new \DateTime());
        $this->entityManager->flush();

        $this->addFlash('success', 'L\'inscription a été validée avec succès.');
        return $this->redirectToRoute('app_admis_index');
    }

    #[Route('/{id}/refuser', name: 'app_admis_refuser', methods: ['POST'])]
    #[IsGranted('ROLE_ENT')]
    public function refuser(Admis $admis, Request $request): Response
    {
        // Vérifier que l'établissement est bien celui de l'admis
        $user = $this->getUser();
        $etablissementUser = $this->entityManager->getRepository(Etablissement::class)
            ->findOneBy(['user' => $user]);

        if (!$etablissementUser || $admis->getEtablissement()->getId() !== $etablissementUser->getId()) {
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à refuser cette inscription.');
            return $this->redirectToRoute('app_dashboard');
        }

        $motif = $request->request->get('motif');
        if (!$motif) {
            $this->addFlash('error', 'Veuillez fournir un motif de refus.');
            return $this->redirectToRoute('app_admis_index');
        }

        $admis->setConfirme(false);
        $admis->setModification(new \DateTime());
        $this->entityManager->flush();

        $this->addFlash('success', 'L\'inscription a été refusée.');
        return $this->redirectToRoute('app_admis_index');
    }
}
