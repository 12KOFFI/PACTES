<?php

namespace App\Tests\Integration\Database;

use App\Entity\Candidature;
use App\Entity\Etablissement;
use App\Entity\Metier;
use App\Entity\SessionRecrutement;
use App\Entity\User;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ConcurrencyAndIntegrityTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get('doctrine.orm.entity_manager');
    }

    public function testDuplicateCandidatureForSameUserAndSessionThrowsException(): void
    {
        // Candidate 1 already has a candidature for Session 2026 Vague 1 in fixtures
        $user1 = $this->em->getRepository(User::class)->findOneBy(['email' => 'candidat1@pacte.ci']);
        $this->assertNotNull($user1);

        $session = $this->em->getRepository(SessionRecrutement::class)->findOneBy(['annee' => 2026]);
        $this->assertNotNull($session);

        $etab = $this->em->getRepository(Etablissement::class)->findOneBy([]);
        $metier = $this->em->getRepository(Metier::class)->findOneBy([]);

        // Try to insert a second candidature for Candidate 1 in the same session
        $duplicateCandidature = (new Candidature())
            ->setUser($user1)
            ->setSession($session)
            ->setEtablissement($etab)
            ->setMetier($metier)
            ->setNumero('PA26019999'); // different numero, but duplicate (user_id, session_id)

        $this->expectException(UniqueConstraintViolationException::class);

        $this->em->persist($duplicateCandidature);
        $this->em->flush();
    }

    public function testDuplicateCandidatureNumeroThrowsException(): void
    {
        // Try to create a candidature with the same numero as an existing one ('PA26010001')
        $session = $this->em->getRepository(SessionRecrutement::class)->findOneBy(['annee' => 2026]);
        $etab = $this->em->getRepository(Etablissement::class)->findOneBy([]);
        $metier = $this->em->getRepository(Metier::class)->findOneBy([]);

        // Create a new user so (user_id, session_id) is unique, but numero collides
        $uniqueEmail = 'candidat.test.conflict.' . uniqid() . '@pacte.ci';
        $newUser = (new User())
            ->setEmail($uniqueEmail)
            ->setNom('TEST')
            ->setPrenoms('Conflict')
            ->setContact('0102030499')
            ->setRoles(['ROLE_CANDIDAT'])
            ->setPassword('dummy');
        $this->em->persist($newUser);
        $this->em->flush();

        $collidingCandidature = (new Candidature())
            ->setUser($newUser)
            ->setSession($session)
            ->setEtablissement($etab)
            ->setMetier($metier)
            ->setNumero('PA26010001'); // existing numero!

        $this->expectException(UniqueConstraintViolationException::class);

        $this->em->persist($collidingCandidature);
        $this->em->flush();
    }
}
