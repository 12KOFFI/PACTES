<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class AppAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    private UrlGeneratorInterface $urlGenerator;
    private AuthorizationCheckerInterface $authorization;
    private RouterInterface $router;

    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        AuthorizationCheckerInterface $authorization,
        RouterInterface $router
    ) {
        $this->urlGenerator = $urlGenerator;
        $this->authorization = $authorization;
        $this->router = $router;
    }

    public function supports(Request $request): bool
    {
        return $request->getPathInfo() === '/connexion' && $request->isMethod('POST');
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->request->get('email', '');
        $password = $request->request->get('password', '');
        $csrfToken = $request->request->get('_csrf_token');

        $request->getSession()->set(Security::LAST_USERNAME, $email);

        return new Passport(
            new UserBadge($email),
            new PasswordCredentials($password),
            [
                new CsrfTokenBadge('authenticate', $csrfToken),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // 1. Vérifier le target_path envoyé par le formulaire
        $targetPathPost = $request->request->get('_target_path');
        if ($targetPathPost) {
            return new RedirectResponse(urldecode($targetPathPost));
        }

        // 2. Vérifier redirect dans l'URL
        $redirect = $request->query->get('redirect');
        if ($redirect) {
            return new RedirectResponse($redirect);
        }

        // 3. Vérifier targetPath stocké dans la session
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        // 4. Redirection selon rôle

        if ($this->authorization->isGranted('ROLE_ADMIN')) {
            return new RedirectResponse($this->router->generate('app_dashboard'));
        }

        if ($this->authorization->isGranted('ROLE_AGENT')) {
            return new RedirectResponse($this->router->generate('app_session_recrutement_index'));
        }

        // ROLE_COM : redirigé vers les statistiques de candidature
        if ($this->authorization->isGranted('ROLE_COM')) {
            return new RedirectResponse($this->router->generate('app_candidature_stat'));
        }

        if ($this->authorization->isGranted('ROLE_ENT') || $this->authorization->isGranted('ROLE_JURY')) {
            return new RedirectResponse($this->router->generate('app_dashboard'));
        }

        if ($this->authorization->isGranted('ROLE_CANDIDAT')) {
            return new RedirectResponse($this->router->generate('app_dashboard'));
        }

        return new RedirectResponse($this->router->generate('app_home'));
    }

    protected function getLoginUrl(Request $request): string
    {
        $redirect = $request->query->get('redirect');

        if ($redirect) {
            return $this->urlGenerator->generate(self::LOGIN_ROUTE, ['redirect' => $redirect]);
        }

        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
