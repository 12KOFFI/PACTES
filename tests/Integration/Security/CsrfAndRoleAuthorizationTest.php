<?php

namespace App\Tests\Integration\Security;

use App\Entity\Candidature;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CsrfAndRoleAuthorizationTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine.orm.entity_manager');
    }

    private function findUser(string $email): User
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        $this->assertNotNull($user, "User {$email} must exist in fixtures");
        return $user;
    }

    public function testAnonymousAccessRedirectsToLogin(): void
    {
        $this->client->request('GET', '/candidature/');
        $this->assertResponseRedirects('/connexion');

        $this->client->request('GET', '/dashboard/');
        $this->assertResponseRedirects('/connexion');

        $this->client->request('GET', '/user/');
        $this->assertResponseRedirects('/connexion');
    }

    public function testCandidateForbiddenFromAdminUserManagement(): void
    {
        $candidat = $this->findUser('candidat1@pacte.ci');
        $this->client->loginUser($candidat);

        $this->client->request('GET', '/user/');
        // AccessDeniedHandler intercepts AccessDeniedException and redirects to /candidature/
        $this->assertResponseRedirects('/candidature/');

        $crawler = $this->client->followRedirect();
        $this->assertStringContainsString('Accès refusé', $this->client->getResponse()->getContent());
    }

    public function testEntUserForbiddenFromAdminUserManagement(): void
    {
        $ent = $this->findUser('ent.energies@pacte.ci');
        $this->client->loginUser($ent);

        $this->client->request('GET', '/user/');
        $this->assertResponseRedirects('/candidature/');

        $crawler = $this->client->followRedirect();
        $this->assertStringContainsString('Accès refusé', $this->client->getResponse()->getContent());
    }

    public function testAdminCanAccessUserManagement(): void
    {
        $admin = $this->findUser('admin@pacte.ci');
        $this->client->loginUser($admin);

        $this->client->request('GET', '/user/');
        $this->assertResponseIsSuccessful('Admin must have access to /user/');
    }

    public function testCsrfTokenProtectionOnUpdateInfo(): void
    {
        $ent = $this->findUser('ent.energies@pacte.ci');
        $this->client->loginUser($ent);

        // Fetch candidate 1
        $candidature = $this->em->getRepository(Candidature::class)->findOneBy(['numero' => 'PA26010001']);
        $this->assertNotNull($candidature);

        // Post with forged/invalid CSRF token
        $this->client->request('POST', '/candidature/' . $candidature->getId() . '/update-info', [
            '_token' => 'invalid_csrf_token_test_12345',
            'nom' => 'HACKED_NAME',
            'prenoms' => 'HACKED_PRENOMS'
        ]);

        // Must redirect with CSRF error flash
        $this->assertResponseRedirects('/candidature/' . $candidature->getId() . '/edit');
        $crawler = $this->client->followRedirect();
        $this->assertStringContainsString('Token de sécurité invalide', $this->client->getResponse()->getContent());

        // Verify database was NOT corrupted
        $freshCandidature = $this->em->getRepository(Candidature::class)->find($candidature->getId());
        $this->assertNotSame('HACKED_NAME', $freshCandidature->getUser()->getNom(), 'Database must not be altered with invalid CSRF');
    }
}
