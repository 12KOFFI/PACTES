<?php

namespace App\Config;

final class AdmisAbandonsConfig
{
    
    //liste des ids des admis definitifs uniquement
    public const ABANDONS_IDS = [

    ];

    public static function getIds(): array
    {
        return self::ABANDONS_IDS;
    }

    public static function hasAbandons(): bool
    {
        return !empty(self::ABANDONS_IDS);
    }

    public static function applyFilter(\Doctrine\ORM\QueryBuilder $qb, string $alias = 'a'): void
    {
        if (self::hasAbandons()) {
            $qb->andWhere("{$alias}.id NOT IN (:admis_abandons_ids)")
               ->setParameter('admis_abandons_ids', self::ABANDONS_IDS);
        }
    }
}
