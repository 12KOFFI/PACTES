<?php

namespace App\Tests\Unit\Service;

use App\Entity\SessionRecrutement;
use App\Entity\User;
use App\Service\EligibiliteService;
use PHPUnit\Framework\TestCase;

class EligibiliteServiceTest extends TestCase
{
    private EligibiliteService $service;
    private SessionRecrutement $session;

    protected function setUp(): void
    {
        $this->service = new EligibiliteService();

        $this->session = (new SessionRecrutement())
            ->setNom('Session Test 2026')
            ->setAnnee(2026)
            ->setNumeroVague(1)
            ->setDateReferenceAge(new \DateTime('2026-12-31'))
            ->setAgeMin(18)
            ->setAgeMax(35)
            ->setStatut(SessionRecrutement::STATUT_OUVERTE)
            ->setActive(true);
    }

    public function testMissingProfileData(): void
    {
        $user = new User();
        // Neither nationality nor birthdate set
        $errors = $this->service->verifier($user, $this->session);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('compléter votre profil', $errors[0]);

        // Only nationality set
        $user->setNationalite('CI');
        $errors = $this->service->verifier($user, $this->session);
        $this->assertNotEmpty($errors);
    }

    public function testNonIvorianNationalityRejected(): void
    {
        $user = (new User())
            ->setNationalite('FR')
            ->setDatenaissance(new \DateTime('2000-05-15'));

        $errors = $this->service->verifier($user, $this->session);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('nationalité ivoirienne', $errors[0]);
    }

    public function testAgeUnder18Rejected(): void
    {
        // Born in 2010 -> will be 16 on 2026-12-31
        $user = (new User())
            ->setNationalite('CI')
            ->setDatenaissance(new \DateTime('2010-01-01'));

        $errors = $this->service->verifier($user, $this->session);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('entre 18 et 35 ans', $errors[0]);
    }

    public function testAgeOver35Rejected(): void
    {
        // Born in 1985 -> will be 41 on 2026-12-31
        $user = (new User())
            ->setNationalite('CI')
            ->setDatenaissance(new \DateTime('1985-01-01'));

        $errors = $this->service->verifier($user, $this->session);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('entre 18 et 35 ans', $errors[0]);
    }

    public function testEligibleCandidateAccepted(): void
    {
        // Born in 2002 -> 24 years old on 2026-12-31
        $user = (new User())
            ->setNationalite('ci') // lower case should be handled via strtoupper
            ->setDatenaissance(new \DateTime('2002-06-20'));

        $errors = $this->service->verifier($user, $this->session);
        $this->assertEmpty($errors, 'An eligible candidate should have 0 errors');
    }

    public function testSessionConditionsNotConfigured(): void
    {
        $unconfiguredSession = new SessionRecrutement();
        $user = (new User())
            ->setNationalite('CI')
            ->setDatenaissance(new \DateTime('2002-06-20'));

        $errors = $this->service->verifier($user, $unconfiguredSession);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('pas configurées', $errors[0]);
    }
}
