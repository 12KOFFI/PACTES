<?php

namespace App\Controller;

use App\Entity\Candidature;
use App\Entity\Etablissement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/media')]
class MediaController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ParameterBagInterface $params
    ) {}

    #[Route('/{candidatureNumero}/{filename}', name: 'app_media_file', methods: ['GET'])]
  
    public function show(string $candidatureNumero, string $filename): BinaryFileResponse
    {
        $candidature = $this->entityManager->getRepository(Candidature::class)
            ->findOneBy(['numero' => $candidatureNumero]);

        if (!$candidature) {
            throw $this->createNotFoundException('Candidature non trouvée.');
        }

        $user = $this->getUser();
        if (!$this->isGranted('ROLE_ADMIN')) {
            $etablissementUser = $this->entityManager->getRepository(Etablissement::class)
                ->findOneBy(['user' => $user]);
            $etablissementCandidature = $candidature->getEtablissement();
            if (!$etablissementUser || !$etablissementCandidature || $etablissementUser->getId() !== $etablissementCandidature->getId()) {
                throw $this->createAccessDeniedException('Vous n\'avez pas accès à ce fichier.');
            }
        }

        $dirMedia = $this->params->get('dir_media');
        $path = $dirMedia . $candidatureNumero . '/' . $filename;
        if (!file_exists($path)) {
            throw $this->createNotFoundException('Fichier non trouvé: ' . $path);
        }

        return $this->file($path, $filename, ResponseHeaderBag::DISPOSITION_INLINE);
    }
}
