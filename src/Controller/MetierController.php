<?php

namespace App\Controller;

use App\Entity\Metier;
use App\Entity\EtablissementMetier;
use App\Form\MetierType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[Route('/metier')]
class MetierController extends AbstractController
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/', name: 'app_metier_index', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function index(): Response
    {
        $metiers = $this->entityManager->getRepository(Metier::class)
            ->findBy([], ['nom' => 'ASC']);
        
        return $this->render('metier/index.html.twig', [
            'metiers' => $metiers
        ]);
    }

    #[Route('/new', name: 'app_metier_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request, SluggerInterface $slugger): Response
    {
        $metier = new Metier();
        $metier->setUser($this->getUser());
        $metier->setCreation(new \DateTime());

        $form = $this->createForm(MetierType::class, $metier);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('metiers_directory'),
                        $newFilename
                    );
                    $metier->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('danger', 'Une erreur est survenue lors de l\'envoi de l\'image.');
                }
            }

            $this->entityManager->persist($metier);
            $this->entityManager->flush();

            $this->addFlash('success', 'Métier créé avec succès.');
            return $this->redirectToRoute('app_metier_index');
        }

        return $this->render('metier/form.html.twig', [
            'metier' => $metier,
            'form' => $form
        ]);
    }

    #[Route('/{id}/edit', name: 'app_metier_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, Metier $metier, SluggerInterface $slugger): Response
    {
        $metier->setModification(new \DateTime());

        $form = $this->createForm(MetierType::class, $metier);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('metiers_directory'),
                        $newFilename
                    );

                    // Optionnel : Supprimer l'ancienne image si nécessaire
                    if ($metier->getImage()) {
                        $oldImagePath = $this->getParameter('metiers_directory').'/'.$metier->getImage();
                        if (file_exists($oldImagePath)) {
                            @unlink($oldImagePath);
                        }
                    }

                    $metier->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('danger', 'Une erreur est survenue lors de l\'envoi de l\'image.');
                }
            }

            $this->entityManager->flush();
            $this->addFlash('success', 'Métier modifié avec succès.');
            return $this->redirectToRoute('app_metier_index');
        }

        return $this->render('metier/form.html.twig', [
            'metier' => $metier,
            'form' => $form
        ]);
    }

    #[Route('/{id}', name: 'app_metier_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, Metier $metier): Response
    {
        if ($this->isCsrfTokenValid('delete' . $metier->getId(), $request->request->get('_token'))) {
            if ($metier->getImage()) {
                $imagePath = $this->getParameter('metiers_directory').'/'.$metier->getImage();
                if (file_exists($imagePath)) {
                    @unlink($imagePath);
                }
            }

            $this->entityManager->remove($metier);
            $this->entityManager->flush();
            $this->addFlash('success', 'Métier supprimé avec succès.');
        }

        return $this->redirectToRoute('app_metier_index');
    }

    #[Route('/by-secteur/{secteurId}', name: 'app_metier_by_secteur', methods: ['GET'])]
    public function getBySecteur(int $secteurId): JsonResponse
    {
        $metiers = [];
        
        $data = [];
        foreach ($metiers as $metier) {
            $data[] = [
                'id' => $metier->getId(),
                'nom' => $metier->getNom()
            ];
        }
        
        return $this->json($data);
    }

    #[Route('/by-etablissement/{metierId}', name: 'app_etablissement_by_metier', methods: ['GET'])]
    public function getEtablissementsByMetier(int $metierId): JsonResponse
    {
        $metier = $this->entityManager->getRepository(Metier::class)->find($metierId);
        
        if (!$metier) {
            return $this->json([]);
        }

        $etablissementMetiers = $this->entityManager->getRepository(EtablissementMetier::class)
            ->findBy(['metier' => $metier]);
        
        $etablissements = [];
        foreach ($etablissementMetiers as $em) {
            $etablissement = $em->getEtablissement();
            if ($etablissement) {
                $etablissements[] = [
                    'id' => $etablissement->getId(),
                    'nom' => $etablissement->getNom(),
                    'nbrplace' => $em->getNbrplace(),
                    'localite' => $etablissement->getLocalite() ? $etablissement->getLocalite()->getNom() : null,
                ];
            }
        }
        
        return $this->json($etablissements);
    }
}
