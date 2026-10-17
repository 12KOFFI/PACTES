<?php

namespace App\Controller;

use App\Entity\EtablissementMetier;
use App\Form\EtablissementMetierType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[Route('/etablissement-metier')]
#[IsGranted('ROLE_ADMIN')]
class EtablissementMetierController extends AbstractController
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/', name: 'app_etablissement_metier_index', methods: ['GET'])]
    public function index(): Response
    {
        $etablissementMetiers = $this->entityManager->getRepository(EtablissementMetier::class)
            ->findAll();
        
        return $this->render('etablissement_metier/index.html.twig', [
            'etablissementMetiers' => $etablissementMetiers
        ]);
    }

    #[Route('/new', name: 'app_etablissement_metier_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $etablissementMetier = new EtablissementMetier();
        $form = $this->createForm(EtablissementMetierType::class, $etablissementMetier);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($etablissementMetier);
            $this->entityManager->flush();

            $this->addFlash('success', 'Association établie avec succès.');
            return $this->redirectToRoute('app_etablissement_metier_index');
        }

        return $this->render('etablissement_metier/form.html.twig', [
            'etablissementMetier' => $etablissementMetier,
            'form' => $form
        ]);
    }

    #[Route('/{id}/edit', name: 'app_etablissement_metier_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, EtablissementMetier $etablissementMetier): Response
    {
        $form = $this->createForm(EtablissementMetierType::class, $etablissementMetier);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Association modifiée avec succès.');
            return $this->redirectToRoute('app_etablissement_metier_index');
        }

        return $this->render('etablissement_metier/form.html.twig', [
            'etablissementMetier' => $etablissementMetier,
            'form' => $form
        ]);
    }

    #[Route('/{id}', name: 'app_etablissement_metier_delete', methods: ['POST'])]
    public function delete(Request $request, EtablissementMetier $etablissementMetier): Response
    {
        if ($this->isCsrfTokenValid('delete' . $etablissementMetier->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($etablissementMetier);
            $this->entityManager->flush();
            $this->addFlash('success', 'Association supprimée avec succès.');
        }

        return $this->redirectToRoute('app_etablissement_metier_index');
    }
}
