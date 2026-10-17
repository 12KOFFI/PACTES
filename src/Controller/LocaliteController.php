<?php
// src/Controller/LocaliteController.php

namespace App\Controller;

use App\Entity\Localite;
use App\Form\LocaliteType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[Route('/localite')]
#[IsGranted('ROLE_ADMIN')]
class LocaliteController extends AbstractController
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/', name: 'app_localite_index', methods: ['GET'])]
    public function index(): Response
    {
        $localites = $this->entityManager->getRepository(Localite::class)
            ->findBy(['parent' => null], ['nom' => 'ASC']);
        
        return $this->render('localite/index.html.twig', [
            'localites' => $localites
        ]);
    }

    #[Route('/new', name: 'app_localite_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $localite = new Localite();
        $form = $this->createForm(LocaliteType::class, $localite);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Ajouter l'utilisateur qui a créé la localité
            $localite->setUser($this->getUser());
            $localite->setCreation(new \DateTime());
            
            $this->entityManager->persist($localite);
            $this->entityManager->flush();

            $this->addFlash('success', 'Localité créée avec succès.');
            return $this->redirectToRoute('app_localite_index');
        }

        return $this->render('localite/form.html.twig', [
            'localite' => $localite,
            'form' => $form->createView()
        ]);
    }

    #[Route('/{id}/edit', name: 'app_localite_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Localite $localite): Response
    {
        $form = $this->createForm(LocaliteType::class, $localite);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $localite->setModification(new \DateTime());
            $this->entityManager->flush();
            $this->addFlash('success', 'Localité modifiée avec succès.');
            return $this->redirectToRoute('app_localite_index');
        }

        return $this->render('localite/form.html.twig', [
            'localite' => $localite,
            'form' => $form->createView()
        ]);
    }

    #[Route('/{id}', name: 'app_localite_delete', methods: ['POST'])]
    public function delete(Request $request, Localite $localite): Response
    {
        if ($this->isCsrfTokenValid('delete' . $localite->getId(), $request->request->get('_token'))) {
            // Vérifier si la localité a des établissements associés
            if (count($localite->getEtablissements()) > 0) {
                $this->addFlash('error', 'Impossible de supprimer cette localité car elle est associée à des établissements.');
                return $this->redirectToRoute('app_localite_index');
            }
            
            // Vérifier si la localité a des enfants
            if (count($localite->getEnfants()) > 0) {
                $this->addFlash('error', 'Impossible de supprimer cette localité car elle a des localités filles.');
                return $this->redirectToRoute('app_localite_index');
            }
            
            $this->entityManager->remove($localite);
            $this->entityManager->flush();
            $this->addFlash('success', 'Localité supprimée avec succès.');
        }

        return $this->redirectToRoute('app_localite_index');
    }

    /**
     * Endpoint pour récupérer les détails d'une localité
     */
    #[Route('/{id}/details', name: 'app_localite_details', methods: ['GET'])]
    public function getLocaliteDetails(Localite $localite): JsonResponse
    {
        return $this->json([
            'id' => $localite->getId(),
            'nom' => $localite->getNom(),
            'type' => $localite->getType(),
            'type_label' => $this->getTypeLabel($localite->getType()),
            'parent' => $localite->getParent() ? [
                'id' => $localite->getParent()->getId(),
                'nom' => $localite->getParent()->getNom()
            ] : null,
            'enfants' => array_map(function($enfant) {
                return [
                    'id' => $enfant->getId(),
                    'nom' => $enfant->getNom(),
                    'type' => $enfant->getType(),
                    'type_label' => $this->getTypeLabel($enfant->getType())
                ];
            }, $localite->getEnfants()->toArray()),
            'etablissements' => array_map(function($etablissement) {
                return [
                    'id' => $etablissement->getId(),
                    'nom' => $etablissement->getNom()
                ];
            }, $localite->getEtablissements()->toArray())
        ]);
    }

    /**
     * Endpoint pour rechercher des localités
     */
    #[Route('/search', name: 'app_localites_search', methods: ['GET'])]
    public function searchLocalites(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        $type = $request->query->get('type');
        
        $qb = $this->entityManager->getRepository(Localite::class)
            ->createQueryBuilder('l')
            ->where('l.nom LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('l.nom', 'ASC');
        
        if ($type) {
            $qb->andWhere('l.type = :type')
               ->setParameter('type', $type);
        }
        
        $localites = $qb->getQuery()->getResult();

        $data = [];
        foreach ($localites as $localite) {
            $data[] = [
                'id' => $localite->getId(),
                'nom' => $localite->getNom(),
                'type' => $localite->getType(),
                'type_label' => $this->getTypeLabel($localite->getType()),
                'direction_regionale' => $localite->getDirectionRegionale() ? $localite->getDirectionRegionale()->getNom() : null
            ];
        }

        return $this->json($data);
    }

    /**
     * Obtient le libellé du type de localité
     */
    private function getTypeLabel(?string $type): string
    {
        $labels = [
            'region' => 'Région',
            'departement' => 'Département',
            'commune' => 'Commune',
            'ville' => 'Ville',
            'sous-prefecture' => 'Sous-préfecture',
            'village' => 'Village'
        ];
        
        return $labels[$type] ?? $type;
    }
}
