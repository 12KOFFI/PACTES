<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;

class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
    private UrlGeneratorInterface $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): ?Response
    {
        if ($request->hasSession()) {
            $flashBag = $request->getSession()->getBag('flashes');

            if ($flashBag instanceof FlashBagInterface) {
                $flashBag->add(
                    'error',
                    'Accès refusé à cette page.'
                );
            }
        }

        return new RedirectResponse($this->urlGenerator->generate('app_candidature_index'));
    }
}
