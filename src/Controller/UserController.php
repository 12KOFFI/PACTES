<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Service\Constant;
use App\Service\SmsNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[Route('/user')]
class UserController extends AbstractController
{
    private $entityManager;
    private $passwordHasher;
    private $smsNotifier;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        SmsNotifier $smsNotifier
    ) {
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
        $this->smsNotifier = $smsNotifier;
    }

    #[Route('/', name: 'app_user_index', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function index(): Response
    {
        $users = $this->entityManager->getRepository(User::class)->findBy([], ['id' => 'DESC']);

        return $this->render('user/index.html.twig', [
            'users' => $users
        ]);
    }

    #[Route('/email/{email}', name: 'app_user_check_email', methods: ['GET'])]
    public function checkEmail(string $email): JsonResponse
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        return $this->json([
            'exists' => $user !== null
        ]);
    }

    #[Route('/new', name: 'app_user_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request, Constant $constant): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user, [
            'is_register' => false,
            'is_edit' => false,
            'is_admin' => true
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword($this->passwordHasher->hashPassword($user, $form->get('password')->getData()));
            $user->setRoles([$form->get('role')->getData()]);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->addFlash('success', 'Utilisateur créé avec succès.');
            return $this->redirectToRoute('app_user_index');
        }

        return $this->render('user/form.html.twig', [
            'user' => $user,
            'form' => $form,
            'is_edit' => false,
            'constant' => $constant
        ]);
    }

    #[Route('/{id}/edit', name: 'app_user_edit', methods: ['GET', 'POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function edit(Request $request, User $user, Constant $constant): Response
    {
        // Vérification des droits
        if (!$this->isGranted('ROLE_ADMIN') && $this->getUser() !== $user) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à modifier ce profil.');
        }

        $form = $this->createForm(UserType::class, $user, [
            'is_edit' => true,
            'is_admin' => $this->isGranted('ROLE_ADMIN'),
            'user_role' => $user->getRoles()[0] ?? 'ROLE_CANDIDAT'
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Si un nouveau mot de passe est fourni
            if ($form->has('password') && $form->get('password')->getData()) {
                $user->setPassword($this->passwordHasher->hashPassword($user, $form->get('password')->getData()));
            }
            if ($this->isGranted('ROLE_ADMIN') && $form->has('role')) {
                $user->setRoles([$form->get('role')->getData()]);
            }

            $this->entityManager->flush();

            $this->addFlash('success', 'Profil mis à jour avec succès.');

            return $this->redirectToRoute(
                $this->isGranted('ROLE_ADMIN') ? 'app_user_index' : 'app_candidature_index'
            );
        }

        return $this->render('user/form.html.twig', [
            'user' => $user,
            'form' => $form,
            'is_edit' => true,
            'constant' => $constant
        ]);
    }

    #[Route('/{id}', name: 'app_user_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, User $user): Response
    {
        // Empêcher l'admin de se supprimer lui-même
        if ($user === $this->getUser()) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte.');
            return $this->redirectToRoute('app_user_index');
        }

        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($user);
            $this->entityManager->flush();
            $this->addFlash('success', 'Utilisateur supprimé avec succès.');
        }

        return $this->redirectToRoute('app_user_index');
    }

    #[Route('/verify/email', name: 'app_user_verify_email', methods: ['GET'])]
    public function verifyEmail(Request $request): JsonResponse
    {
        $this->smsNotifier->initializeBackgroundProcessing();

        return $this->json([
            'verified' => true
        ]);
    }

    #[Route('/mon-compte/change-password', name: 'app_user_change_password', methods: ['GET', 'POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function changePassword(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            throw $this->createAccessDeniedException('Utilisateur non connecté.');
        }

        if ($request->isMethod('POST')) {
            $currentPassword = $request->request->get('current_password', '');
            $newPassword = $request->request->get('new_password', '');
            $confirmPassword = $request->request->get('confirm_password', '');

            // Vérifier le mot de passe actuel
            if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('error', 'Le mot de passe actuel est incorrect.');
                return $this->render('user/change_password.html.twig');
            }

            // Vérifier que les nouveaux mots de passe correspondent
            if ($newPassword !== $confirmPassword) {
                $this->addFlash('error', 'Les nouveaux mots de passe ne correspondent pas.');
                return $this->render('user/change_password.html.twig');
            }

            // Vérifier la longueur
            if (strlen($newPassword) < 6) {
                $this->addFlash('error', 'Le nouveau mot de passe doit contenir au moins 6 caractères.');
                return $this->render('user/change_password.html.twig');
            }

            // Mettre à jour le mot de passe
            $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
            $this->entityManager->flush();

            $this->addFlash('success', 'Votre mot de passe a été modifié avec succès.');
            return $this->redirectToRoute('app_candidature_index');
        }

        return $this->render('user/change_password.html.twig');
    }
}
