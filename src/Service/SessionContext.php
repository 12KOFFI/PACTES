<?php

namespace App\Service;

use App\Entity\SessionRecrutement;
use App\Repository\SessionRecrutementRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Session de recrutement "consultée" (écrans du personnel) :
 * 1) paramètre ?session=ID valide, 2) session mémorisée, 3) session ouverte, 4) la plus récente.
 * Distincte de la session qui accepte les candidatures (SessionRecrutementRepository::findActive()).
 */
class SessionContext
{
    private const KEY = 'session_consultee_id';

    private ?SessionRecrutement $current = null;
    private bool $resolved = false;

    public function __construct(
        private SessionRecrutementRepository $repository,
        private RequestStack $requestStack
    ) {
    }

    public function resolve(Request $request): ?SessionRecrutement
    {
        $php = $request->hasSession() ? $request->getSession() : null;
        $session = null;

        $param = $request->query->get('session');
        if ($param !== null && ctype_digit((string) $param)) {
            $session = $this->repository->find((int) $param);
            if ($session && $php) {
                $php->set(self::KEY, $session->getId());
            }
        }

        if (!$session && $php && $php->has(self::KEY)) {
            $session = $this->repository->find((int) $php->get(self::KEY));
        }

        $session ??= $this->repository->findActive();
        $session ??= $this->repository->findOneBy([], ['annee' => 'DESC', 'numeroVague' => 'DESC']);

        $this->current = $session;
        $this->resolved = true;

        return $session;
    }

    public function getCurrentSession(): ?SessionRecrutement
    {
        if (!$this->resolved) {
            $request = $this->requestStack->getCurrentRequest();
            if (!$request) {
                return null;
            }
            $this->resolve($request);
        }

        return $this->current;
    }

    /** @return SessionRecrutement[] */
    public function getSessions(): array
    {
        return $this->repository->findBy([], ['annee' => 'DESC', 'numeroVague' => 'DESC']);
    }
}