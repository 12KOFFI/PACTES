<?php

namespace App\Repository;

use App\Entity\SessionRecrutement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SessionRecrutementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SessionRecrutement::class);
    }

    public function findActive(?\DateTimeImmutable $at = null): ?SessionRecrutement
    {
        $at = $at ?? new \DateTimeImmutable();

        return $this->createQueryBuilder('session')
            ->andWhere('session.active = :active')
            ->andWhere('session.statut = :statut')
            ->andWhere('(session.dateOuverture IS NULL OR session.dateOuverture <= :at)')
            ->andWhere('(session.dateCloture IS NULL OR session.dateCloture >= :at)')
            ->setParameter('active', true)
            ->setParameter('statut', SessionRecrutement::STATUT_OUVERTE)
            ->setParameter('at', $at)
            ->orderBy('session.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
