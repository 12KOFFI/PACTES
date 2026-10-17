<?php

namespace App\Controller;

use App\Entity\SessionRecrutement;
use App\Form\SessionRecrutementType;
use App\Service\SessionContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/session-recrutement')]
class SessionRecrutementController extends AbstractController
{
    public function __construct(private EntityManagerInterface $entityManager, private SessionContext $sessionContext) {}

    #[Route('/', name: 'app_session_recrutement_index', methods: ['GET'])]
    public function index(): Response
    {
        if ($accessDenied = $this->accessDeniedResponse()) {
            return $accessDenied;
        }

        $sessions = $this->entityManager->getRepository(SessionRecrutement::class)
            ->findBy([], ['annee' => 'DESC', 'id' => 'DESC']);

        return $this->render('session_recrutement/index.html.twig', [
            'sessions' => $sessions,
            'counts' => $this->countCandidaturesParSession(),
        ]);
    }

    #[Route('/new', name: 'app_session_recrutement_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        if ($accessDenied = $this->accessDeniedResponse()) {
            return $accessDenied;
        }

        $session = new SessionRecrutement();
        $session->setCreatedBy($this->getUser());
        // Suggestion automatique du prochain numéro de vague pour l'année courante (modifiable par l'agent)
        $session->setNumeroVague($this->suggestNextNumeroVague($session->getAnnee()));

        $form = $this->createForm(SessionRecrutementType::class, $session);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($erreurs = $this->validateSession($session)) {
                foreach ($erreurs as $erreur) {
                    $this->addFlash('error', $erreur);
                }

                return $this->render('session_recrutement/form.html.twig', [
                    'session' => $session,
                    'form' => $form,
                ]);
            }

            if ($session->isActive()) {
                $this->deactivateOtherSessions($session);
            }

            $this->entityManager->persist($session);
            $this->entityManager->flush();
            $this->addFlash('success', 'Session de recrutement créée avec succès.');
            return $this->redirectToRoute('app_session_recrutement_index');
        }

        return $this->render('session_recrutement/form.html.twig', [
            'session' => $session,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_session_recrutement_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, SessionRecrutement $session): Response
    {
        if ($accessDenied = $this->accessDeniedResponse()) {
            return $accessDenied;
        }

        $form = $this->createForm(SessionRecrutementType::class, $session);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($erreurs = $this->validateSession($session)) {
                foreach ($erreurs as $erreur) {
                    $this->addFlash('error', $erreur);
                }

                return $this->render('session_recrutement/form.html.twig', [
                    'session' => $session,
                    'form' => $form,
                ]);
            }

            if ($session->isActive()) {
                $this->deactivateOtherSessions($session);
            }

            $session->setUpdatedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
            $this->addFlash('success', 'Session de recrutement modifiée avec succès.');
            return $this->redirectToRoute('app_session_recrutement_index');
        }

        return $this->render('session_recrutement/form.html.twig', [
            'session' => $session,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_session_recrutement_delete', methods: ['POST'])]
    public function delete(Request $request, SessionRecrutement $session): Response
    {
        if ($accessDenied = $this->accessDeniedResponse()) {
            return $accessDenied;
        }

        if (!$this->isCsrfTokenValid('delete_session_' . $session->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if ($this->countCandidaturesParSession()[$session->getId()] ?? 0) {
            $this->addFlash('error', 'Cette session contient des candidatures et ne peut pas être supprimée.');
        } else {
            $this->entityManager->remove($session);
            $this->entityManager->flush();
            $this->addFlash('success', 'Session supprimée avec succès.');
        }

        return $this->redirectToRoute('app_session_recrutement_index');
    }

    /**
     * @return string[] messages d'erreur (vide si la session est valide)
     */
    private function validateSession(SessionRecrutement $session): array
    {
        $erreurs = [];
        $ouverture = $session->getDateOuverture();
        $cloture = $session->getDateCloture();

        if ($ouverture && $cloture && $cloture <= $ouverture) {
            $erreurs[] = 'La date de clôture doit être postérieure à la date d\'ouverture.';
        }

        if ($session->getAgeMin() !== null && $session->getAgeMax() !== null && $session->getAgeMin() > $session->getAgeMax()) {
            $erreurs[] = 'L\'âge minimum ne peut pas dépasser l\'âge maximum.';
        }

        $doublon = $this->entityManager->getRepository(SessionRecrutement::class)
            ->findOneBy(['annee' => $session->getAnnee(), 'numeroVague' => $session->getNumeroVague()]);
        if ($doublon && $doublon->getId() !== $session->getId()) {
            $erreurs[] = 'Une session existe déjà pour cette année et ce numéro de vague.';
        }

        if ($session->isActive()) {
            if ($session->getStatut() !== SessionRecrutement::STATUT_OUVERTE) {
                $erreurs[] = 'Une session active doit avoir le statut « Ouverte ».';
            }
            if (!$ouverture || !$cloture) {
                $erreurs[] = 'Les dates d\'ouverture et de clôture sont obligatoires pour activer une session.';
            } elseif ($cloture < new \DateTime()) {
                $erreurs[] = 'La date de clôture est déjà dépassée : la session ne peut pas être activée.';
            }
            if (!$session->getDateReferenceAge() || $session->getAgeMin() === null || $session->getAgeMax() === null) {
                $erreurs[] = 'La date de référence et les bornes d\'âge sont obligatoires pour activer une session.';
            }
        }

        return $erreurs;
    }

    /**
     * Nombre de candidatures par session, tous périmètres confondus (le filtre de session de
     * consultation est suspendu pour ce calcul).
     *
     * @return array<int,int>
     */
    private function countCandidaturesParSession(): array
    {
        $filters = $this->entityManager->getFilters();
        $etaitActif = $filters->isEnabled('session_filter');
        if ($etaitActif) {
            $filters->disable('session_filter');
        }

        $rows = $this->entityManager->createQuery(
            'SELECT IDENTITY(c.session) AS sid, COUNT(c.id) AS nb FROM App\Entity\Candidature c GROUP BY sid'
        )->getArrayResult();

        if ($etaitActif) {
            $courante = $this->sessionContext->getCurrentSession();
            $filters->enable('session_filter')->setParameter('session_id', $courante->getId(), 'integer');
        }

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['sid']] = (int) $row['nb'];
        }

        return $counts;
    }

    private function accessDeniedResponse(): ?Response
    {
        if (!$this->getUser() || !$this->isGranted('ROLE_AGENT')) {
            return $this->render('security/access_denied.html.twig');
        }

        return null;
    }

    /**
     * Garantit qu'une seule session de recrutement est active à la fois : quand on active
     * une session, toutes les autres sont automatiquement désactivées. Indispensable puisque
     * la création de candidature s'appuie sur "la" session active (findOneBy(['active' => true])).
     */
    private function deactivateOtherSessions(SessionRecrutement $session): void
    {
        $autresSessions = $this->entityManager->getRepository(SessionRecrutement::class)
            ->findBy(['active' => true]);

        foreach ($autresSessions as $autreSession) {
            if ($autreSession->getId() !== $session->getId()) {
                $autreSession->setActive(false);
            }
        }
    }

    /**
     * Suggère le prochain numéro de vague disponible pour une année donnée
     * (max(numeroVague) + 1 parmi les sessions de cette année, ou 1 si aucune).
     */
    private function suggestNextNumeroVague(int $annee): int
    {
        $sessionsAnnee = $this->entityManager->getRepository(SessionRecrutement::class)
            ->findBy(['annee' => $annee]);

        $max = 0;
        foreach ($sessionsAnnee as $s) {
            $max = max($max, $s->getNumeroVague() ?? 0);
        }

        return $max + 1;
    }
}
