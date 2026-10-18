<?php

namespace App\Tests\Integration\Security;

use App\Entity\Candidature;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class EstablishmentIsolationTest extends WebTestCase
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
        $this->assertNotNull($user, "User with email {$email} should exist in test fixtures");
        return $user;
    }

    private function findCandidatureByEtablissement(int $etablissementId): Candidature
    {
        $candidature = $this->em->getRepository(Candidature::class)
            ->createQueryBuilder('c')
            ->innerJoin('c.etablissement', 'e')
            ->where('e.id = :etabId')
            ->setParameter('etabId', $etablissementId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $this->assertNotNull($candidature, "A candidature for establishment {$etablissementId} should exist");
        return $candidature;
    }

    public function testEntCanAccessOwnEstablishmentCandidature(): void
    {
        // ENT 2: Jacqueville (associated with Etablissement ID 2)
        $ent2 = $this->findUser('ent.jacqueville@pacte.ci');
        $candEtab2 = $this->findCandidatureByEtablissement(2);

        $this->client->loginUser($ent2);
        $this->client->request('GET', '/candidature/' . $candEtab2->getId() . '/edit');

        $this->assertResponseIsSuccessful('ENT user must be able to view and evaluate candidates from their own establishment');
    }

    public function testEntCannotAccessOtherEstablishmentCandidature(): void
    {
        // ENT 2 (Jacqueville) tries to access candidate from Etablissement 3 (Korhogo)
        $ent2 = $this->findUser('ent.jacqueville@pacte.ci');
        $candEtab3 = $this->findCandidatureByEtablissement(3);

        $this->client->loginUser($ent2);
        $this->client->request('GET', '/candidature/' . $candEtab3->getId() . '/edit');

        // AccessDeniedHandler redirects to /candidature/ with an error flash message
        $this->assertResponseRedirects('/candidature/');
        $crawler = $this->client->followRedirect();
        $this->assertStringContainsString('Accès refusé', $this->client->getResponse()->getContent());
    }

    public function testEnt1CannotAccessJacquevilleCandidature(): void
    {
        // ENT 1 (Energies) tries to access candidate from Etablissement 2 (Jacqueville)
        $ent1 = $this->findUser('ent.energies@pacte.ci');
        $candEtab2 = $this->findCandidatureByEtablissement(2);

        $this->client->loginUser($ent1);
        $this->client->request('GET', '/candidature/' . $candEtab2->getId() . '/edit');

        $this->assertResponseRedirects('/candidature/');
        $crawler = $this->client->followRedirect();
        $this->assertStringContainsString('Accès refusé', $this->client->getResponse()->getContent());
    }

    public function testCandidateCannotAccessOtherCandidateEdit(): void
    {
        // Candidate 1 tries to access Candidate 2's candidature
        $candidat1 = $this->findUser('candidat1@pacte.ci');
        $candidature2 = $this->em->getRepository(Candidature::class)->findOneBy(['numero' => 'PA26010002']);
        $this->assertNotNull($candidature2);

        $this->client->loginUser($candidat1);
        $this->client->request('GET', '/candidature/' . $candidature2->getId() . '/edit');

        $this->assertResponseRedirects('/candidature/');
        $crawler = $this->client->followRedirect();
        $this->assertStringContainsString('Accès refusé', $this->client->getResponse()->getContent());
    }

    public function testAdminCanAccessAnyEstablishmentCandidature(): void
    {
        $admin = $this->findUser('admin@pacte.ci');
        $candEtab1 = $this->findCandidatureByEtablissement(1);
        $candEtab2 = $this->findCandidatureByEtablissement(2);
        $candEtab3 = $this->findCandidatureByEtablissement(3);

        $this->client->loginUser($admin);

        $this->client->request('GET', '/candidature/' . $candEtab1->getId() . '/edit');
        $this->assertResponseIsSuccessful('Admin must have access to Establishment 1');

        $this->client->request('GET', '/candidature/' . $candEtab2->getId() . '/edit');
        $this->assertResponseIsSuccessful('Admin must have access to Establishment 2');

        $this->client->request('GET', '/candidature/' . $candEtab3->getId() . '/edit');
        $this->assertResponseIsSuccessful('Admin must have access to Establishment 3');
    }
}
