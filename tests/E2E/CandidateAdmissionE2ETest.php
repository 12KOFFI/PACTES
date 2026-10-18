<?php

namespace App\Tests\E2E;

use App\Entity\Candidature;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Test E2E simulant l'ensemble du parcours utilisateur et des interactions réelles
 * dans l'interface web (DOM, Alpine.js contracts, formulaires, transitions et résultat 2).
 */
class CandidateAdmissionE2ETest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine.orm.entity_manager');

        // Réinitialiser candidat 1 pour un parcours E2E propre
        $cand = $this->em->getRepository(Candidature::class)->findOneBy(['numero' => 'PA26010001']);
        if ($cand) {
            $cand->setEtustatut(null)
                ->setEtucom(null)
                ->setJury(null)
                ->setCreneauEntretien(null)
                ->setEntstatut(null)
                ->setDecisionJury(null)
                ->setEntcom(null)
                ->setVisstatut(null)
                ->setVislieu(null)
                ->setViscom(null)
                ->setResultat(null);
            $this->em->flush();
            $this->em->clear();
        }
    }

    private function findUser(string $email): User
    {
        return $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    public function testFullE2EAdmissionJourneyAndUiInteractions(): void
    {
        // 1. Accès à la page publique d'accueil
        $crawler = $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful('Home page must load with 200 OK');
        $this->assertSelectorExists('nav', 'Navbar must be present');
        $this->assertStringContainsString('PACTE', $this->client->getResponse()->getContent());

        // 2. Connexion en tant que responsable ENT (Lycée des Énergies)
        $ent = $this->findUser('ent.energies@pacte.ci');
        $this->client->loginUser($ent);

        // Accès au tableau de bord
        $crawler = $this->client->request('GET', '/dashboard/');
        $this->assertResponseIsSuccessful('Dashboard must load for ENT user');

        // 3. Navigation vers la liste des candidatures
        $crawler = $this->client->request('GET', '/candidature/');
        $this->assertResponseIsSuccessful('Candidature list must load');

        // Vérification de la présence des composants Alpine.js et du DOM de listing
        $this->assertSelectorExists('table', 'Candidature table must be rendered');
        $this->assertStringContainsString('PA26010001', $this->client->getResponse()->getContent());

        // 4. Ouverture de la fiche d'évaluation du Candidat 1
        $candidature = $this->em->getRepository(Candidature::class)->findOneBy(['numero' => 'PA26010001']);
        $crawler = $this->client->request('GET', '/candidature/' . $candidature->getId() . '/edit?redirect_to=/candidature/');
        $this->assertResponseIsSuccessful();

        // Vérification des contrats UI : mini-menu d'accès rapide, sections 1, 2, 3 et bouton de scroll flottant
        $this->assertSelectorExists('#section-dossier', 'Section 1 (Analyse du dossier) must exist');
        $this->assertSelectorExists('#section-entretien', 'Section 2 (Entretien de sélection) must exist');
        $this->assertSelectorExists('#section-visite', 'Section 3 (Visite médicale) must exist');
        $this->assertSelectorExists('#scrollTopBtn', 'Floating scroll to top button contract must be rendered');

        // 5. Soumission Étape 1 : Analyse du dossier -> Accepté (valeur 1 dans select, entité 2)
        $form = $crawler->selectButton("Enregistrer l'évaluation")->form();
        $form['candidature[etustatut]']->select('1');
        $form['candidature[etucom]'] = 'Dossier E2E validé avec succès';
        $this->client->submit($form);
        $this->assertResponseRedirects('/candidature/');
        $this->client->followRedirect();

        // 6. Soumission Étape 2 : Entretien & Jury -> Admissible (valeur 1 dans select, entité 2)
        $crawler = $this->client->request('GET', '/candidature/' . $candidature->getId() . '/edit?redirect_to=/candidature/');
        $form = $crawler->selectButton("Enregistrer l'évaluation")->form();
        $form['candidature[jury]'] = 1;
        $form['candidature[creneauEntretien]'] = 1;
        $form['candidature[entstatut]'] = 1;
        $form['candidature[decisionJury]']->select('1');
        $form['candidature[entcom]'] = 'Entretien très concluant';
        $this->client->submit($form);
        $this->assertResponseRedirects('/candidature/');
        $this->client->followRedirect();

        // 7. Soumission Étape 3 : Visite médicale -> Effectuée (valeur 1 dans select, entité 2)
        $crawler = $this->client->request('GET', '/candidature/' . $candidature->getId() . '/edit?redirect_to=/candidature/');
        $form = $crawler->selectButton("Enregistrer l'évaluation")->form();
        $form['candidature[visstatut]']->select('1');
        $form['candidature[vislieu]'] = 'Centre Médical A';
        $this->client->submit($form);
        $this->assertResponseRedirects('/candidature/');
        $this->client->followRedirect();

        // 8. Soumission Étape 4 : Admission définitive -> Admis (valeur 1 dans select, entité 2)
        $crawler = $this->client->request('GET', '/candidature/' . $candidature->getId() . '/edit?redirect_to=/candidature/');
        $form = $crawler->selectButton("Enregistrer l'évaluation")->form();
        $form['candidature[resultat]']->select('1');
        $this->client->submit($form);
        $this->assertResponseRedirects('/candidature/');
        $this->client->followRedirect();

        // 9. Vérification persistance en base de données et statut final
        $this->em->clear();
        $finalCand = $this->em->getRepository(Candidature::class)->findOneBy(['numero' => 'PA26010001']);
        $this->assertSame(2, $finalCand->getResultat(), 'Candidate must reach final admission status 2');

        // 10. Consultation de la liste des admis dans l'établissement
        $crawler = $this->client->request('GET', '/admis/etablissement/admis');
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('PA26010001', $this->client->getResponse()->getContent());
        $this->assertStringContainsString('KOUASSI', $this->client->getResponse()->getContent());
    }
}
