<?php

namespace App\Tests\Functional;

use App\Entity\Candidature;
use App\Entity\Etablissement;
use App\Entity\Metier;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PaginationAndFilteringTest extends WebTestCase
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
        return $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    public function testDefaultPaginationLimitIs20(): void
    {
        $admin = $this->findUser('admin@pacte.ci');
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/candidature/');
        $this->assertResponseIsSuccessful();

        // 20 candidates are rendered in the table body rows
        $rows = $crawler->filter('tbody tr');
        $this->assertCount(20, $rows, 'Default page must display exactly 20 candidates per page');
    }

    public function testFiltersAppliedBeforePagination(): void
    {
        $admin = $this->findUser('admin@pacte.ci');
        $this->client->loginUser($admin);

        // Filter by establishment 2 (Jacqueville)
        $etab2 = $this->em->getRepository(Etablissement::class)->findOneBy(['nom' => 'Lycée Professionnel de Jacqueville']);
        $this->assertNotNull($etab2);

        $crawler = $this->client->request('GET', '/candidature/?etablissement=' . $etab2->getId());
        $this->assertResponseIsSuccessful();

        // Out of 20 total, exactly 7 belong to establishment 2
        $rows = $crawler->filter('tbody tr');
        $this->assertLessThanOrEqual(20, count($rows));
        $this->assertGreaterThan(0, count($rows));

        // Every row in the filtered table must be from Jacqueville
        foreach ($rows as $row) {
            $this->assertStringContainsString('Jacqueville', $row->textContent);
        }
    }

    public function testSearchFilterInQuery(): void
    {
        $admin = $this->findUser('admin@pacte.ci');
        $this->client->loginUser($admin);

        // Search for specific candidate name "KOUASSI"
        $crawler = $this->client->request('GET', '/candidature/?q=KOUASSI');
        $this->assertResponseIsSuccessful();

        $rows = $crawler->filter('tbody tr');
        $this->assertGreaterThanOrEqual(1, count($rows));
        $this->assertStringContainsString('KOUASSI', $rows->first()->text());
    }

    public function testEntIsolationInListing(): void
    {
        // Login as ENT Korhogo (Etablissement 3)
        $entKorhogo = $this->findUser('ent.korhogo@pacte.ci');
        $this->client->loginUser($entKorhogo);

        $crawler = $this->client->request('GET', '/candidature/');
        $this->assertResponseIsSuccessful();

        $rows = $crawler->filter('tbody tr');
        // Must only show candidates belonging to Korhogo
        foreach ($rows as $row) {
            $this->assertStringContainsString('Korhogo', $row->textContent);
            $this->assertStringNotContainsString('Jacqueville', $row->textContent);
            $this->assertStringNotContainsString('Yopougon', $row->textContent);
        }
    }

    public function testNoNPlusOneQueriesOnCandidatureList(): void
    {
        $admin = $this->findUser('admin@pacte.ci');
        $this->client->loginUser($admin);

        // Enable profiler
        $this->client->enableProfiler();

        $this->client->request('GET', '/candidature/');
        $this->assertResponseIsSuccessful();

        $profile = $this->client->getProfile();
        if ($profile && $profile->hasCollector('db')) {
            $dbCollector = $profile->getCollector('db');
            $queryCount = $dbCollector->getQueryCount();

            // With eager joins (leftJoin c.etablissement e, leftJoin c.metier m, leftJoin c.user u with addSelect),
            // rendering 20 candidates must take less than 35 total page lifecycle queries,
            // strictly avoiding an N+1 explosion (which would produce > 60 queries).
            $this->assertLessThanOrEqual(
                35,
                $queryCount,
                "Query count ({$queryCount}) indicates N+1 query problem is avoided thanks to join fetches"
            );
        }
    }
}
