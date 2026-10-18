<?php

namespace App\Tests\Functional;

use App\Entity\Candidature;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdmissionWorkflowTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine.orm.entity_manager');

        // Reset candidate 1 to pristine state so tests are fully reproducible
        $candidature = $this->em->getRepository(Candidature::class)->findOneBy(['numero' => 'PA26010001']);
        if ($candidature) {
            $candidature->setEtustatut(null)
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
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        $this->assertNotNull($user, "User {$email} must exist");
        return $user;
    }

    private function findCandidature(string $numero): Candidature
    {
        $candidature = $this->em->getRepository(Candidature::class)->findOneBy(['numero' => $numero]);
        $this->assertNotNull($candidature, "Candidature {$numero} must exist");
        return $candidature;
    }

    public function testCompleteAdmissionLifecycle(): void
    {
        // =========================================================================
        // ÉTAPE 1 : Vérification de l'état initial du candidat
        // =========================================================================
        $candidature = $this->findCandidature('PA26010001');
        $this->assertNull($candidature->getEtustatut(), 'Initial dossier status must be null (En attente)');
        $this->assertNull($candidature->getVisstatut(), 'Initial medical status must be null');
        $this->assertNull($candidature->getResultat(), 'Initial final result must be null');

        // =========================================================================
        // ÉTAPE 2 : Connexion de l'utilisateur ENT rattaché (Énergies - Etab 1)
        // =========================================================================
        $entUser = $this->findUser('ent.energies@pacte.ci');
        $this->client->loginUser($entUser);

        // Affichage du formulaire d'évaluation
        $crawler = $this->client->request('GET', '/candidature/' . $candidature->getId() . '/edit');
        $this->assertResponseIsSuccessful('ENT user must access evaluation form');

        // Extraction du token CSRF du formulaire
        $csrfToken = $crawler->filter('form[name="candidature"] input[name="candidature[_token]"]')->attr('value');
        $this->assertNotEmpty($csrfToken, 'CSRF token must be present');

        // =========================================================================
        // ÉTAPE 3 : Analyse du dossier (Validation étape 1 : Accepté = 2)
        // =========================================================================
        $crawler = $this->client->request('GET', '/candidature/' . $candidature->getId() . '/edit?redirect_to=/candidature/');
        $form = $crawler->selectButton('Enregistrer l\'évaluation')->form();
        $form['candidature[etustatut]']->select('1'); // HTML Option 1 = 'Accepté' (Entity value 2)
        $form['candidature[etucom]'] = 'Dossier complet, conforme aux exigences PACTE';
        $this->client->submit($form);

        $this->assertResponseRedirects('/candidature/');
        $this->client->followRedirect();

        // Vérification de la persistance en base de données
        $this->em->clear();
        $candidature = $this->findCandidature('PA26010001');
        $this->assertSame(2, $candidature->getEtustatut(), 'Etustatut must be 2 (Accepté)');
        $this->assertSame('Dossier complet, conforme aux exigences PACTE', $candidature->getEtucom());

        // =========================================================================
        // ÉTAPE 4 : Évaluation entretien et jury
        // =========================================================================
        $crawler = $this->client->request('GET', '/candidature/' . $candidature->getId() . '/edit?redirect_to=/candidature/');
        $form = $crawler->selectButton('Enregistrer l\'évaluation')->form();
        $form['candidature[jury]'] = 1;
        $form['candidature[creneauEntretien]'] = 1;
        $form['candidature[entstatut]'] = 1;
        $form['candidature[decisionJury]']->select('1'); // HTML Option 1 = 'Admissible' (Entity value 2)
        $form['candidature[note1]'] = 3.5;
        $form['candidature[note2]'] = 2.5;
        $form['candidature[note3]'] = 4.0;
        $form['candidature[note4]'] = 5.0;
        $form['candidature[note5]'] = 4.5;
        $form['candidature[entcom]'] = 'Très bonne prestation, excellente motivation';
        $this->client->submit($form);

        $this->assertResponseRedirects('/candidature/');
        $this->client->followRedirect();

        $this->em->clear();
        $candidature = $this->findCandidature('PA26010001');
        $this->assertSame(2, $candidature->getDecisionJury(), 'Decision Jury must be 2 (Admissible)');
        $this->assertSame(1, $candidature->getJury());

        // =========================================================================
        // ÉTAPE 5 : Sécurité & Règle métier : Blocage admission avant visite médicale
        // =========================================================================
        // Tentative d'admission frauduleuse ou prématurée (resultat = 2 alors que visstatut != 2)
        $crawler = $this->client->request('GET', '/candidature/' . $candidature->getId() . '/edit');
        $csrfToken = $crawler->filter('form[name="candidature"] input[name="candidature[_token]"]')->attr('value');

        $this->client->request('POST', '/candidature/' . $candidature->getId() . '/edit', [
            'candidature' => [
                'etustatut' => 2,
                'decisionJury' => 2,
                'resultat' => 2, // Tentative d'admission sans visite médicale!
                '_token' => $csrfToken,
            ]
        ]);

        // Le contrôleur doit refuser l'admission et renvoyer une erreur flash explicite
        $this->assertResponseRedirects('/candidature/' . $candidature->getId() . '/edit');
        $crawler = $this->client->followRedirect();
        $this->assertStringContainsString("visite médicale doit d'abord être marquée comme effectuée", $this->client->getResponse()->getContent());

        $this->em->clear();
        $candidature = $this->findCandidature('PA26010001');
        $this->assertNotSame(2, $candidature->getResultat(), 'Resultat must NOT be set to 2 before medical visit is completed');

        // =========================================================================
        // ÉTAPE 6 : Validation de la visite médicale (visstatut = 2 Effectuée)
        // =========================================================================
        $crawler = $this->client->request('GET', '/candidature/' . $candidature->getId() . '/edit?redirect_to=/candidature/');
        $form = $crawler->selectButton('Enregistrer l\'évaluation')->form();
        $form['candidature[visstatut]']->select('1'); // HTML Option 1 = 'Effectué' (Entity value 2)
        $form['candidature[vislieu]'] = 'Centre de santé de Yopougon';
        $form['candidature[viscom]'] = 'Apte au service';
        $this->client->submit($form);

        $this->assertResponseRedirects('/candidature/');
        $this->client->followRedirect();

        $this->em->clear();
        $candidature = $this->findCandidature('PA26010001');
        $this->assertSame(2, $candidature->getVisstatut(), 'Medical status must be 2 (Effectuée)');

        // =========================================================================
        // ÉTAPE 7 : Admission définitive avec résultat final attendu "2"
        // =========================================================================
        $crawler = $this->client->request('GET', '/candidature/' . $candidature->getId() . '/edit?redirect_to=/candidature/');
        $form = $crawler->selectButton('Enregistrer l\'évaluation')->form();
        $form['candidature[resultat]']->select('1'); // HTML Option 1 = 'Admis' (Entity value 2)
        $this->client->submit($form);

        $this->assertResponseRedirects('/candidature/');
        $this->client->followRedirect();

        // Vérification de la persistance en base de données
        $this->em->clear();
        $candidatureFinale = $this->findCandidature('PA26010001');
        $this->assertSame(2, $candidatureFinale->getResultat(), 'Expected final admission result must strictly be 2 (Admis)');
        $this->assertSame(2, $candidatureFinale->getEtustatut());
        $this->assertSame(2, $candidatureFinale->getDecisionJury());
        $this->assertSame(2, $candidatureFinale->getVisstatut());

        // =========================================================================
        // ÉTAPE 8 : Vérification de la présence dans la liste des admis de l'établissement
        // =========================================================================
        $crawler = $this->client->request('GET', '/admis/etablissement/admis');
        $this->assertResponseIsSuccessful('ENT user must access admitted list');
        $this->assertStringContainsString('PA26010001', $this->client->getResponse()->getContent(), 'Candidate PA26010001 must appear in the admitted list');
        $this->assertStringContainsString('KOUASSI', $this->client->getResponse()->getContent());
    }
}
