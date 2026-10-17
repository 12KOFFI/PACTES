<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:user:create', description: 'Créer un utilisateur agent ou administrateur')]
class CreateUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Adresse email')
            ->addArgument('role', InputArgument::REQUIRED, 'ROLE_ADMIN, ROLE_AGENT, ROLE_ETABLISSEMENT ou ROLE_JURY')
            ->addArgument('nom', InputArgument::REQUIRED, 'Nom')
            ->addArgument('prenoms', InputArgument::REQUIRED, 'Prénoms');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = strtolower(trim((string) $input->getArgument('email')));
        $role = strtoupper(trim((string) $input->getArgument('role')));

        $allowedRoles = ['ROLE_ADMIN', 'ROLE_AGENT', 'ROLE_ETABLISSEMENT', 'ROLE_JURY'];
        if (!in_array($role, $allowedRoles, true)) {
            $io->error('Rôle invalide. Choisissez ROLE_ADMIN, ROLE_AGENT, ROLE_ETABLISSEMENT ou ROLE_JURY.');
            return Command::INVALID;
        }

        if ($this->entityManager->getRepository(User::class)->findOneBy(['email' => $email])) {
            $io->error('Un utilisateur existe déjà avec cette adresse email.');
            return Command::FAILURE;
        }

        $passwordQuestion = new Question('Mot de passe (minimum 6 caractères) : ');
        $passwordQuestion->setHidden(true);
        $passwordQuestion->setHiddenFallback(false);
        $password = (string) $io->askQuestion($passwordQuestion);
        if (strlen($password) < 6) {
            $io->error('Le mot de passe doit contenir au moins 6 caractères.');
            return Command::INVALID;
        }

        $user = new User();
        $user->setEmail($email)
            ->setNom((string) $input->getArgument('nom'))
            ->setPrenoms((string) $input->getArgument('prenoms'))
            ->setRoles([$role])
            ->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('Utilisateur %s créé avec le rôle %s.', $email, $role));
        return Command::SUCCESS;
    }
}
