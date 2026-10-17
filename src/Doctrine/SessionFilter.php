<?php

namespace App\Doctrine;

use App\Entity\Admis;
use App\Entity\Candidature;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Restreint Candidature (et Admis, via sa candidature) à la session de recrutement consultée.
 * Activé uniquement pour le personnel (voir SessionContextSubscriber) ; jamais pour les candidats ni les pages publiques.
 */
class SessionFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias): string
    {
        if (!$this->hasParameter('session_id')) {
            return '';
        }

        $sessionId = $this->getParameter('session_id');

        if ($targetEntity->getName() === Candidature::class) {
            return sprintf('%s.session_id = %s', $targetTableAlias, $sessionId);
        }

        if ($targetEntity->getName() === Admis::class) {
            return sprintf(
                '%s.candidature_id IN (SELECT fc.id FROM candidature fc WHERE fc.session_id = %s)',
                $targetTableAlias,
                $sessionId
            );
        }

        return '';
    }
}