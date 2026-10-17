<?php

namespace App\Config;


final class EtablissementsActifsConfig
{
    /** Établissement de formation affiché sur la plateforme. */
    public const ACTIFS_IDS = [
        1,
        2,
        3,
    ];

    /**
     * IDs des établissements à afficher EN PREMIER dans les listes.
     * L'ordre ici est l'ordre d'affichage (ex: 98 -> 99...).
     */
    public const PRIORITAIRES_IDS = [];

    /**
     * Génère la clause DQL CASE WHEN pour trier les établissements prioritaires en premier.
     * Usage : ->addSelect(EtablissementsActifsConfig::getSortPriorityCaseWhen())
     *
     * @param string $alias L'alias Doctrine de l'entité Etablissement (ex: 'e')
     */
    public static function getSortPriorityCaseWhen(string $alias = 'e'): string
    {
        // Crée une clause CASE dynamique pour préserver l'ordre strict de PRIORITAIRES_IDS
        $cases = [];
        foreach (self::PRIORITAIRES_IDS as $index => $id) {
            $cases[] = "WHEN {$alias}.id = {$id} THEN {$index}";
        }

        if (empty($cases)) {
            return "1 AS HIDDEN sort_priority";
        }

        $caseString = implode(' ', $cases);
        $defaultOrder = count(self::PRIORITAIRES_IDS);

        return "(CASE {$caseString} ELSE {$defaultOrder} END) AS HIDDEN sort_priority";
    }

    /**
     * Génère la clause DQL WHERE pour filtrer TOUS les établissements actifs.
     * Usage : ->where(EtablissementsActifsConfig::getDqlWhereClause())
     *
     * @param string $alias L'alias Doctrine de l'entité Etablissement (ex: 'e')
     */
    public static function getDqlWhereClause(string $alias = 'e'): string
    {
        $tousLesIds = array_merge(self::PRIORITAIRES_IDS, self::ACTIFS_IDS);
        if (empty($tousLesIds)) {
            return '1 = 0';
        }
        $ids = implode(', ', $tousLesIds);
        return "{$alias}.id IN ({$ids})";
    }
}
