<?php

namespace App\Controller;

use App\Config\AdmisAbandonsConfig;
use App\Config\PacteConfig;
use App\Service\EligibiliteService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use App\Entity\Admis;
use App\Entity\Candidature;
use App\Entity\Metier;
use App\Entity\Etablissement;
use App\Entity\User;
use App\Entity\EtablissementMetier;
use App\Entity\SessionRecrutement;
use App\Form\CandidatureType;
use App\Service\FileUploader;
use App\Service\PdfGenerator;
use App\Service\SpreadsheetGenerator;
use App\Service\Constant;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[Route('/candidature')]
class CandidatureController extends AbstractController
{
    // Total maximum de la grille d'évaluation d'entretien (somme des 13 notes max : 3+3+5+6+6+2+2+3+2+2+2+2+2)
    private const NOTE_TOTAL_MAX = 40;

    private const STATUT_LABELS = [
        2 => 'Accepté',
        1 => 'Rejeté',
        'default' => 'En attente'
    ];

    private EntityManagerInterface $entityManager;
    private FileUploader $fileUploader;
    private PdfGenerator $pdfGenerator;
    private SpreadsheetGenerator $spreadsheetGenerator;
    private ParameterBagInterface $params;
    private Constant $constant;
    private UserPasswordHasherInterface $passwordHasher;
    private EligibiliteService $eligibiliteService;

    public function __construct(
        EntityManagerInterface $entityManager,
        FileUploader $fileUploader,
        PdfGenerator $pdfGenerator,
        SpreadsheetGenerator $spreadsheetGenerator,
        ParameterBagInterface $params,
        Constant $constant,
        UserPasswordHasherInterface $passwordHasher,
        EligibiliteService $eligibiliteService
    ) {
        $this->eligibiliteService = $eligibiliteService;
        $this->entityManager = $entityManager;
        $this->fileUploader = $fileUploader;
        $this->pdfGenerator = $pdfGenerator;
        $this->spreadsheetGenerator = $spreadsheetGenerator;
        $this->params = $params;
        $this->constant = $constant;
        $this->passwordHasher = $passwordHasher;
    }

    #[Route('/', name: 'app_candidature_index', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(Request $request, \Knp\Component\Pager\PaginatorInterface $paginator): Response
    {
        $user = $this->getUser();

        $qb = $this->entityManager->getRepository(Candidature::class)
            ->createQueryBuilder('c')
            ->leftJoin('c.etablissement', 'e')
            ->leftJoin('c.metier', 'm')
            ->addSelect('e', 'm');

        if ($this->isGranted('ROLE_ADMIN')) {
            $qb->leftJoin('c.user', 'u')->addSelect('u');
            $is_admin = true;
        } elseif ($this->isGranted('ROLE_ENT') || $this->isGranted('ROLE_JURY')) {
            $qb->leftJoin('c.user', 'u')->addSelect('u')
                ->where('e.user = :user')
                ->setParameter('user', $user);
            $is_admin = true; // Vérifiez si ROLE_ENT/JURY doivent vraiment être considérés comme admin dans la vue Twig
        } else {
            $qb->where('c.user = :user')
                ->setParameter('user', $user);
            $is_admin = false;
        }

        // ===== FILTRES ADMIN =====
        $etablissementFiltre = null;
        $metierFiltre = null;
        $sessionFiltre = null;
        $etablissements = [];
        $metiers = [];
        $sessions = [];

        if ($this->isGranted('ROLE_ADMIN')) {
            $etablissementId = $request->query->get('etablissement');
            $metierId        = $request->query->get('metier');
            $sessionId       = $request->query->get('session');

            $etablissementFiltre = $etablissementId ? $this->entityManager->getRepository(Etablissement::class)->find($etablissementId) : null;
            $metierFiltre = $metierId ? $this->entityManager->getRepository(Metier::class)->find($metierId) : null;
            $sessionFiltre = $sessionId ? $this->entityManager->getRepository(SessionRecrutement::class)->find($sessionId) : null;

            if ($etablissementFiltre) {
                $qb->andWhere('c.etablissement = :etablissement')
                    ->setParameter('etablissement', $etablissementFiltre);
            }

            if ($metierFiltre) {
                $qb->andWhere('c.metier = :metier')
                    ->setParameter('metier', $metierFiltre);
            }

            if ($sessionFiltre) {
                $qb->andWhere('c.session = :session')
                    ->setParameter('session', $sessionFiltre);
            }

            $etablissements = $this->entityManager->getRepository(Etablissement::class)->findBy([], ['nom' => 'ASC']);
            $metiers        = $this->entityManager->getRepository(Metier::class)->findBy([], ['nom' => 'ASC']);
            $sessions       = $this->entityManager->getRepository(SessionRecrutement::class)->findBy([], ['annee' => 'DESC', 'numeroVague' => 'DESC']);
        } elseif ($this->isGranted('ROLE_ENT') || $this->isGranted('ROLE_JURY')) {
            $metierId = $request->query->get('metier');
            if ($metierId) {
                $qb->andWhere('m.id = :metierId')
                    ->setParameter('metierId', $metierId);
                $metierFiltre = $this->entityManager->getRepository(Metier::class)->find($metierId);
            }
            $etablissement = $this->entityManager->getRepository(Etablissement::class)->findOneBy(['user' => $user]);
            if ($etablissement) {
                $metiers = $this->getMetiersForEtablissement($etablissement);
            }
        }

        // ===== FILTRE PAR DATE =====
        $dateDebut = $request->query->get('date_debut');
        $dateFin = $request->query->get('date_fin');

        if ($dateDebut) {
            $qb->andWhere('c.creation >= :dateDebut')
                ->setParameter('dateDebut', new \DateTime($dateDebut . ' 00:00:00'));
        }
        if ($dateFin) {
            $qb->andWhere('c.creation <= :dateFin')
                ->setParameter('dateFin', new \DateTime($dateFin . ' 23:59:59'));
        }

        // Gestion de la recherche
        $searchTerm = $request->query->get('q');
        if ($searchTerm) {
            if ($is_admin) {
                $qb->andWhere('c.numero LIKE :search OR u.nom LIKE :search OR u.prenoms LIKE :search OR m.nom LIKE :search OR e.nom LIKE :search')
                    ->setParameter('search', '%' . $searchTerm . '%');
            } else {
                $qb->andWhere('c.numero LIKE :search OR m.nom LIKE :search OR e.nom LIKE :search')
                    ->setParameter('search', '%' . $searchTerm . '%');
            }
        }

        $qb->orderBy('c.id', 'DESC');

        $limit = $request->query->getInt('limit', 20);

        $candidatures = $paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            $limit
        );

        return $this->render('candidature/index.html.twig', [
            'candidatures'         => $candidatures,
            'is_admin'             => $is_admin,
            'limit'                => $limit,
            'searchTerm'           => $searchTerm,
            'etablissements'       => $etablissements,
            'metiers'              => $metiers,
            'sessions'             => $sessions,
            'etablissement_filtre' => $etablissementFiltre,
            'metier_filtre'        => $metierFiltre,
            'session_filtre'       => $sessionFiltre,
            'date_debut'           => $dateDebut,
            'date_fin'             => $dateFin,
        ]);
    }

    #[Route('/new', name: 'app_candidature_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {

        // Bloquer les utilisateurs ENT
        if ($this->isGranted('ROLE_ENT')) {
            $this->addFlash(
                'error',
                'Vous êtes connecté comme établissement. <a href="' . $this->generateUrl('app_logout') . '" class="font-semibold underline">Se déconnecter</a> pour créer une candidature.'
            );

            return $this->redirectToRoute('app_candidature_index');
        }


        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        // Session ouverte (active, statut OUVERTE, dans la fenêtre de dates)
        $sessionActive = $this->entityManager->getRepository(SessionRecrutement::class)->findActive();

        if (!$sessionActive) {
            $this->addFlash('error', 'Aucune session de recrutement n\'est actuellement ouverte. Les candidatures ne sont possibles que pendant la période d\'ouverture.');
            return $this->redirectToRoute('app_candidature_index');
        }

        if ($this->countUserCandidatures($user, $sessionActive) > 0) {
            $this->addFlash('error', 'Vous avez déjà soumis une candidature.');
            return $this->redirectToRoute('app_candidature_index');
        }

        $candidature = $this->createCandidature($user);
        $candidature->setSession($sessionActive);
        $this->prefillFromRequest($candidature, $request);

        $form = $this->createForm(CandidatureType::class, $candidature);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $renderForm = fn () => $this->render('candidature/form.html.twig', [
                'candidature' => $candidature,
                'form' => $form,
                'document_labels' => $this->constant->document_labels,
                'candidaturesCount' => $this->countUserCandidatures($user)
            ]);

            // Contrôle serveur : une candidature par utilisateur et par session
            if ($this->countUserCandidatures($user, $sessionActive) > 0) {
                $this->addFlash('error', 'Vous avez déjà soumis une candidature.');
                return $this->redirectToRoute('app_candidature_index');
            }

            // Éligibilité (nationalité, âge à la date de référence de la session)
            $erreursEligibilite = $this->eligibiliteService->verifier($user, $sessionActive);
            if ($erreursEligibilite) {
                foreach ($erreursEligibilite as $erreur) {
                    $this->addFlash('error', $erreur);
                }
                return $renderForm();
            }

            // L'association (établissement, métier) doit exister et être active (nbrplace n'est jamais contrôlé)
            $etab = $candidature->getEtablissement();
            $met = $candidature->getMetier();
            $emActif = ($etab && $met) ? $this->entityManager->getRepository(EtablissementMetier::class)
                ->findOneBy(['etablissement' => $etab, 'metier' => $met, 'estActif' => true]) : null;
            if (!$emActif) {
                $this->addFlash('error', 'Le métier sélectionné n\'est pas ouvert dans cet établissement.');
                return $renderForm();
            }

            // Pièces obligatoires
            $fichiers = [];
            $manquants = [];
            foreach ($this->constant->document_labels as $field => $config) {
                $fichiers[$field] = $form->get($field)->getData();
                if (($config['required'] ?? false) && !$fichiers[$field]) {
                    $manquants[] = $config['text'] ?? $field;
                }
            }
            if ($manquants) {
                $this->addFlash('error', 'Documents requis : ' . implode(', ', $manquants));
                return $renderForm();
            }

            // 1) Transaction courte : insertion + numéro basé sur l'ID réel
            try {
                $this->entityManager->wrapInTransaction(function () use ($candidature) {
                    $this->entityManager->persist($candidature);
                    $this->entityManager->flush();
                    $candidature->setNumero($this->generateNumero($candidature));
                    $this->entityManager->flush();
                });
            } catch (UniqueConstraintViolationException $e) {
                $this->addFlash('error', 'Vous avez déjà soumis une candidature.');
                return $this->redirectToRoute('app_candidature_index');
            }

            // 2) Hors transaction : dossier média et téléversement
            try {
                $this->handleUploadedFiles($candidature, $fichiers);
                $this->entityManager->flush();
            } catch (\Throwable $e) {
                // 3) Échec : suppression de la candidature ET du dossier
                try {
                    $this->fileUploader->remove($this->params->get('dir_media') . $candidature->getNumero());
                } catch (\Throwable $ignored) {
                }
                if ($this->entityManager->isOpen()) {
                    $this->entityManager->remove($candidature);
                    $this->entityManager->flush();
                }
                $this->addFlash('error', 'Le téléversement de vos documents a échoué. Votre candidature n\'a pas été enregistrée, veuillez réessayer.');
                return $this->redirectToRoute('app_candidature_new');
            }

            return $this->redirectToRoute('app_candidature_success', ['id' => $candidature->getId()]);
        }

        $this->displayFormErrors($form);

        return $this->render('candidature/form.html.twig', [
            'candidature' => $candidature,
            'form' => $form,
            'document_labels' => $this->constant->document_labels,
            'candidaturesCount' => $this->countUserCandidatures($user)
        ]);
    }
    #[Route('/success/{id}', name: 'app_candidature_success', requirements: ['id' => '\\d+'])]
    #[Security("is_granted('ROLE_CANDIDAT') and user === candidature.getUser() or is_granted('ROLE_ADMIN')")]
    public function success(Candidature $candidature): Response
    {
        return $this->render('candidature/success.html.twig', ['candidature' => $candidature]);
    }

    #[Route('/{id}/edit', name: 'app_candidature_edit', methods: ['GET', 'POST'], requirements: ['id' => '\\d+'])]
    #[Security("is_granted('ROLE_ADMIN') or ((is_granted('ROLE_ENT') or is_granted('ROLE_JURY')) and candidature.getEtablissement() and candidature.getEtablissement().getUser() == user) or (is_granted('ROLE_CANDIDAT') and candidature.getUser() == user)")]
    public function edit(Request $request, Candidature $candidature): Response
    {
        $isStaff = $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_ENT') || $this->isGranted('ROLE_JURY');

        if (!$isStaff && $candidature->getEtustatut() !== null) {
            $message = $candidature->getEtustatut() === 2
                ? 'Votre candidature a été acceptée, vous ne pouvez plus la modifier.'
                : 'Votre candidature a été rejetée, vous ne pouvez plus la modifier.';
            $this->addFlash('error', $message);
            return $this->redirectToRoute('app_candidature_voir', ['id' => $candidature->getId()]);
        }

        $form = $this->createForm(CandidatureType::class, $candidature, ['evaluation_only' => true]);

        // ✅ Règle métier : on ne peut saisir le résultat final que si la visite médicale est effectuée
        if ($candidature->getVisstatut() !== 2) {
            $form->add('resultat', ChoiceType::class, [
                'choices' => [
                    'En attente' => null,
                    'Admis' => 2,
                    'Non admis' => 1
                ],
                'label' => 'Résultat final',
                'required' => false,
                'disabled' => true,
                'help' => 'Disponible uniquement après une visite médicale effectuée.',
                'attr' => [
                    'class' => 'w-full px-4 py-2 rounded-lg border border-slate-200 bg-slate-100 cursor-not-allowed focus:outline-none transition'
                ]
            ]);
        }

        $section = (string) $request->request->get('section', '');
        $sectionFields = self::evaluationSectionFields();
        if ($section !== '' && !isset($sectionFields[$section])) {
            $this->addFlash('error', 'Section de formulaire inconnue.');
            return $this->redirectToRoute('app_candidature_edit', ['id' => $candidature->getId()]);
        }
        // L'analyse du dossier est réservée aux rôles ENT et ADMIN
        if ($section === 'dossier' && !$this->isGranted('ROLE_ENT') && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $safeRedirectTo = $this->sanitizeRedirectTarget($request->get('redirect_to'), $request);

        if ($isStaff) {
            if ($section !== '' && $request->isMethod('POST')) {
                // Enregistrement indépendant d'une étape : seuls les champs de la section (et le jeton CSRF) sont soumis au formulaire
                $submitted = $request->request->all($form->getName());
                $allowed = array_flip(array_merge($sectionFields[$section], ['_token']));
                $form->submit(array_intersect_key($submitted, $allowed), false);
            } else {
                $form->handleRequest($request);
            }

            if ($form->isSubmitted() && $form->isValid()) {
                // ✅ Règle métier : on ne peut admettre un candidat que si sa visite médicale a été effectuée
                $nouveauResultat = $candidature->getResultat();
                if (($section === '' || $section === 'visite') && $nouveauResultat !== null && $candidature->getVisstatut() !== 2) {
                    $this->addFlash('error', 'Impossible de définir le résultat final : la visite médicale doit d\'abord être marquée comme effectuée.');
                    return $this->redirectToRoute('app_candidature_edit', ['id' => $candidature->getId()]);
                }

                // $this->calculerDecisionEntretien($candidature);
                $candidature->setModification(new \DateTime());
                $this->entityManager->flush();
                $this->addFlash('success', 'Évaluation mise à jour avec succès.');

                // Enregistrement d'une étape : on reste sur la page, à la même étape
                if ($section !== '') {
                    return $this->redirectToRoute('app_candidature_edit', [
                        'id' => $candidature->getId(),
                        'redirect_to' => $safeRedirectTo,
                        'step' => $section,
                    ]);
                }

                // Redirection dynamique : paramètre redirect_to, sinon Referer du même hôte, sinon index
                $redirectTo = $safeRedirectTo
                    ?? $this->sanitizeRedirectTarget($request->headers->get('referer'), $request);

                return $redirectTo
                    ? $this->redirect($redirectTo)
                    : $this->redirectToRoute('app_candidature_index');
            }
        }

        $activeStep = (string) $request->get('step', $section);
        if (!isset($sectionFields[$activeStep])) {
            $activeStep = '';
        }
        return $this->render('candidature/form.html.twig', [
            'candidature' => $candidature,
            'form' => $form,
            'document_labels' => $this->constant->document_labels,
            'redirect_to' => $safeRedirectTo, // URL interne validée, transmise à la vue
            'active_step' => $activeStep,
        ]);
    }

    /**
     * Champs du formulaire d'évaluation regroupés par étape (enregistrement indépendant).
     *
     * @return array<string, string[]>
     */
    private static function evaluationSectionFields(): array
    {
        $notes = [];
        for ($i = 1; $i <= 13; $i++) {
            $notes[] = 'note' . $i;
        }

        return [
            'dossier' => ['etustatut', 'etucom'],
            'entretien' => array_merge(
                ['entdate', 'entlieu', 'jury', 'creneauEntretien', 'entstatut', 'decisionJury', 'entcom'],
                $notes
            ),
            'visite' => ['visdate', 'vislieu', 'visstatut', 'resultat', 'viscom'],
        ];
    }

    /**
     * Anti open-redirect : n'accepte qu'un chemin interne (ou une URL du même hôte, ramenée à son chemin).
     */
    private function sanitizeRedirectTarget(?string $url, Request $request): ?string
    {
        if ($url === null || $url === '' || preg_match('/[\x00-\x1F\x7F\\\\]/', $url)) {
            return null;
        }

        if (preg_match('#^https?://#i', $url)) {
            $parts = parse_url($url);
            if (!$parts || ($parts['host'] ?? null) !== $request->getHost()) {
                return null;
            }
            $url = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
        }

        if ($url[0] !== '/' || str_starts_with($url, '//')) {
            return null;
        }

        return $url;
    }
    /**
     * Calcule la décision du jury (entstatut) à partir des 13 notes de la grille d'évaluation.
     * Barème : total sur 40, ramené à une moyenne sur 4 (total / 10).
     * Admissible (entstatut = 2) si moyenne >= 2, sinon non admissible (entstatut = 1).
     * Si aucune note n'est encore renseignée, la décision n'est pas modifiée (reste "En attente").
     */
    private function calculerDecisionEntretien(Candidature $candidature): void
    {
        $total = 0.0;
        $uneNoteRenseignee = false;

        for ($i = 1; $i <= 13; $i++) {
            $getter = 'getNote' . $i;
            $valeur = $candidature->$getter();

            if ($valeur !== null) {
                $uneNoteRenseignee = true;
                $total += $valeur;
            }
        }

        if (!$uneNoteRenseignee) {
            return;
        }

        $moyenne = ($total / self::NOTE_TOTAL_MAX) * 4;
        $candidature->setDecisionJury($moyenne >= 2 ? 2 : 1);
    }

    // #[Route('/selection-visite-medicale', name: 'app_candidature_selection_visite', methods: ['GET', 'POST'])]
    // #[Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_ENT') or is_granted('ROLE_JURY')")]
    public function selectionVisiteMedicale(Request $request): Response
    {
        // Nombre de places à pourvoir pour la visite médicale
        $nombrePlaces = 75;

        // Récupérer tous les candidats admissibles (entstatut = 2), avec leurs notes
        $qb = $this->entityManager->getRepository(Candidature::class)
            ->createQueryBuilder('c')
            ->leftJoin('c.user', 'u')
            ->leftJoin('c.metier', 'm')
            ->leftJoin('c.etablissement', 'e')
            ->addSelect('u', 'm', 'e')
            ->where('c.decisionJury = 2')
            ->orderBy('c.note1 + c.note2 + c.note3 + c.note4 + c.note5 + c.note6 + c.note7 + c.note8 + c.note9 + c.note10 + c.note11 + c.note12 + c.note13', 'DESC');

        // Filtre par établissement pour ENT/JURY
        if (!$this->isGranted('ROLE_ADMIN')) {
            $etablissement = $this->entityManager->getRepository(Etablissement::class)
                ->findOneBy(['user' => $this->getUser()]);
            if ($etablissement) {
                $qb->andWhere('c.etablissement = :etab')->setParameter('etab', $etablissement);
            }
        }

        $candidats = $qb->getQuery()->getResult();

        // Si le formulaire est soumis, on effectue la sélection
        if ($request->isMethod('POST')) {
            // Réinitialiser la sélection
            foreach ($candidats as $c) {
                $c->setSelectionnePourVisite(null);
            }

            // Prendre les N premiers
            $selectionnes = array_slice($candidats, 0, $nombrePlaces);
            foreach ($selectionnes as $c) {
                $c->setSelectionnePourVisite(true);
            }

            // Les autres sont marqués comme non retenus
            $nonSelectionnes = array_slice($candidats, $nombrePlaces);
            foreach ($nonSelectionnes as $c) {
                $c->setSelectionnePourVisite(false);
            }

            $this->entityManager->flush();
            $this->addFlash('success', count($selectionnes) . ' candidats ont été sélectionnés pour la visite médicale.');

            return $this->redirectToRoute('app_candidature_selection_visite');
        }

        // Calculer les totaux pour l'affichage
        $candidatsAvecTotal = [];
        foreach ($candidats as $c) {
            $total = 0;
            for ($i = 1; $i <= 13; $i++) {
                $getter = 'getNote' . $i;
                $total += $c->$getter() ?? 0;
            }
            $candidatsAvecTotal[] = [
                'candidature' => $c,
                'total' => $total,
                'moyenne' => round(($total / 40) * 4, 2),
            ];
        }

        return $this->render('candidature/selection_visite.html.twig', [
            'candidats' => $candidatsAvecTotal,
            'nombrePlaces' => $nombrePlaces,
        ]);
    }

    #[Route('/{id}/update-info', name: 'app_candidature_update_info', methods: ['POST'], requirements: ['id' => '\\d+'])]
    #[Security("is_granted('ROLE_ADMIN') or ((is_granted('ROLE_ENT') or is_granted('ROLE_JURY')) and candidature.getEtablissement() and candidature.getEtablissement().getUser() == user) or (is_granted('ROLE_CANDIDAT') and candidature.getUser() == user)")]
    public function updateInfo(Request $request, Candidature $candidature): Response
    {
        if (!$this->isCsrfTokenValid('candidature_update_info_' . $candidature->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_candidature_edit', ['id' => $candidature->getId()]);
        }

        // Le candidat ne peut modifier son dossier que tant qu'il est "en attente" (resultat non renseigné).
        // Une fois accepté (2) ou rejeté (1), il n'a plus le droit de le modifier.
        $isStaff = $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_ENT') || $this->isGranted('ROLE_JURY');
        if (!$isStaff && $candidature->getResultat() !== null) {
            $message = $candidature->getResultat() === 2
                ? 'Votre candidature a été acceptée, vous ne pouvez plus la modifier.'
                : 'Votre candidature a été rejetée, vous ne pouvez plus la modifier.';
            $this->addFlash('error', $message);
            return $this->redirectToRoute('app_candidature_voir', ['id' => $candidature->getId()]);
        }

        // Mettre à jour nom/prénoms/email/contact du candidat associé
        $candidatUser = $candidature->getUser();
        if ($candidatUser) {
            $nom = trim($request->request->get('nom', ''));
            $prenoms = trim($request->request->get('prenoms', ''));
            $email = trim($request->request->get('email', ''));
            $contact = trim($request->request->get('contact', ''));

            if ($nom !== '') {
                $candidatUser->setNom($nom);
            }
            if ($prenoms !== '') {
                $candidatUser->setPrenoms($prenoms);
            }

            if ($email !== '') {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->addFlash('error', "L'adresse email saisie n'est pas valide.");
                } else {
                    $existing = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
                    if ($existing && $existing->getId() !== $candidatUser->getId()) {
                        $this->addFlash('error', 'Cette adresse email est déjà utilisée par un autre compte.');
                    } else {
                        $candidatUser->setEmail($email);
                    }
                }
            }

            $candidatUser->setContact($contact !== '' ? $contact : null);
        }

        // Mettre à jour le métier
        $metierId = $request->request->get('metier_id');
        if ($metierId) {
            $metier = $this->entityManager->getRepository(Metier::class)->find($metierId);
            if ($metier) {
                $emExists = $this->entityManager->getRepository(EtablissementMetier::class)
                    ->findOneBy(['etablissement' => $candidature->getEtablissement(), 'metier' => $metier]);
                if ($emExists) {
                    $candidature->setMetier($metier);
                } else {
                    $this->addFlash('error', "Le métier sélectionné n'est pas ouvert dans cet établissement.");
                    return $this->redirectToRoute('app_candidature_edit', ['id' => $candidature->getId()]);
                }
            }
        }

        // Mettre à jour le diplôme
        $diplome = $request->request->get('diplome');
        if ($diplome !== null) {
            $candidature->setDiplome($diplome !== '' ? $diplome : null);
        }

        // Traiter les fichiers uploadés
        $dir = $this->params->get('dir_media') . $candidature->getNumero() . '/';
        $this->fileUploader->mkdir($dir);
        foreach (['fpiece', 'fniveau', 'fautre'] as $field) {
            $file = $request->files->get($field);
            if ($file) {
                // Validation serveur : type MIME réel (détecté sur le contenu) et taille max, comme dans CandidatureType
                $allowedMimes = $this->constant->document_labels[$field]['mimeTypes'] ?? ['application/pdf', 'image/jpeg', 'image/pjpeg', 'image/png'];
                if (!$file->isValid() || $file->getSize() > \App\Service\Constant::UPLOAD_MAX_BYTES || !in_array($file->getMimeType(), $allowedMimes, true)) {
                    $this->addFlash('error', 'Fichier refusé : formats acceptés JPG, PNG ou PDF (5 Mo maximum).');
                    return $this->redirectToRoute('app_candidature_edit', ['id' => $candidature->getId()]);
                }
                $getter = 'get' . ucfirst($field);
                $setter = 'set' . ucfirst($field);
                $oldFile = $candidature->$getter();
                $fileName = $this->fileUploader->upload($file, $dir, $oldFile);
                $candidature->$setter($fileName);
            }
        }

        $candidature->setModification(new \DateTime());
        $this->entityManager->flush();
        $this->addFlash('success', 'Informations mises à jour avec succès.');
        return $this->redirectToRoute('app_candidature_edit', ['id' => $candidature->getId()]);
    }

    #[Route('/{id}', name: 'app_candidature_delete', methods: ['POST'], requirements: ['id' => '\\d+'])]
    #[Security("(is_granted('ROLE_CANDIDAT') and user === candidature.getUser()) or is_granted('ROLE_ADMIN') or is_granted('ROLE_ENT') or is_granted('ROLE_JURY')")]
    public function delete(Request $request, Candidature $candidature): Response
    {
        if ($this->isCsrfTokenValid('delete' . $candidature->getId(), $request->request->get('_token'))) {
            $this->deleteCandidature($candidature);
            $this->addFlash('success', 'Candidature supprimée avec succès.');
        }
        return $this->redirectToRoute('app_candidature_index');
    }

    #[Route('/{id}/voir', name: 'app_candidature_voir', methods: ['GET'], requirements: ['id' => '\\d+'])]
    #[Security("is_granted('ROLE_ADMIN') or ((is_granted('ROLE_ENT') or is_granted('ROLE_JURY')) and candidature.getEtablissement() and candidature.getEtablissement().getUser() == user) or (is_granted('ROLE_CANDIDAT') and candidature.getUser() == user)")]
    public function voir(Candidature $candidature): Response
    {
        $isStaff = $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_ENT') || $this->isGranted('ROLE_JURY');

        return $this->render('candidature/show.html.twig', [
            'candidature' => $candidature,
            'document_labels' => $this->constant->document_labels,
            'is_staff' => $isStaff,
        ]);
    }

    #[Route('/{id}/reinitialiser-mdp', name: 'app_candidature_reinitialiser_mdp', methods: ['POST'], requirements: ['id' => '\\d+'])]
    #[Security("is_granted('ROLE_ADMIN') or (is_granted('ROLE_ENT') and candidature.getEtablissement() and candidature.getEtablissement().getUser() == user)")]
    public function reinitialiserMdp(Request $request, Candidature $candidature): Response
    {
        if (!$this->isCsrfTokenValid('candidature_reinitialiser_mdp_' . $candidature->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_candidature_edit', ['id' => $candidature->getId()]);
        }

        // Générer un nouveau mot de passe aléatoire
        $nouveauMdp = bin2hex(random_bytes(8));

        // Mettre à jour le mot de passe de l'utilisateur associé
        $user = $candidature->getUser();
        if ($user) {
            $encodedPassword = $this->passwordHasher->hashPassword($user, $nouveauMdp);
            $user->setPassword($encodedPassword);

            // Sauvegarder les changements
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Envoyer le nouveau mot de passe par email ou notification (à implémenter selon votre système de notification)
            // Pour l'instant, on ajoute juste un message flash
            $this->addFlash('success', 'Mot de passe réinitialisé avec succès. Le nouveau mot de passe est : ' . $nouveauMdp);
        } else {
            $this->addFlash('error', 'Aucun utilisateur associé à cette candidature.');
        }

        return $this->redirectToRoute('app_candidature_edit', ['id' => $candidature->getId()]);
    }

    #[Route('/imprimer/{id}', name: 'app_candidature_imprimer', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function imprimer(Candidature $candidature): Response
    {
        // Fixe le timezone sur le fuseau de Côte d'Ivoire (UTC)
        $timezone = new \DateTimeZone('Africa/Abidjan');

        return $this->pdfGenerator->stream('candidature/print/fiche.html.twig', [
            'candidature' => $candidature,
            'now' => new \DateTime('now', $timezone),
            'image' => $this->prepareImagesForPdf($candidature)
        ]);
    }
    
    #[Route('/imprimer-convocation/{id}', name: 'app_candidature_imprimer_convocation', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function imprimerConvocation(Request $request, Candidature $candidature): Response
    {
        // Vérifier le jeton
        $token = $request->query->get('token');
        $user = $this->getUser();
    
        // Si l'utilisateur n'est PAS connecté, on exige le jeton
        if (!$user) {
            if (!$token || strtoupper($token) !== strtoupper((string) $candidature->getNumero())) {
                $this->addFlash('error', 'Accès non autorisé. Veuillez vérifier votre éligibilité.');
                return $this->redirectToRoute('app_verifier_eligibilite');
            }
        } else {
            // Utilisateur connecté : contrôle d'accès selon les rôles
            if (!$this->isGranted('ROLE_ADMIN')) {
                if ($this->isGranted('ROLE_ENT') || $this->isGranted('ROLE_JURY')) {
                    $etablissementUser = $candidature->getEtablissement()?->getUser();
                    if (!$etablissementUser || $etablissementUser->getId() !== $user->getId()) {
                        throw $this->createAccessDeniedException('Vous n\'avez pas accès à la convocation de cet établissement.');
                    }
                } elseif ($this->isGranted('ROLE_CANDIDAT')) {
                    if (!$candidature->getUser() || $candidature->getUser()->getId() !== $user->getId()) {
                        throw $this->createAccessDeniedException('Vous ne pouvez consulter que votre propre convocation.');
                    }
                }
            }
        }

        // Vérifie si le candidat est bien éligible (etustatut == 2)
        if ($candidature->getEtustatut() !== 2) {
            $this->addFlash('error', 'Ce candidat n\'est pas éligible, aucune convocation disponible.');
            return $this->redirectToRoute('app_candidature_index');
        }

        // Diagnostic détaillé en cas de programmation manquante
        if (!$candidature->getEntdate() || !$candidature->getJury()) {
            $details = sprintf(
                'Entdate: %s | Jury: %s | Creneau: %s | Entlieu: %s',
                $candidature->getEntdate() ? $candidature->getEntdate()->format('Y-m-d H:i') : 'NULL',
                $candidature->getJury() ?? 'NULL',
                $candidature->getCreneauEntretien() ?? 'NULL',
                $candidature->getEntlieu() ?? 'NULL'
            );
        
            error_log('[CONVOCATION] Échec pour ' . $candidature->getNumero() . ' — ' . $details);
        
            $this->addFlash('warning', 
                'La programmation de cet entretien n\'a pas encore été effectuée pour le candidat ' 
                . $candidature->getNumero() 
                . '.'
            );
        
            return $this->redirectToRoute('app_candidature_index');
        }

        // Génère le PDF de la convocation
        return $this->pdfGenerator->stream('candidature/print/convocation.html.twig', [
            'candidature' => $candidature,
            'image'       => $this->prepareImagesForPdf($candidature)
        ]);
    }

    #[Route('/imprimer-convocation-visite/{id}', name: 'app_candidature_imprimer_convocation_visite', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function imprimerConvocationVisite(Request $request, Candidature $candidature): Response
    {
        // Vérifier le jeton
        $token = $request->query->get('token');
        $user = $this->getUser();

        // Si l'utilisateur n'est PAS connecté, on exige le jeton
        if (!$user) {
            if (!$token || strtoupper($token) !== strtoupper((string) $candidature->getNumero())) {
                $this->addFlash('error', 'Accès non autorisé. Veuillez vérifier votre éligibilité.');
                return $this->redirectToRoute('app_verifier_eligibilite');
            }
        } else {
            // Utilisateur connecté : contrôle d'accès selon les rôles
            if (!$this->isGranted('ROLE_ADMIN')) {
                if ($this->isGranted('ROLE_ENT') || $this->isGranted('ROLE_JURY')) {
                    $etablissementUser = $candidature->getEtablissement()?->getUser();
                    if (!$etablissementUser || $etablissementUser->getId() !== $user->getId()) {
                        throw $this->createAccessDeniedException('Vous n\'avez pas accès à la convocation de cet établissement.');
                    }
                } elseif ($this->isGranted('ROLE_CANDIDAT')) {
                    if (!$candidature->getUser() || $candidature->getUser()->getId() !== $user->getId()) {
                        throw $this->createAccessDeniedException('Vous ne pouvez consulter que votre propre convocation.');
                    }
                }
            }
        }

        // Vérifie que le candidat est admissible à la visite médicale
        if ($candidature->getDecisionJury() !== 2) {
            $this->addFlash('error', 'Ce candidat n\'est pas admissible à la visite médicale, aucune convocation disponible.');
            return $this->redirectToRoute('app_candidature_index');
        }

        // Vérifie que la programmation de la visite a été effectuée
        if (!$candidature->getVisdate() || !$candidature->getVislieu()) {
            $details = sprintf(
                'Visdate: %s | Vislieu: %s',
                $candidature->getVisdate() ? $candidature->getVisdate()->format('Y-m-d H:i') : 'NULL',
                $candidature->getVislieu() ?? 'NULL'
            );

            error_log('[CONVOCATION VISITE] Échec pour ' . $candidature->getNumero() . ' — ' . $details);

            $this->addFlash('warning',
                'La programmation de la visite médicale n\'a pas encore été effectuée pour le candidat '
                . $candidature->getNumero()
                . '.'
            );

            return $this->redirectToRoute('app_candidature_index');
        }

        // Génère le PDF de la convocation pour la visite médicale
        return $this->pdfGenerator->stream('candidature/print/convocation_visite.html.twig', [
            'candidature' => $candidature,
            'image'       => $this->prepareImagesForPdf($candidature)
        ]);
    }

    #[Route('/print', name: 'app_candidature_print', methods: ['GET'])]
    #[Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_ENT') or is_granted('ROLE_JURY') or is_granted('ROLE_CANDIDAT') or is_granted('ROLE_COM')")]
    public function print(Request $request): Response
    {
        $type = $request->query->get('type');
        $numero = $request->query->get('numero');

        // Le type "stat" n'a pas de rendu PDF dédié : on redirige simplement
        // vers le tableau de bord des statistiques avec les mêmes filtres.
        if ($type === 'stat') {
            return $this->printStatistics($request);
        }

        if (!$numero) {
            $this->addFlash('error', 'Numéro de candidature manquant.');
            return $this->redirectToRoute('app_candidature_impressions');
        }

        $candidature = $this->entityManager->getRepository(Candidature::class)
            ->findOneBy(['numero' => trim($numero)]);

        if (!$candidature) {
            $this->addFlash('error', 'Candidature introuvable pour ce numéro.');
            return $this->redirectToRoute('app_candidature_impressions');
        }

        // Droits : administration/communication = tout ; ENT/JURY = leur établissement ; candidat = sa candidature
        $autorise = $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_COM');
        if (!$autorise && ($this->isGranted('ROLE_ENT') || $this->isGranted('ROLE_JURY'))) {
            $etab = $candidature->getEtablissement();
            $autorise = $etab && $etab->getUser() === $this->getUser();
        }
        if (!$autorise && $this->isGranted('ROLE_CANDIDAT')) {
            $autorise = $candidature->getUser() === $this->getUser();
        }
        if (!$autorise) {
            throw $this->createAccessDeniedException();
        }

        if ($type === 'fiche') {
            $timezone = new \DateTimeZone('Africa/Abidjan');
            return $this->pdfGenerator->stream('candidature/print/fiche.html.twig', [
                'candidature' => $candidature,
                'now' => new \DateTime('now', $timezone),
                'image' => $this->prepareImagesForPdf($candidature)
            ]);
        }

        if ($type === 'convocation') {
            // Vérifier que la programmation a été faite
            if (!$candidature->getEntdate() || !$candidature->getJury()) {
                $this->addFlash('warning', 'La programmation n\'a pas encore été effectuée pour ce candidat.');
                return $this->redirectToRoute('app_candidature_impressions');
            }
            
            return $this->generateConvocationPdf($candidature);
        }

        $this->addFlash('error', 'Type d\'impression invalide.');

        return match ($type) {
            'convocation' => $candidature->getEntstatut() === 2
                ? $this->generateConvocationPdf($candidature)
                : $this->handleInvalidConvocation(),
            default => $this->redirectToRoute('app_candidature_impressions')
        };
        return $this->redirectToRoute('app_candidature_impressions');
    }

    private function generateConvocationPdf(Candidature $candidature): Response
    {
        return $this->pdfGenerator->stream('candidature/print/convocation.html.twig', [
            'candidature' => $candidature,
            'image' => $this->prepareImagesForPdf($candidature)
        ]);
    }

    private function getGlobalMetierStatistics(?Metier $metier = null): array
    {
        $qb = $this->entityManager->getRepository(Candidature::class)
            ->createQueryBuilder('c')
            ->innerJoin('c.metier', 'm')
            ->leftJoin('c.user', 'u')
            ->select(
                'm.id as metier_id',
                'COUNT(c.id) as total',
                'SUM(CASE WHEN u.sexe = :homme THEN 1 ELSE 0 END) as hommes',
                'SUM(CASE WHEN u.sexe = :femme THEN 1 ELSE 0 END) as femmes',
                'SUM(CASE WHEN c.decisionJury = 2 THEN 1 ELSE 0 END) as admissible',
                'SUM(CASE WHEN c.resultat = 2 THEN 1 ELSE 0 END) as admis'
            )
            ->setParameter('homme', 'MASCULIN')
            ->setParameter('femme', 'FEMININ')
            ->groupBy('m.id');

        $noteConditions = [];
        for ($i = 1; $i <= 13; $i++) {
            $noteConditions[] = 'c.note' . $i . ' IS NOT NULL';
        }
        $qb->addSelect('SUM(CASE WHEN ' . implode(' OR ', $noteConditions) . ' THEN 1 ELSE 0 END) as evalue');

        if ($metier) {
            $qb->andWhere('c.metier = :metier')
                ->setParameter('metier', $metier);
        }

        $results = $qb->getQuery()->getResult();
        $indexed = [];

        foreach ($results as $res) {
            $indexed[$res['metier_id']] = [
                'total' => (int) $res['total'],
                'hommes' => (int) $res['hommes'],
                'femmes' => (int) $res['femmes'],
                'admissible' => (int) $res['admissible'],
                'admis' => (int) $res['admis'],
                'evalue' => (int) $res['evalue'],
                'places' => 0
            ];
        }

        // Récupérer le total des places par métier
        $placesQb = $this->entityManager->getRepository(EtablissementMetier::class)
            ->createQueryBuilder('em')
            ->innerJoin('em.metier', 'm')
            ->select('m.id as metier_id', 'SUM(em.nbrplace) as places')
            ->groupBy('m.id');

        if ($metier) {
            $placesQb->andWhere('em.metier = :metier')
                ->setParameter('metier', $metier);
        }

        $placesResults = $placesQb->getQuery()->getResult();
        foreach ($placesResults as $res) {
            $mId = $res['metier_id'];
            if (!isset($indexed[$mId])) {
                $indexed[$mId] = [
                    'total' => 0,
                    'hommes' => 0,
                    'femmes' => 0,
                    'evalue' => 0,
                    'admissible' => 0,
                    'admis' => 0,
                    'places' => (int) $res['places']
                ];
            } else {
                $indexed[$mId]['places'] = (int) $res['places'];
            }
        }

        return $indexed;
    }

    private function getStatisticsByMetier(?Metier $metier = null): array
    {
        $metiers = $metier ? [$metier] : $this->entityManager->getRepository(Metier::class)->findAll();
        $globalStats = $this->getGlobalMetierStatistics($metier);
        $results = [];

        foreach ($metiers as $m) {
            $stats = $globalStats[$m->getId()] ?? [
                'total' => 0,
                'hommes' => 0,
                'femmes' => 0,
                'places' => 0,
                'evalue' => 0,
                'admissible' => 0,
                'admis' => 0
            ];
            $results[] = [
                'name' => $m->getNom(),
                'sum_h' => $stats['hommes'],
                'sum_f' => $stats['femmes'],
                'sum_pl' => $stats['places'],
                'sum' => $stats['total'],
                'sum_evalue' => $stats['evalue'],
                'sum_admissible' => $stats['admissible'],
                'sum_admis' => $stats['admis']
            ];
        }
        return $results;
    }

    private function printStatistics(Request $request): Response
    {
        $metierId = $request->query->get('metier');
        $metier = $metierId ? $this->entityManager->getRepository(Metier::class)->find($metierId) : null;
        $results = $this->getStatisticsByMetier($metier);

        $dirImage = $this->params->get('dir_image');
        $images = ['entete' => $this->pdfGenerator->imageToBase64($dirImage . 'pacte/logo-ministere.png')];

        return $this->pdfGenerator->stream('candidature/print/stat.html.twig', [
            'results' => $results,
            'image' => $images,
            'date' => new \DateTime()
        ]);
    }

    #[Route('/statistiques', name: 'app_candidature_stat', methods: ['GET'])]
    #[Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_ENT') or is_granted('ROLE_JURY')")]
    public function statistiques(Request $request): Response
    {
        $etablissementId = $request->query->get('etablissement');
        $etablissement = $etablissementId ? $this->entityManager->getRepository(Etablissement::class)->find($etablissementId) : null;
        $metierId = $request->query->get('metier');
        $metier = $metierId ? $this->entityManager->getRepository(Metier::class)->find($metierId) : null;

        // Pour le filtre : Admin et COM voient tout, ENT et JURY sont restreints à leur établissement
        $user = $this->getUser();
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_COM')) {
            $etablissements = $this->entityManager->getRepository(Etablissement::class)->findBy([], ['nom' => 'ASC']);
        } elseif ($this->isGranted('ROLE_ENT') || $this->isGranted('ROLE_JURY')) {
            // L'utilisateur est restreint à ses propres établissements
            $userEtablissements = $this->entityManager->getRepository(Etablissement::class)->findBy(['user' => $user]);

            if ($etablissementId) {
                $requested = $this->entityManager->getRepository(Etablissement::class)->find($etablissementId);
                if ($requested && $requested->getUser() === $user) {
                    $etablissement = $requested;
                } else {
                    $etablissement = $userEtablissements[0] ?? null;
                }
            } else {
                $etablissement = $userEtablissements[0] ?? null;
            }
            $etablissements = $etablissement ? [$etablissement] : [];
        } else {
            $etablissements = $etablissement ? [$etablissement] : [];
        }

        // Récupérer les métiers de l'établissement sélectionné (pour le filtre)
        $metiers = [];
        if ($etablissement) {
            $metiers = $this->getMetiersForEtablissement($etablissement);
        }

        // Obtenir les statistiques
        $stats = $this->getDetailedStatistics($etablissement, $metier);

        // Calculer les totaux pour les graphiques
        $totals = $this->calculateTotals($stats);

        if ($request->query->get('export') === 'true') {
            return $this->exportDetailedStatistics($stats, $etablissement, $metier);
        }

        return $this->render('candidature/stat.html.twig', [
            'etablissements' => $etablissements,
            'etablissement_selectionne' => $etablissement,
            'metiers' => $metiers,
            'metier_selectionne' => $metier,
            'stats' => $stats,
            'totals' => $totals
        ]);
    }

    #[Route('/export/stats', name: 'app_candidature_export_stats', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function exportStats(Request $request): Response
    {
        $etablissementId = $request->query->get('etablissement');
        $etablissement = $etablissementId ? $this->entityManager->getRepository(Etablissement::class)->find($etablissementId) : null;
        $metierId = $request->query->get('metier');
        $metier = $metierId ? $this->entityManager->getRepository(Metier::class)->find($metierId) : null;

        $stats = $this->getDetailedStatistics($etablissement, $metier);

        $filename = 'STATISTIQUES_CANDIDATURES_' . date('Ymd_His') . '.xlsx';

        $headers = [
            'Établissement',
            'Métier',
            'Total',
            'Hommes',
            'Femmes',
            'Éligibles',
            'Évalués',
            'Admissibles',
            'Admis',
            'Places'
        ];

        $datas = [];

        foreach ($stats as $index => $row) {
            $datas[] = [
                $row['etablissement_nom'] ?? '',
                $row['metier_nom'] ?? '',
                $row['total'] ?? 0,
                $row['hommes'] ?? 0,
                $row['femmes'] ?? 0,
                $row['eligible'] ?? 0,
                $row['evalue'] ?? 0,
                $row['admissible'] ?? 0,
                $row['admis'] ?? 0,
                $row['places'] ?? 0
            ];
        }

        return $this->spreadsheetGenerator->generate($filename, $headers, $datas);
    }

    #[Route('/etude-dossier', name: 'app_candidature_etude_dossier', methods: ['GET'])]
    #[Security("is_granted('ROLE_ENT') or is_granted('ROLE_JURY') or is_granted('ROLE_ADMIN')")]
    public function etudeDossier(): Response
    {
        $user = $this->getUser();
        $repo = $this->entityManager->getRepository(Candidature::class);

        // Construction de la requête de base
        $qbBase = $repo->createQueryBuilder('c')
            ->leftJoin('c.etablissement', 'e')
            ->leftJoin('c.metier', 'm')
            ->leftJoin('c.user', 'u')
            ->addSelect('e', 'm', 'u');

        // Filtrer par établissement pour ROLE_ENT et ROLE_JURY (sauf ROLE_ADMIN)
        if (!$this->isGranted('ROLE_ADMIN')) {
            $qbBase->andWhere('e.user = :user')
                ->setParameter('user', $user);
        }

        // --- 1. Statistiques Globale ---
        $statsQb = clone $qbBase;
        $statsData = $statsQb
            ->select(
                'SUM(CASE WHEN c.etustatut IS NULL THEN 1 ELSE 0 END) as nb_attente',
                'SUM(CASE WHEN c.etustatut = 2 THEN 1 ELSE 0 END) as nb_dossier_valide',
                'SUM(CASE WHEN c.decisionJury = 2 THEN 1 ELSE 0 END) as nb_admissible',
                'SUM(CASE WHEN c.resultat = 2 THEN 1 ELSE 0 END) as nb_admis',
                'SUM(CASE WHEN c.visstatut = 2 THEN 1 ELSE 0 END) as nb_visite',
                'SUM(CASE WHEN c.entstatut IN (1, 2) THEN 1 ELSE 0 END) as nb_entretien_effectue',
                'SUM(CASE WHEN c.etustatut = 2 AND c.entstatut IS NULL THEN 1 ELSE 0 END) as nb_entretien_non_effectue'
            )
            ->getQuery()
            ->getSingleResult();

        $stats = [
            'attente'                => (int) ($statsData['nb_attente'] ?? 0),
            'dossier_valide'         => (int) ($statsData['nb_dossier_valide'] ?? 0),
            'admissible'             => (int) ($statsData['nb_admissible'] ?? 0),
            'admis'                  => (int) ($statsData['nb_admis'] ?? 0),
            'visite'                 => (int) ($statsData['nb_visite'] ?? 0),
            'entretien_effectue'     => (int) ($statsData['nb_entretien_effectue'] ?? 0),
            'entretien_non_effectue' => (int) ($statsData['nb_entretien_non_effectue'] ?? 0),
        ];

        // --- Helper pour récuperer 5 candidats selon une condition ---
        $getTop5 = function ($conditionStr, array $params = []) use ($qbBase) {
            $qb = clone $qbBase;
            $qb->andWhere($conditionStr)
                ->orderBy('c.creation', 'DESC')
                ->setMaxResults(5);

            foreach ($params as $key => $val) {
                $qb->setParameter($key, $val);
            }

            return $qb->getQuery()->getResult();
        };

        // --- 2. Listes des 5 dernières candidatures par catégorie ---
        $candidatsAttente            = $getTop5('c.etustatut IS NULL');
        $candidatsAdmissibles        = $getTop5('c.decisionJury = 2');
        $candidatsAdmis              = $getTop5('c.resultat = 2');
        $candidatsVisiteMedicale     = $getTop5('c.visstatut = 2');
        $candidatsEntretienEffectue  = $getTop5('c.entstatut IN (1, 2)');
        $candidatsEntretienNonEffectue = $getTop5('c.etustatut = 2 AND c.entstatut IS NULL');
        $candidatsVisiteNonEffectuee = $getTop5('c.etustatut = 2 AND c.decisionJury = 2 AND (c.visstatut IS NULL OR c.visstatut != 2)');

        return $this->render('candidature/etude_dossier.html.twig', [
            'stats'                          => $stats,
            'candidats_attente'              => $candidatsAttente,
            'candidats_admissibles'          => $candidatsAdmissibles,
            'candidats_admis'                => $candidatsAdmis,
            'candidats_visite_medicale'      => $candidatsVisiteMedicale,
            'candidats_visite_non_effectuee'   => $candidatsVisiteNonEffectuee,
            'candidats_entretien_effectue'   => $candidatsEntretienEffectue,
            'candidats_entretien_non_effectue' => $candidatsEntretienNonEffectue,
        ]);
    }

    #[Route('/etude-dossier/en-attente', name: 'app_candidature_en_attente', methods: ['GET'])]
    #[Security("is_granted('ROLE_ENT') or is_granted('ROLE_JURY') or is_granted('ROLE_ADMIN')")]
    public function listAttente(Request $request, \Knp\Component\Pager\PaginatorInterface $paginator): Response
    {
        $user = $this->getUser();

        $qb = $this->entityManager->getRepository(Candidature::class)
            ->createQueryBuilder('c')
            ->leftJoin('c.etablissement', 'e')
            ->leftJoin('c.metier', 'm')
            ->leftJoin('c.user', 'u')
            ->addSelect('e', 'm', 'u')
            ->where('c.etustatut IS NULL');

        if (!$this->isGranted('ROLE_ADMIN')) {
            $qb->andWhere('e.user = :user')
                ->setParameter('user', $user);
        }

        $searchTerm = $request->query->get('q');
        if ($searchTerm) {
            $qb->andWhere('c.numero LIKE :search OR u.nom LIKE :search OR u.prenoms LIKE :search OR m.nom LIKE :search')
                ->setParameter('search', '%' . $searchTerm . '%');
        }

        $qb->orderBy('c.creation', 'DESC');

        $candidatures = $paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            20
        );

        return $this->render('candidature/en_attente.html.twig', [
            'candidatures' => $candidatures,
            'searchTerm'   => $searchTerm,
            'totalAttente' => $candidatures->getTotalItemCount(),
        ]);
    }

    #[Route('/etude-dossier/entretien-non-effectue', name: 'app_candidature_entretien_non_effectue', methods: ['GET'])]
    #[Security("is_granted('ROLE_ENT') or is_granted('ROLE_JURY') or is_granted('ROLE_ADMIN')")]
    public function listEntretienNonEffectue(Request $request, \Knp\Component\Pager\PaginatorInterface $paginator): Response
    {
        $user = $this->getUser();

        $qb = $this->entityManager->getRepository(Candidature::class)
            ->createQueryBuilder('c')
            ->leftJoin('c.etablissement', 'e')
            ->leftJoin('c.metier', 'm')
            ->leftJoin('c.user', 'u')
            ->addSelect('e', 'm', 'u')
            ->where('c.etustatut = 2')
            ->andWhere('c.entstatut IS NULL');

        if (!$this->isGranted('ROLE_ADMIN')) {
            $qb->andWhere('e.user = :user')
                ->setParameter('user', $user);
        }

        $searchTerm = $request->query->get('q');
        if ($searchTerm) {
            $qb->andWhere('c.numero LIKE :search OR u.nom LIKE :search OR u.prenoms LIKE :search OR m.nom LIKE :search')
                ->setParameter('search', '%' . $searchTerm . '%');
        }

        $qb->orderBy('c.creation', 'DESC');

        $candidatures = $paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            20
        );

        return $this->render('candidature/entretien_non_effectue.html.twig', [
            'candidatures' => $candidatures,
            'searchTerm'   => $searchTerm,
            'total'        => $candidatures->getTotalItemCount(),
        ]);
    }

    #[Route('/etude-dossier/visite-medicale-non-effectuee', name: 'app_candidature_visite_non_effectuee', methods: ['GET'])]
    #[Security("is_granted('ROLE_ENT') or is_granted('ROLE_JURY') or is_granted('ROLE_ADMIN')")]
    public function listVisiteMedicaleNonEffectuee(Request $request, \Knp\Component\Pager\PaginatorInterface $paginator): Response
    {
        $user = $this->getUser();

        $qb = $this->entityManager->getRepository(Candidature::class)
            ->createQueryBuilder('c')
            ->leftJoin('c.etablissement', 'e')
            ->leftJoin('c.metier', 'm')
            ->leftJoin('c.user', 'u')
            ->addSelect('e', 'm', 'u')
            ->where('c.decisionJury = 2')
            ->andWhere('c.visstatut IS NULL OR c.visstatut != 2');

        if (!$this->isGranted('ROLE_ADMIN')) {
            $qb->andWhere('e.user = :user')
                ->setParameter('user', $user);
        }

        $searchTerm = $request->query->get('q');
        if ($searchTerm) {
            $qb->andWhere('c.numero LIKE :search OR u.nom LIKE :search OR u.prenoms LIKE :search OR m.nom LIKE :search')
                ->setParameter('search', '%' . $searchTerm . '%');
        }

        $qb->orderBy('c.creation', 'DESC');

        $candidatures = $paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            20
        );

        return $this->render('candidature/visite_non_effectuee.html.twig', [
            'candidatures' => $candidatures,
            'searchTerm'   => $searchTerm,
            'total'        => $candidatures->getTotalItemCount(),
        ]);
    }

    #[Route('/impressions', name: 'app_candidature_impressions', methods: ['GET', 'POST'])]
    #[Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_ENT') or is_granted('ROLE_JURY') or is_granted('ROLE_CANDIDAT')")]
    public function impressions(Request $request): Response
    {
        $user = $this->getUser();
        $etablissementId = $request->query->get('etablissement');
        $etablissement = $etablissementId ? $this->entityManager->getRepository(Etablissement::class)->find($etablissementId) : null;
        $metierId = $request->query->get('metier');
        $metier = $metierId ? $this->entityManager->getRepository(Metier::class)->find($metierId) : null;

        // Gestion des accès aux établissements selon le rôle
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_COM')) {
            $etablissements = $this->entityManager->getRepository(Etablissement::class)->findBy([], ['nom' => 'ASC']);
        } else {
            // ENT / JURY : Restreint à leurs établissements
            $userEtablissements = $this->entityManager->getRepository(Etablissement::class)->findBy(['user' => $user]);
            if ($etablissementId) {
                $requested = $this->entityManager->getRepository(Etablissement::class)->find($etablissementId);
                if ($requested && $requested->getUser() === $user) {
                    $etablissement = $requested;
                } else {
                    $etablissement = $userEtablissements[0] ?? null;
                }
            } else {
                $etablissement = $userEtablissements[0] ?? null;
            }
            $etablissements = $etablissement ? [$etablissement] : [];
        }

        // Récupérer les métiers pour le filtre
        $metiers = [];
        if ($etablissement) {
            $metiers = $this->getMetiersForEtablissement($etablissement);
        }

        // GET simple : on affiche la page avec les différentes options d'impression/export
        if (!$request->isMethod('POST')) {
            return $this->render('candidature/impressions.html.twig', [
                'etablissements' => $etablissements,
                'etablissement_selectionne' => $etablissement,
                'metiers' => $metiers,
                'metier_selectionne' => $metier,
            ]);
        }

        // POST : on dispatche vers l'export demandé via le champ caché "action"
        $action = $request->request->get('action');

        return match ($action) {
            'export_liste_candidats_inscrits' => $this->exportListeCandidatsInscrits(
                $request->request->get('etablissement_liste'),
                $request->request->get('metier_liste'),
                $request->request->get('date_debut'),
                $request->request->get('date_fin')
            ),
            'export_liste_attente' => $this->exportListeAttente(
                $request->request->get('etablissement_attente'),
                $request->request->get('metier_attente'),
                $request->request->get('date_debut'),
                $request->request->get('date_fin')
            ),
            'export_liste_eligibles' => $this->exportListeEligibles(
                $request->request->get('etablissement_eligible'),
                $request->request->get('metier_eligible'),
                $request->request->get('date_debut'),
                $request->request->get('date_fin')
            ),
            'export_liste_evalues' => $this->exportListeEvalues(
                $request->request->get('etablissement_evalue'),
                $request->request->get('metier_evalue'),
                $request->request->get('date_debut'),
                $request->request->get('date_fin')
            ),
            'export_liste_admissibles_visite' => $this->exportListeAdmissiblesVisite(
                $request->request->get('etablissement_admissible_visite'),
                $request->request->get('metier_admissible_visite'),
                $request->request->get('date_debut'),
                $request->request->get('date_fin')
            ),
            'export_liste_admis' => $this->exportListeAdmis(
                $request->request->get('etablissement_admis'),
                $request->request->get('metier_admis'),
                $request->request->get('date_debut'),
                $request->request->get('date_fin')
            ),
            'export_liste_admis_definitifs' => $this->exportListeAdmisDefinitifs(
                $request->request->get('etablissement_admis_def'),
                $request->request->get('metier_admis_def'),
                $request->request->get('date_debut'),
                $request->request->get('date_fin')
            ),
            'export_zip_contrats' => $this->exportZipContrats(
                $request->request->get('etablissement_zip_contrats')
            ),
            default => $this->redirectToRoute('app_candidature_impressions'),
        };
    }

    /**
     * Retourne les IDs des métiers autorisés pour un établissement donné.
     * (getMetiersForEtablissement() renvoie des objets Metier ; ce helper
     * évite de comparer un id (int) à un tableau d'objets par erreur.)
     */
    private function getMetierIdsForEtablissement(Etablissement $etablissement): array
    {
        return array_map(
            static fn(Metier $m): int => $m->getId(),
            $this->getMetiersForEtablissement($etablissement)
        );
    }

    private function exportListeCandidatsInscrits(?string $etablissementId, ?string $metierId, ?string $dateDebut = null, ?string $dateFin = null): Response
    {
        if (!$etablissementId) {
            $this->addFlash('error', 'Veuillez sélectionner un établissement.');
            return $this->redirectToRoute('app_candidature_impressions');
        }

        $etablissement = $this->entityManager->getRepository(Etablissement::class)->find((int) $etablissementId);
        if (!$etablissement) {
            $this->addFlash('error', 'Établissement invalide.');
            return $this->redirectToRoute('app_candidature_impressions');
        }

        if ($this->isGranted('ROLE_ENT') && !$this->isGranted('ROLE_ADMIN') && $etablissement->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Établissement non autorisé.');
        }

        $qb = $this->entityManager->getRepository(Candidature::class)
            ->createQueryBuilder('c')
            ->leftJoin('c.user', 'u')
            ->leftJoin('c.etablissement', 'e')
            ->leftJoin('c.metier', 'm')
            ->addSelect('u', 'e', 'm')
            ->andWhere('c.etablissement = :etablissement')
            ->setParameter('etablissement', $etablissement)
            ->orderBy('u.nom', 'ASC')
            ->addOrderBy('u.prenoms', 'ASC');

        if ($metierId && $metierId !== 'TOUS') {
            $metierIdsAutorises = $this->getMetierIdsForEtablissement($etablissement);
            $metierSelectionneId = (int) $metierId;
            if (!in_array($metierSelectionneId, $metierIdsAutorises, true)) {
                $this->addFlash('error', 'Sélection de métier non autorisée.');
                return $this->redirectToRoute('app_candidature_impressions');
            }
            $qb->andWhere('c.metier = :metier')->setParameter('metier', $metierSelectionneId);
        }

        if ($dateDebut) {
            $qb->andWhere('c.creation >= :dateDebut')->setParameter('dateDebut', new \DateTime($dateDebut . ' 00:00:00'));
        }
        if ($dateFin) {
            $qb->andWhere('c.creation <= :dateFin')->setParameter('dateFin', new \DateTime($dateFin . ' 23:59:59'));
        }

        $candidatures = $qb->getQuery()->getResult();
        if (empty($candidatures)) {
            $this->addFlash('warning', 'Aucune candidature trouvée pour les critères sélectionnés.');
            return $this->redirectToRoute('app_candidature_impressions');
        }

        $headers = ['N°', 'Numéro', 'Nom', 'Prénoms', 'Sexe', 'Contact', 'Établissement', 'Métier', 'Diplôme'];
        $datas = [];
        foreach ($candidatures as $index => $candidature) {
            $candidat = $candidature->getUser();
            $datas[] = [
                $index + 1,
                $candidature->getNumero(),
                $candidat?->getNom() ?? '',
                $candidat?->getPrenoms() ?? '',
                $candidat?->getSexe() ?? '',
                $candidat?->getContact() ?? '',
                $candidature->getEtablissement()?->getNom() ?? '',
                $candidature->getMetier()?->getNom() ?? '',
                $candidature->getDiplome() ?? '',
            ];
        }

        $filename = 'LISTE_CANDIDATS_INSCRITS_' . date('Ymd_His') . '.xlsx';
        return $this->spreadsheetGenerator->generate($filename, $headers, $datas);
    }

    private function exportListeAttente(?string $etablissementId, ?string $metierId, ?string $dateDebut = null, ?string $dateFin = null): Response
    {
        if (!$etablissementId) {
            $this->addFlash('error', 'Veuillez sélectionner un établissement.');
            return $this->redirectToRoute('app_candidature_impressions');
        }

        $etablissement = $this->entityManager->getRepository(Etablissement::class)->find((int) $etablissementId);
        if (!$etablissement) {
            $this->addFlash('error', 'Établissement invalide.');
            return $this->redirectToRoute('app_candidature_impressions');
        }

        if ($this->isGranted('ROLE_ENT') && !$this->isGranted('ROLE_ADMIN') && $etablissement->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Établissement non autorisé.');
        }

        $qb = $this->entityManager->getRepository(Candidature::class)
            ->createQueryBuilder('c')
            ->leftJoin('c.user', 'u')
            ->leftJoin('c.etablissement', 'e')
            ->leftJoin('c.metier', 'm')
            ->addSelect('u', 'e', 'm')
            ->andWhere('c.etablissement = :etablissement')
            ->andWhere('c.etustatut IS NULL')
            ->setParameter('etablissement', $etablissement)
            ->orderBy('u.nom', 'ASC')
            ->addOrderBy('u.prenoms', 'ASC');

        if ($metierId && $metierId !== 'TOUS') {
            $metierIdsAutorises = $this->getMetierIdsForEtablissement($etablissement);
            $metierSelectionneId = (int) $metierId;
            if (!in_array($metierSelectionneId, $metierIdsAutorises, true)) {
                $this->addFlash('error', 'Sélection de métier non autorisée.');
                return $this->redirectToRoute('app_candidature_impressions');
            }
            $qb->andWhere('c.metier = :metier')->setParameter('metier', $metierSelectionneId);
        }

        if ($dateDebut) {
            $qb->andWhere('c.creation >= :dateDebut')->setParameter('dateDebut', new \DateTime($dateDebut . ' 00:00:00'));
        }
        if ($dateFin) {
            $qb->andWhere('c.creation <= :dateFin')->setParameter('dateFin', new \DateTime($dateFin . ' 23:59:59'));
        }

        $candidatures = $qb->getQuery()->getResult();
        if (empty($candidatures)) {
            $this->addFlash('warning', 'Aucun candidat en attente trouvé pour les critères sélectionnés.');
            return $this->redirectToRoute('app_candidature_impressions');
        }

        $headers = ['N°', 'Numéro', 'Nom', 'Prénoms', 'Sexe', 'Contact', 'Établissement', 'Métier'];
        $datas = [];
        foreach ($candidatures as $index => $candidature) {
            $candidat = $candidature->getUser();
            $datas[] = [
                $index + 1,
                $candidature->getNumero(),
                $candidat?->getNom() ?? '',
                $candidat?->getPrenoms() ?? '',
                $candidat?->getSexe() ?? '',
                $candidat?->getContact() ?? '',
                $candidature->getEtablissement()?->getNom() ?? '',
                $candidature->getMetier()?->getNom() ?? '',
            ];
        }

        $filename = 'LISTE_CANDIDATS_ATTENTE_' . date('Ymd_His') . '.xlsx';
        return $this->spreadsheetGenerator->generate($filename, $headers, $datas);
    }

    private function exportListeEligibles(?string $etablissementId, ?string $metierId, ?string $dateDebut = null, ?string $dateFin = null): Response
    {
        if (!$etablissementId) {
            $this->addFlash('error', 'Veuillez sélectionner un établissement.');
            return $this->redirectToRoute('app_candidature_impressions');
        }

        $etablissement = $this->entityManager->getRepository(Etablissement::class)->find((int) $etablissementId);
        if (!$etablissement) {
            $this->addFlash('error', 'Établissement invalide.');
            return $this->redirectToRoute('app_candidature_impressions');
        }

        if ($this->isGranted('ROLE_ENT') && !$this->isGranted('ROLE_ADMIN') && $etablissement->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Établissement non autorisé.');
        }

        $qb = $this->entityManager->getRepository(Candidature::class)
            ->createQueryBuilder('c')
            ->leftJoin('c.user', 'u')
            ->leftJoin('c.etablissement', 'e')
            ->leftJoin('c.metier', 'm')
            ->addSelect('u', 'e', 'm')
            ->andWhere('c.etablissement = :etablissement')
            ->andWhere('c.etustatut = 2')
            ->setParameter('etablissement', $etablissement)
            ->orderBy('u.nom', 'ASC')
            ->addOrderBy('u.prenoms', 'ASC');

        if ($metierId && $metierId !== 'TOUS') {
            $metierIdsAutorises = $this->getMetierIdsForEtablissement($etablissement);
            $metierSelectionneId = (int) $metierId;
            if (!in_array($metierSelectionneId, $metierIdsAutorises, true)) {
                $this->addFlash('error', 'Sélection de métier non autorisée.');
                return $this->redirectToRoute('app_candidature_impressions');
            }
            $qb->andWhere('c.metier = :metier')->setParameter('metier', $metierSelectionneId);
        }

        if ($dateDebut) {
            $qb->andWhere('c.creation >= :dateDebut')->setParameter('dateDebut', new \DateTime($dateDebut . ' 00:00:00'));
        }
        if ($dateFin) {
            $qb->andWhere('c.creation <= :dateFin')->setParameter('dateFin', new \DateTime($dateFin . ' 23:59:59'));
        }

        $candidatures = $qb->getQuery()->getResult();
        if (empty($candidatures)) {
            $this->addFlash('warning', 'Aucun candidat éligible trouvé pour les critères sélectionnés.');
            return $this->redirectToRoute('app_candidature_impressions');
        }

        $headers = ['N°', 'Numéro', 'Nom', 'Prénoms', 'Sexe', 'Contact', 'Établissement', 'Métier'];
        $datas = [];
        foreach ($candidatures as $index => $candidature) {
            $candidat = $candidature->getUser();
            $datas[] = [
                $index + 1,
                $candidature->getNumero(),
                $candidat?->getNom() ?? '',
                $candidat?->getPrenoms() ?? '',
                $candidat?->getSexe() ?? '',
                $candidat?->getContact() ?? '',
                $candidature->getEtablissement()?->getNom() ?? '',
                $candidature->getMetier()?->getNom() ?? '',
            ];
        }

        $filename = 'LISTE_CANDIDATS_ELIGIBLES_' . date('Ymd_His') . '.xlsx';
        return $this->spreadsheetGenerator->generate($filename, $headers, $datas);
    }

    /**
     * Liste des candidats évalués : etustatut = 2 (ont fait l'entretien de sélection)
     * ET ayant au moins une note saisie (note1 à note13).
     * Colonnes : 13 notes individuelles + Total /40 + Moyenne /4 + Statut (APTE/INAPTE).
     */
    private function exportListeEvalues(?string $etablissementId, ?string $metierId, ?string $dateDebut = null, ?string $dateFin = null): Response
    {
        if (!$etablissementId) {
            $this->addFlash('error', 'Veuillez sélectionner un établissement.');
            return $this->redirectToRoute('app_candidature_impressions');
        }

        $etablissement = $this->entityManager->getRepository(Etablissement::class)->find((int) $etablissementId);
        if (!$etablissement) {
            $this->addFlash('error', 'Établissement invalide.');
            return $this->redirectToRoute('app_candidature_impressions');
        }

        if ($this->isGranted('ROLE_ENT') && !$this->isGranted('ROLE_ADMIN') && $etablissement->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Établissement non autorisé.');
        }

        $qb = $this->entityManager->getRepository(Candidature::class)
            ->createQueryBuilder('c')
            ->leftJoin('c.user', 'u')
            ->leftJoin('c.etablissement', 'e')
            ->leftJoin('c.metier', 'm')
            ->addSelect('u', 'e', 'm')
            ->andWhere('c.etablissement = :etablissement')
            ->andWhere('c.etustatut = 2')
            ->andWhere(
                'c.note1 IS NOT NULL OR c.note2 IS NOT NULL OR c.note3 IS NOT NULL OR c.note4 IS NOT NULL OR ' .
                'c.note5 IS NOT NULL OR c.note6 IS NOT NULL OR c.note7 IS NOT NULL OR c.note8 IS NOT NULL OR ' .
                'c.note9 IS NOT NULL OR c.note10 IS NOT NULL OR c.note11 IS NOT NULL OR c.note12 IS NOT NULL OR c.note13 IS NOT NULL'
            )
            ->setParameter('etablissement', $etablissement)
            ->orderBy('u.nom', 'ASC')
            ->addOrderBy('u.prenoms', 'ASC');

        if ($metierId && $metierId !== 'TOUS') {
            $metierIdsAutorises = $this->getMetierIdsForEtablissement($etablissement);
            $metierSelectionneId = (int) $metierId;
            if (!in_array($metierSelectionneId, $metierIdsAutorises, true)) {
                $this->addFlash('error', 'Sélection de métier non autorisée.');
                return $this->redirectToRoute('app_candidature_impressions');
            }
            $qb->andWhere('c.metier = :metier')->setParameter('metier', $metierSelectionneId);
        }

        if ($dateDebut) {
            $qb->andWhere('c.creation >= :dateDebut')->setParameter('dateDebut', new \DateTime($dateDebut . ' 00:00:00'));
        }
        if ($dateFin) {
            $qb->andWhere('c.creation <= :dateFin')->setParameter('dateFin', new \DateTime($dateFin . ' 23:59:59'));
        }

        $candidatures = $qb->getQuery()->getResult();
        if (empty($candidatures)) {
            $this->addFlash('warning', 'Aucun candidat évalué trouvé pour les critères sélectionnés.');
            return $this->redirectToRoute('app_candidature_impressions');
        }

        // En-têtes : infos candidat + 13 notes + Total + Moyenne + Statut
        $headers = [
            'N°',
            'Numéro',
            'Nom',
            'Prénoms',
            'Sexe',
            'Contact',
            'Établissement',
            'Métier',
            'Note 1 /4',
            'Note 2 /3',
            'Note 3 /5',
            'Note 4 /6',
            'Note 5 /6',
            'Note 6 /2',
            'Note 7 /2',
            'Note 8 /3',
            'Note 9 /2',
            'Note 10 /2',
            'Note 11 /2',
            'Note 12 /2',
            'Note 13 /2',
            'Total /40',
            'Moyenne /4',
            'Statut',
        ];

        $datas = [];
        foreach ($candidatures as $index => $candidature) {
            $candidat = $candidature->getUser();

            // Récupération des 13 notes (0 si null)
            $notes = [];
            $total = 0.0;
            for ($i = 1; $i <= 13; $i++) {
                $getter = 'getNote' . $i;
                $valeur = $candidature->$getter();
                $notes[$i] = $valeur !== null ? (float) $valeur : 0;
                $total += $valeur ?? 0;
            }

            $moyenne = round($total / 10, 2);
            $statut = $moyenne >= 2 ? 'APTE' : 'INAPTE';

            $datas[] = [
                $index + 1,
                $candidature->getNumero(),
                $candidat?->getNom() ?? '',
                $candidat?->getPrenoms() ?? '',
                $candidat?->getSexe() ?? '',
                $candidat?->getContact() ?? '',
                $candidature->getEtablissement()?->getNom() ?? '',
                $candidature->getMetier()?->getNom() ?? '',
                $notes[1],
                $notes[2],
                $notes[3],
                $notes[4],
                $notes[5],
                $notes[6],
                $notes[7],
                $notes[8],
                $notes[9],
                $notes[10],
                $notes[11],
                $notes[12],
                $notes[13],
                $total,
                $moyenne,
                $statut,
            ];
        }

        $filename = 'LISTE_CANDIDATS_EVALUES_' . date('Ymd_His') . '.xlsx';
        return $this->spreadsheetGenerator->generate($filename, $headers, $datas);
    }

    private function exportListeAdmissiblesVisite(?string $etablissementId, ?string $metierId, ?string $dateDebut = null, ?string $dateFin = null): Response
    {
        if (!$etablissementId) {
            $this->addFlash('error', 'Veuillez sélectionner un établissement.');
            return $this->redirectToRoute('app_candidature_impressions');
        }

        $etablissement = $this->entityManager->getRepository(Etablissement::class)->find((int) $etablissementId);
        if (!$etablissement) {
            $this->addFlash('error', 'Établissement invalide.');
            return $this->redirectToRoute('app_candidature_impressions');
        }

        if ($this->isGranted('ROLE_ENT') && !$this->isGranted('ROLE_ADMIN') && $etablissement->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Établissement non autorisé.');
        }

        // Admissibles à la visite médicale = candidats dont entstatut = 2 (présents à l'entretien / admissibles)
        $qb = $this->entityManager->getRepository(Candidature::class)
            ->createQueryBuilder('c')
            ->leftJoin('c.user', 'u')
            ->leftJoin('c.etablissement', 'e')
            ->leftJoin('c.metier', 'm')
            ->addSelect('u', 'e', 'm')
            ->andWhere('c.etablissement = :etablissement')
            ->andWhere('c.decisionJury = 2')
            ->setParameter('etablissement', $etablissement)
            ->orderBy('u.nom', 'ASC')
            ->addOrderBy('u.prenoms', 'ASC');

        if ($metierId && $metierId !== 'TOUS') {
            $metierIdsAutorises = $this->getMetierIdsForEtablissement($etablissement);
            $metierSelectionneId = (int) $metierId;
            if (!in_array($metierSelectionneId, $metierIdsAutorises, true)) {
                $this->addFlash('error', 'Sélection de métier non autorisée.');
                return $this->redirectToRoute('app_candidature_impressions');
            }
            $qb->andWhere('c.metier = :metier')->setParameter('metier', $metierSelectionneId);
        }

        if ($dateDebut) {
            $qb->andWhere('c.entdate >= :dateDebut')->setParameter('dateDebut', new \DateTime($dateDebut . ' 00:00:00'));
        }
        if ($dateFin) {
            $qb->andWhere('c.entdate <= :dateFin')->setParameter('dateFin', new \DateTime($dateFin . ' 23:59:59'));
        }

        $candidatures = $qb->getQuery()->getResult();
        if (empty($candidatures)) {
            $this->addFlash('warning', 'Aucun candidat admissible pour la visite médicale trouvé pour les critères sélectionnés.');
            return $this->redirectToRoute('app_candidature_impressions');
        }

        $headers = ['N°', 'Numéro', 'Nom', 'Prénoms', 'Sexe', 'Contact', 'Date Naissance', 'Lieu Naissance', 'Établissement', 'Métier', 'Jury', 'Date entretien'];
        $datas = [];
        foreach ($candidatures as $index => $candidature) {
            $candidat = $candidature->getUser();
            $datas[] = [
                $index + 1,
                $candidature->getNumero(),
                $candidat?->getNom() ?? '',
                $candidat?->getPrenoms() ?? '',
                $candidat?->getSexe() ?? '',
                $candidat?->getContact() ?? '',
                $candidat?->getDateNaissance()?->format('d/m/Y') ?? '',
                $candidat?->getLieuNaissance() ?? '',
                $candidature->getEtablissement()?->getNom() ?? '',
                $candidature->getMetier()?->getNom() ?? '',
                $candidature->getJury() !== null ? 'Jury ' . $candidature->getJury() : 'Non défini',
                $candidature->getEntdate() ? $candidature->getEntdate()->format('d/m/Y H:i') : '',
            ];
        }

        $filename = 'LISTE_CANDIDATS_ADMISSIBLES_VISITE_MEDICALE_' . date('Ymd_His') . '.xlsx';
        return $this->spreadsheetGenerator->generate($filename, $headers, $datas);
    }

    private function exportListeAdmis(?string $etablissementId, ?string $metierId, ?string $dateDebut = null, ?string $dateFin = null): Response
    {
        if (!$etablissementId) {
            $this->addFlash('error', 'Veuillez sélectionner un établissement.');
            return $this->redirectToRoute('app_candidature_impressions');
        }

        $etablissement = $this->entityManager->getRepository(Etablissement::class)->find((int) $etablissementId);
        if (!$etablissement) {
            $this->addFlash('error', 'Établissement invalide.');
            return $this->redirectToRoute('app_candidature_impressions');
        }

        if ($this->isGranted('ROLE_ENT') && !$this->isGranted('ROLE_ADMIN') && $etablissement->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Établissement non autorisé.');
        }

        $qb = $this->entityManager->getRepository(Candidature::class)
            ->createQueryBuilder('c')
            ->leftJoin('c.user', 'u')
            ->leftJoin('c.etablissement', 'e')
            ->leftJoin('c.metier', 'm')
            ->addSelect('u', 'e', 'm')
            ->andWhere('c.etablissement = :etablissement')
            ->andWhere('c.resultat = 2')
            ->setParameter('etablissement', $etablissement)
            ->orderBy('u.nom', 'ASC')
            ->addOrderBy('u.prenoms', 'ASC');

        if ($metierId && $metierId !== 'TOUS') {
            $metierIdsAutorises = $this->getMetierIdsForEtablissement($etablissement);
            $metierSelectionneId = (int) $metierId;
            if (!in_array($metierSelectionneId, $metierIdsAutorises, true)) {
                $this->addFlash('error', 'Sélection de métier non autorisée.');
                return $this->redirectToRoute('app_candidature_impressions');
            }
            $qb->andWhere('c.metier = :metier')->setParameter('metier', $metierSelectionneId);
        }

        if ($dateDebut) {
            $qb->andWhere('c.creation >= :dateDebut')->setParameter('dateDebut', new \DateTime($dateDebut . ' 00:00:00'));
        }
        if ($dateFin) {
            $qb->andWhere('c.creation <= :dateFin')->setParameter('dateFin', new \DateTime($dateFin . ' 23:59:59'));
        }

        $candidatures = $qb->getQuery()->getResult();
        if (empty($candidatures)) {
            $this->addFlash('warning', 'Aucun candidat admis trouvé pour les critères sélectionnés.');
            return $this->redirectToRoute('app_candidature_impressions');
        }

        $headers = ['N°', 'Numéro', 'Nom', 'Prénoms', 'Sexe', 'Contact', 'Date Naissance', 'Lieu Naissance', 'Établissement', 'Métier'];
        $datas = [];
        foreach ($candidatures as $index => $candidature) {
            $candidat = $candidature->getUser();
            $datas[] = [
                $index + 1,
                $candidature->getNumero(),
                $candidat?->getNom() ?? '',
                $candidat?->getPrenoms() ?? '',
                $candidat?->getSexe() ?? '',
                $candidat?->getContact() ?? '',
                $candidat?->getDateNaissance()?->format('d/m/Y') ?? '',
                $candidat?->getLieuNaissance() ?? '',
                $candidature->getEtablissement()?->getNom() ?? '',
                $candidature->getMetier()?->getNom() ?? '',
            ];
        }

        $filename = 'LISTE_CANDIDATS_ADMIS_' . date('Ymd_His') . '.xlsx';
        return $this->spreadsheetGenerator->generate($filename, $headers, $datas);
    }

    private function exportListeAdmisDefinitifs(?string $etablissementId, ?string $metierId, ?string $dateDebut = null, ?string $dateFin = null): Response
    {
        $etablissement = null;

        if ($this->isGranted('ROLE_ADMIN')) {
            if (!$etablissementId) {
                $this->addFlash('error', 'Veuillez sélectionner un établissement.');
                return $this->redirectToRoute('app_candidature_impressions');
            }
            if ($etablissementId !== 'TOUS') {
                $etablissement = $this->entityManager->getRepository(Etablissement::class)->find((int) $etablissementId);
                if (!$etablissement) {
                    $this->addFlash('error', 'Établissement invalide.');
                    return $this->redirectToRoute('app_candidature_impressions');
                }
            }
        } else {
            $etablissement = $this->entityManager->getRepository(Etablissement::class)->findOneBy(['user' => $this->getUser()]);
            if (!$etablissement) {
                $this->addFlash('warning', 'Votre compte n\'est pas associé à un établissement.');
                return $this->redirectToRoute('app_candidature_impressions');
            }
        }

        $qb = $this->entityManager->getRepository(Admis::class)
            ->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')
            ->leftJoin('a.etablissement', 'e')
            ->leftJoin('a.metier', 'm')
            ->leftJoin('a.candidature', 'c')
            ->select(
                'a.numeroInscription',
                'c.numero as candidatureNumero',
                'u.nom as userNom',
                'u.prenoms as userPrenoms',
                'u.contact as userContact',
                'u.dateNaissance as userDateNaissance',
                'u.lieuNaissance as userLieuNaissance',
                'e.nom as etablissementNom',
                'm.nom as metierNom',
                'a.dateInscription',
                'a.confirme'
            )
            ->orderBy('u.nom', 'ASC')
            ->addOrderBy('u.prenoms', 'ASC');

        if ($etablissement) {
            $qb->andWhere('a.etablissement = :etablissement')->setParameter('etablissement', $etablissement);
        }

        if (class_exists(AdmisAbandonsConfig::class)) {
            AdmisAbandonsConfig::applyFilter($qb);
        }

        if ($metierId && $metierId !== 'TOUS') {
            $metierSelectionneId = (int) $metierId;
            if ($etablissement) {
                $metierIdsAutorises = $this->getMetierIdsForEtablissement($etablissement);
                if (!in_array($metierSelectionneId, $metierIdsAutorises, true)) {
                    $this->addFlash('error', 'Sélection de métier non autorisée.');
                    return $this->redirectToRoute('app_candidature_impressions');
                }
            }
            $qb->andWhere('a.metier = :metier')->setParameter('metier', $metierSelectionneId);
        }

        if ($dateDebut) {
            $qb->andWhere('c.creation >= :dateDebut')->setParameter('dateDebut', new \DateTime($dateDebut . ' 00:00:00'));
        }
        if ($dateFin) {
            $qb->andWhere('c.creation <= :dateFin')->setParameter('dateFin', new \DateTime($dateFin . ' 23:59:59'));
        }

        $admisDefinitifs = $qb->getQuery()->getArrayResult();
        if (empty($admisDefinitifs)) {
            $this->addFlash('warning', 'Aucun candidat admis définitif trouvé pour les critères sélectionnés.');
            return $this->redirectToRoute('app_candidature_impressions');
        }

        $headers = ['N°', 'N° Inscription', 'N° Candidature', 'Nom', 'Prénoms', 'Sexe', 'Contact', 'Date Naissance', 'Lieu Naissance', 'Établissement', 'Métier', 'Date Inscription', 'Confirmé'];
        $datas = [];
        foreach ($admisDefinitifs as $index => $row) {
            $dateNaissanceStr = $row['userDateNaissance'] instanceof \DateTimeInterface
                ? $row['userDateNaissance']->format('d/m/Y')
                : '';
            $dateInscriptionStr = $row['dateInscription'] instanceof \DateTimeInterface
                ? $row['dateInscription']->format('d/m/Y H:i')
                : '';

            $datas[] = [
                $index + 1,
                $row['numeroInscription'] ?? '',
                $row['candidatureNumero'] ?? '',
                $row['userNom'] ?? '',
                $row['userPrenoms'] ?? '',
                $row['userSexe'] ?? '',
                $row['userContact'] ?? '',
                $dateNaissanceStr,
                $row['userLieuNaissance'] ?? '',
                $row['etablissementNom'] ?? '',
                $row['metierNom'] ?? '',
                $dateInscriptionStr,
                $row['confirme'] ? 'Oui' : 'Non',
            ];
        }

        $filename = 'LISTE_CANDIDATS_ADMIS_DEFINITIFS_' . date('Ymd_His') . '.xlsx';
        return $this->spreadsheetGenerator->generate($filename, $headers, $datas);
    }

    private function exportZipContrats(?string $etablissementId): Response
    {
        $etablissement = null;
        $qbContrats = $this->entityManager->getRepository(Admis::class)->createQueryBuilder('a');

        if ($this->isGranted('ROLE_ADMIN')) {
            if (!$etablissementId) {
                $this->addFlash('error', 'Veuillez sélectionner un établissement.');
                return $this->redirectToRoute('app_candidature_impressions');
            }
            if ($etablissementId !== 'TOUS') {
                $etablissement = $this->entityManager->getRepository(Etablissement::class)->find((int) $etablissementId);
                if (!$etablissement) {
                    $this->addFlash('error', 'Établissement invalide ou non trouvé.');
                    return $this->redirectToRoute('app_candidature_impressions');
                }
                $qbContrats->andWhere('a.etablissement = :etablissement')->setParameter('etablissement', $etablissement);
            }
        } else {
            $etablissement = $this->entityManager->getRepository(Etablissement::class)->findOneBy(['user' => $this->getUser()]);
            if (!$etablissement) {
                $this->addFlash('error', 'Établissement invalide ou non trouvé.');
                return $this->redirectToRoute('app_candidature_impressions');
            }
            $qbContrats->andWhere('a.etablissement = :etablissement')->setParameter('etablissement', $etablissement);
        }

        if (class_exists(AdmisAbandonsConfig::class)) {
            AdmisAbandonsConfig::applyFilter($qbContrats);
        }
        $admisList = $qbContrats->getQuery()->getResult();

        $zip = new \ZipArchive();
        $zipName = 'contrats_' . ($etablissement ? $etablissement->getNom() : 'tous') . '_' . date('Ymd_His') . '.zip';
        $zipPath = sys_get_temp_dir() . '/' . uniqid('zip_') . '.zip';

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            $this->addFlash('error', 'Impossible de créer le fichier ZIP.');
            return $this->redirectToRoute('app_candidature_impressions');
        }

        $basePath = $this->getParameter('kernel.project_dir') . '/public/uploads/contrats/';
        $hasFiles = false;

        foreach ($admisList as $admis) {
            $fichier = method_exists($admis, 'getFichierContrat') ? $admis->getFichierContrat() : null;
            if ($fichier) {
                $fullPath = $basePath . $fichier;
                if (file_exists($fullPath)) {
                    $zip->addFile($fullPath, basename($fichier));
                    $hasFiles = true;
                }
            }
        }

        $zip->close();

        if (!$hasFiles) {
            $this->addFlash('warning', 'Aucun contrat trouvé pour ' . ($etablissement ? 'cet établissement' : 'ces établissements') . '.');
            if (file_exists($zipPath)) {
                unlink($zipPath);
            }
            return $this->redirectToRoute('app_candidature_impressions');
        }

        $response = new \Symfony\Component\HttpFoundation\BinaryFileResponse($zipPath);
        $response->setContentDisposition(\Symfony\Component\HttpFoundation\ResponseHeaderBag::DISPOSITION_ATTACHMENT, $zipName);
        $response->deleteFileAfterSend(true);
        return $response;
    }

    #[Route('/etablissement/{id}/metiers', name: 'app_candidature_etablissement_metiers', methods: ['GET'])]
    public function getMetiersParEtablissement(Etablissement $etablissement): JsonResponse
    {
        $metiers = $this->getMetiersWithDetails($etablissement);

        return new JsonResponse($metiers);
    }

    // ==================== MÉTHODES PRIVÉES ====================

    private function countUserCandidatures(User $user, ?SessionRecrutement $session = null): int
    {
        $criteria = ['user' => $user];
        if ($session) {
            $criteria['session'] = $session;
        }
        return $this->entityManager->getRepository(Candidature::class)->count($criteria);
    }
    private function getDocumentTypeForField(string $field): string
    {
        $map = [
            'fpiece' => 'PIECE_IDENTITE',
            'fniveau' => 'JUSTIFICATIF_NIVEAU',
            'fautre' => 'CARTE_CMU'
        ];
        return $map[$field] ?? $field;
    }

    private function createCandidature(?User $user): Candidature
    {
        $candidature = new Candidature();
        if ($user) {
            $candidature->setUser($user);
        }

        // Définit la date de création sur le fuseau horaire de Côte d'Ivoire (Africa/Abidjan)
        $timezone = new \DateTimeZone('Africa/Abidjan');
        $candidature->setCreation(new \DateTime('now', $timezone));

        return $candidature;
    }

    private function prefillFromRequest(Candidature $candidature, Request $request): void
    {
        if ($metierId = $request->query->get('metier')) {
            $metier = $this->entityManager->getRepository(Metier::class)->find($metierId);
            if ($metier) {
                $candidature->setMetier($metier);
            }
        }

        if ($etablissementId = $request->query->get('etablissement')) {
            $etablissement = $this->entityManager->getRepository(Etablissement::class)->find($etablissementId);
            if ($etablissement) {
                $candidature->setEtablissement($etablissement);
            }
        }

        if ($diplome = $request->query->get('diplome')) {
            $candidature->setDiplome($diplome);
        }
    }

    private function displayFormErrors($form): void
    {
        if ($form->isSubmitted() && !$form->isValid()) {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }
    }

    private function handleUploadedFiles(Candidature $candidature, array $fichiers): void
    {
        $dir = $this->params->get('dir_media') . $candidature->getNumero() . '/';
        $this->fileUploader->mkdir($dir);

        $fields = ['fpiece', 'fniveau', 'fautre'];

        foreach ($fields as $field) {
            $file = $fichiers[$field] ?? null;

            if ($file) {
                $getter = 'get' . ucfirst($field);
                $setter = 'set' . ucfirst($field);

                // Supprimer l'ancien fichier s'il existe
                $oldFileName = $candidature->$getter();
                if ($oldFileName && file_exists($dir . $oldFileName)) {
                    unlink($dir . $oldFileName);
                }

                // Générer un nom unique et déplacer le fichier
                $fileName = uniqid() . '.' . $file->guessExtension();
                $file->move($dir, $fileName);

                // Assigner le nom du fichier au champ correspondant
                $candidature->$setter($fileName);
            }
        }
    }

    private function generateNumero(Candidature $candidature): string
    {
        $session = $candidature->getSession();
        $yearShort = substr((string) $session->getAnnee(), -2);
        $vagueStr = str_pad((string) $session->getNumeroVague(), 2, '0', STR_PAD_LEFT);
        $idStr = str_pad((string) $candidature->getId(), 4, '0', STR_PAD_LEFT);

        return PacteConfig::NUMERO_PREFIX . $yearShort . $vagueStr . $idStr;
    }
    private function deleteCandidature(Candidature $candidature): void
    {
        $this->fileUploader->remove($this->params->get('dir_media') . $candidature->getNumero());
        $this->entityManager->remove($candidature);
        $this->entityManager->flush();
    }

    private function getMetiersForEtablissement(Etablissement $etablissement): array
    {
        $etablissementMetiers = $this->entityManager->getRepository(EtablissementMetier::class)
            ->findBy(['etablissement' => $etablissement]);

        $metiers = [];
        foreach ($etablissementMetiers as $etablissementMetier) {
            $metiers[] = $etablissementMetier->getMetier();
        }

        return $metiers;
    }

    private function getMetiersWithDetails(Etablissement $etablissement): array
    {
        $etabMetiers = $this->entityManager->getRepository(EtablissementMetier::class)
            ->createQueryBuilder('em')
            ->innerJoin('em.metier', 'm')
            ->where('em.etablissement = :etablissement')
            ->andWhere('em.estActif = true')
            ->setParameter('etablissement', $etablissement)
            ->orderBy('m.nom', 'ASC')
            ->select('em', 'm')
            ->getQuery()
            ->getResult();

        return array_map(fn($em) => [
            'id' => $em->getMetier()->getId(),
            'nom' => $em->getMetier()->getNom(),
            'secteur_nom' => null,
            'nbrplace' => $em->getNbrplace(),
            'niveau' => $em->getNiveauRequis() ?: 'CAP - BT - BTS',
            'duree' => $em->getDureeFormation()
        ], $etabMetiers);
    }

    private function getDetailedStatistics(?Etablissement $etablissement = null, ?Metier $metier = null): array
    {
        $qb = $this->entityManager->getRepository(Candidature::class)
            ->createQueryBuilder('c')
            ->innerJoin('c.etablissement', 'e')
            ->innerJoin('c.metier', 'm')
            ->leftJoin('c.user', 'u')
            ->select(
                'm.id as metier_id',
                'm.nom as metier_nom',
                'e.id as etablissement_id',
                'e.nom as etablissement_nom',
                'COUNT(c.id) as total',
                'SUM(CASE WHEN c.etustatut = 2 THEN 1 ELSE 0 END) as eligible',
                'SUM(CASE WHEN c.entstatut IS NOT NULL OR c.resultat IS NOT NULL THEN 1 ELSE 0 END) as evalue',
                'SUM(CASE WHEN c.decisionJury = 2 THEN 1 ELSE 0 END) as admissible',
                'SUM(CASE WHEN c.resultat = 2 THEN 1 ELSE 0 END) as admis',
                'SUM(CASE WHEN u.sexe = :homme THEN 1 ELSE 0 END) as hommes',
                'SUM(CASE WHEN u.sexe = :femme THEN 1 ELSE 0 END) as femmes'
            )
            ->setParameter('homme', 'MASCULIN')
            ->setParameter('femme', 'FEMININ')
            ->groupBy('e.id, m.id')
            ->orderBy('e.nom, m.nom');

        if ($etablissement) {
            $qb->andWhere('c.etablissement = :etablissement')
                ->setParameter('etablissement', $etablissement);
        }

        if ($metier) {
            $qb->andWhere('c.metier = :metier')
                ->setParameter('metier', $metier);
        }

        return $qb->getQuery()->getResult();
    }

    private function calculateTotals(array $stats): array
    {
        $totals = [
            'total'      => 0,
            'eligible'   => 0,
            'non_eligible' => 0,
            'evalue'     => 0,
            'non_evalue'   => 0,
            'admissible' => 0,
            'admis'      => 0,
            'hommes'     => 0,
            'femmes'     => 0
        ];

        foreach ($stats as $stat) {
            $totals['total']      += $stat['total'] ?? 0;
            $totals['eligible']   += $stat['eligible'] ?? 0;
            $totals['non_eligible'] += $stat['non_eligible'] ?? (($stat['total'] ?? 0) - ($stat['eligible'] ?? 0));
            $totals['evalue']     += $stat['evalue'] ?? 0;
            $totals['admissible'] += $stat['admissible'] ?? 0;
            $totals['admis']      += $stat['admis'] ?? 0;
            $totals['hommes']     += $stat['hommes'] ?? 0;
            $totals['femmes']     += $stat['femmes'] ?? 0;
        }

        // Calcul dynamique de non_evalue si non renvoyé par la requête
        $totals['non_evalue'] = $totals['total'] - $totals['evalue'];

        return $totals;
    }

    private function exportDetailedStatistics(array $stats, ?Etablissement $etablissement, ?Metier $metier): Response
    {
        $headers = [
            'Établissement',
            'Métier',
            'Total',
            'Hommes',
            'Femmes',
            'Éligibles',
            'Évalués',
            'Admissibles',
            'Admis',
            'Places'
        ];

        $datas = [];
        foreach ($stats as $stat) {
            $datas[] = [
                $stat['etablissement_nom'] ?? '',
                $stat['metier_nom'] ?? '',
                $stat['total'] ?? 0,
                $stat['hommes'] ?? 0,
                $stat['femmes'] ?? 0,
                $stat['eligible'] ?? 0,
                $stat['evalue'] ?? 0,
                $stat['admissible'] ?? 0,
                $stat['admis'] ?? 0,
                $stat['places'] ?? 0
            ];
        }

        $filename = 'STATISTIQUES';
        if ($etablissement) {
            $filename .= '_' . $etablissement->getNom();
        }
        if ($metier) {
            $filename .= '_' . $metier->getNom();
        }
        $filename .= '_' . date('Ymd_His') . '.xlsx';

        return $this->spreadsheetGenerator->generate($filename, $headers, $datas);
    }

    private function prepareImagesForPdf(Candidature $candidature): array
    {
        $dirImage = $this->params->get('dir_image');

        $entetePath = file_exists($dirImage . 'pacte/logo-ministere.png')
            ? $dirImage . 'pacte/logo-ministere.png'
            : $dirImage . 'entete_generique_e2c.png';

        $logoPath = file_exists($dirImage . 'pacte/logo-pacte.png')
            ? $dirImage . 'pacte/logo-pacte.png'
            : $dirImage . 'pacte.png';

        return [
            'entete' => $this->pdfGenerator->imageToBase64($entetePath),
            'logo'   => $this->pdfGenerator->imageToBase64($logoPath)
        ];
    }
    private function handleInvalidConvocation(): Response
    {
        $this->addFlash('error', 'Ce candidat n\'est pas admissible, aucune convocation disponible');
        return $this->redirectToRoute('app_candidature_impressions');
    }
}