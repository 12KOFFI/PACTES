<?php

namespace App\Service;

use App\Entity\SessionRecrutement;
use App\Entity\User;

class EligibiliteService
{
    public const NATIONALITE_REQUISE = 'CI';

    /**
     * Retourne la liste des motifs de refus (vide si le candidat est éligible).
     *
     * @return string[]
     */
    public function verifier(User $user, SessionRecrutement $session): array
    {
        $nationalite = $user->getNationalite();
        $naissance = $user->getDatenaissance();

        if (!$nationalite || !$naissance) {
            return ['Veuillez compléter votre profil (nationalité et date de naissance) avant de déposer une candidature.'];
        }

        $erreurs = [];

        if (strtoupper(trim($nationalite)) !== self::NATIONALITE_REQUISE) {
            $erreurs[] = 'Cette candidature est réservée aux personnes de nationalité ivoirienne.';
        }

        $reference = $session->getDateReferenceAge();
        $ageMin = $session->getAgeMin();
        $ageMax = $session->getAgeMax();

        if (!$reference || $ageMin === null || $ageMax === null) {
            return ['Les conditions d\'âge de cette session ne sont pas configurées. Veuillez contacter l\'administration.'];
        }

        $age = \DateTimeImmutable::createFromInterface($naissance)
            ->setTime(0, 0)
            ->diff(\DateTimeImmutable::createFromInterface($reference)->setTime(0, 0));

        if ($age->invert === 1 || $age->y < $ageMin || $age->y > $ageMax) {
            $erreurs[] = sprintf(
                'Vous devez avoir entre %d et %d ans (inclus) au %s pour déposer une candidature.',
                $ageMin,
                $ageMax,
                $reference->format('d/m/Y')
            );
        }

        return $erreurs;
    }
}
