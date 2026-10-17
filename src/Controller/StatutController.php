<?php

namespace App\Controller;

use App\Entity\Candidature;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StatutController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/verifier-statut', name: 'app_verifier_statut', methods: ['GET'])]
    public function verifier(Request $request): Response
    {
        $candidature = null;
        $erreur = null;
        $recherche = null;

        // Lecture depuis l'URL (query string)
        $recherche = trim($request->query->get('recherche', ''));

        if ($recherche !== '') {
            // On cherche par numéro de candidature OU par email de l'utilisateur
            $candidature = $this->entityManager->getRepository(Candidature::class)
                ->createQueryBuilder('c')
                ->leftJoin('c.user', 'u')
                ->leftJoin('c.etablissement', 'e')
                ->leftJoin('c.metier', 'm')
                ->addSelect('u', 'e', 'm')
                ->where('UPPER(TRIM(c.numero)) = :rechercheNumero')
                ->orWhere('LOWER(TRIM(u.email)) = :rechercheEmail')
                ->setParameter('rechercheNumero', strtoupper($recherche))
                ->setParameter('rechercheEmail', strtolower($recherche))
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$candidature) {
                $erreur = 'Aucune candidature trouvée avec ce numéro ou cet email.';
            }
        }

        return $this->render('verifier_statut/verifier.html.twig', [
            'candidature' => $candidature,
            'erreur' => $erreur,
            'recherche' => $recherche,
        ]);
    }
}
