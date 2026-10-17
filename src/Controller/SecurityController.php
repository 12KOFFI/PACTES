<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Security\AppAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

class SecurityController extends AbstractController
{
    #[Route('/connexion', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils, Request $request): Response
    {
        if ($this->getUser()) {
            // Gérer la redirection après connexion si un utilisateur est déjà connecté
            $redirect = $request->query->get('redirect');
            if ($redirect && $this->isUrlSafe($redirect)) {
                return $this->redirect($redirect);
            }
            
            if ($this->isGranted('ROLE_ADMIN')) {
                return $this->redirectToRoute('app_dashboard');
            }
            return $this->redirectToRoute('app_dashboard');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();
        
        // Récupérer l'URL de redirection
        $redirect = $request->query->get('redirect');

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'redirect' => $redirect
        ]);
    }

    #[Route('/mot-de-passe-oublie', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $step = $request->getSession()->get('forgot_password_step', 1);
        $userId = $request->getSession()->get('forgot_password_user_id');
        $identifier = '';
        
        // Étape 1 : Vérification de l'utilisateur
        if ($request->isMethod('POST') && $request->request->has('verify_user')) {
            $identifier = trim($request->request->get('identifier', ''));
            $lastFourDigits = trim($request->request->get('last_four_digits', ''));
            
            if (empty($identifier) || empty($lastFourDigits)) {
                $this->addFlash('error', 'Veuillez remplir tous les champs.');
                return $this->render('security/forgot_password.html.twig', ['step' => 1]);
            }
            
            // Rechercher l'utilisateur par email ou par contact
            $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $identifier]);
            if (!$user && str_starts_with($identifier, '0') && strlen($identifier) >= 8) {
                $user = $entityManager->getRepository(User::class)->findOneBy(['contact' => $identifier]);
            }
            
            if (!$user) {
                $this->addFlash('error', 'Aucun utilisateur trouvé avec cet identifiant.');
                return $this->render('security/forgot_password.html.twig', ['step' => 1]);
            }
            
            // Vérifier les 4 derniers chiffres du numéro de téléphone
            $contact = $user->getContact();
            if (!$contact || strlen($contact) < 4) {
                $this->addFlash('error', 'Aucun numéro de téléphone associé à ce compte.');
                return $this->render('security/forgot_password.html.twig', ['step' => 1]);
            }
            
            $lastFour = substr(str_replace(' ', '', $contact), -4);
            if ($lastFour !== $lastFourDigits) {
                $this->addFlash('error', 'Les 4 derniers chiffres du numéro de téléphone ne correspondent pas.');
                return $this->render('security/forgot_password.html.twig', ['step' => 1]);
            }
            
            // Stocker l'ID utilisateur en session
            $request->getSession()->set('forgot_password_user_id', $user->getId());
            $request->getSession()->set('forgot_password_step', 2);
            
            return $this->render('security/forgot_password.html.twig', ['step' => 2, 'identifier' => $identifier]);
        }
        
        // Étape 2 : Nouveau mot de passe
        if ($request->isMethod('POST') && $request->request->has('reset_password')) {
            if (!$userId) {
                $this->addFlash('error', 'Session expirée. Veuillez recommencer.');
                return $this->redirectToRoute('app_forgot_password');
            }
            
            $user = $entityManager->getRepository(User::class)->find($userId);
            if (!$user) {
                $this->addFlash('error', 'Utilisateur introuvable.');
                return $this->redirectToRoute('app_forgot_password');
            }
            
            // Récupérer l'identifiant pour l'affichage
            $identifier = $user->getEmail() ?: $user->getContact();
            
            $newPassword = $request->request->get('new_password', '');
            $confirmPassword = $request->request->get('confirm_password', '');
            
            if (empty($newPassword) || empty($confirmPassword)) {
                $this->addFlash('error', 'Veuillez remplir tous les champs.');
                return $this->render('security/forgot_password.html.twig', ['step' => 2, 'identifier' => $identifier]);
            }
            
            if ($newPassword !== $confirmPassword) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
                return $this->render('security/forgot_password.html.twig', ['step' => 2, 'identifier' => $identifier]);
            }
            
            if (strlen($newPassword) < 6) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 6 caractères.');
                return $this->render('security/forgot_password.html.twig', ['step' => 2, 'identifier' => $identifier]);
            }
            
            // Hachage et mise à jour du mot de passe
            $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
            $entityManager->flush();
            
            // Nettoyer la session
            $request->getSession()->remove('forgot_password_step');
            $request->getSession()->remove('forgot_password_user_id');
            
            $this->addFlash('success', 'Votre mot de passe a été réinitialisé avec succès. Veuillez vous connecter.');
            return $this->redirectToRoute('app_login');
        }
        
        // Réinitialiser la session si on arrive sur la page
        $request->getSession()->remove('forgot_password_step');
        $request->getSession()->remove('forgot_password_user_id');
        
        return $this->render('security/forgot_password.html.twig', ['step' => 1]);
    }

    #[Route('/inscription', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        UserAuthenticatorInterface $authenticator,
        AppAuthenticator $formAuthenticator
    ): Response {
        if ($this->getUser()) {
            // Gérer la redirection après inscription si un utilisateur est déjà connecté
            $redirect = $request->query->get('redirect');
            if ($redirect && $this->isUrlSafe($redirect)) {
                return $this->redirect($redirect);
            }
            return $this->redirectToRoute('app_candidature_index');
        }

        $user = new User();
        $form = $this->createForm(UserType::class, $user, [
            'is_register' => true,
            'is_edit' => false
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérifier si l'email existe déjà
            $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $user->getEmail()]);
            if ($existingUser) {
                $this->addFlash('error', 'Cet email est déjà utilisé.');
                return $this->renderForm('security/register.html.twig', [
                    'form' => $form,
                    'redirect' => $request->query->get('redirect')
                ]);
            }

            // Hachage du mot de passe
            $user->setPassword(
                $passwordHasher->hashPassword($user, $form->get('password')->getData())
            );
            
            // Attribution du rôle candidat
            $user->setRoles(['ROLE_CANDIDAT']);

            $entityManager->persist($user);
            $entityManager->flush();

            // Authentification automatique
            $authenticator->authenticateUser($user, $formAuthenticator, $request);

            $this->addFlash('success', 'Votre compte a été créé avec succès.');
            
            // Redirection après inscription
            $redirect = $request->query->get('redirect');
            if ($redirect && $this->isUrlSafe($redirect)) {
                return $this->redirect($redirect);
            }
            
            return $this->redirectToRoute('app_candidature_new');
        }

        return $this->renderForm('security/register.html.twig', [
            'form' => $form,
            'redirect' => $request->query->get('redirect')
        ]);
    }

    #[Route('/deconnexion', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    /**
     * Vérifie si une URL de redirection est sûre (évite les redirections vers des domaines externes)
     */
    private function isUrlSafe(string $url): bool
    {
        // Vérifier que l'URL commence par / (chemin relatif)
        if (str_starts_with($url, '/')) {
            return true;
        }
        
        // Vérifier que l'URL est sur le même domaine
        $parsedUrl = parse_url($url);
        if (isset($parsedUrl['host'])) {
            $request = Request::createFromGlobals();
            return $parsedUrl['host'] === $request->getHost();
        }
        
        return false;
    }
}
