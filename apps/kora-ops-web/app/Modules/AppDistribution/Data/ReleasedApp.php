<?php

namespace App\Modules\AppDistribution\Data;

use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

/**
 * Applications distribuées par KORA OPS Web (MEZ-150).
 *
 * - `ops_mobile` : Convoyeur, APK hors store (`ops_apk`).
 * - `passenger_mobile` : Passager, release store Android/iOS (`store`).
 *
 * - `value` : identifiant stocké en base et exposé dans les réponses API.
 * - `slug()` : forme lisible utilisée dans les URL publiques.
 */
enum ReleasedApp: string
{
    use HasEnumUtilities;

    case OpsMobile = 'ops_mobile';
    case PassengerMobile = 'passenger_mobile';

    public function label(): string
    {
        return match ($this) {
            self::OpsMobile => 'KORA OPS Mobile',
            self::PassengerMobile => 'KORA Passager',
        };
    }

    public function slug(): string
    {
        return str_replace('_', '-', $this->value);
    }

    /**
     * Public visé — affiché sur la page de téléchargement et l'écran admin.
     */
    public function audience(): string
    {
        return match ($this) {
            self::OpsMobile => 'Convoyeurs, chauffeurs et agents KORA EXPRESS — terminaux P501 et téléphones Android.',
            self::PassengerMobile => 'Voyageurs KORA — applications Android et iOS disponibles sur les stores.',
        };
    }

    /**
     * Description courte (meta description / Open Graph) de la page publique.
     */
    public function description(): string
    {
        return match ($this) {
            self::OpsMobile => 'Téléchargez la dernière version de KORA OPS Mobile, l’application terrain des équipes KORA EXPRESS : embarquement, manifestes, synchronisation et suivi des départs.',
            self::PassengerMobile => 'Découvrez KORA Passager : recherche de trajets, réservation, suivi du bus et informations en temps réel.',
        };
    }

    /**
     * Préfixe du nom de fichier proposé au téléchargement.
     */
    public function fileBasename(): string
    {
        return match ($this) {
            self::OpsMobile => 'kora-ops-mobile',
            self::PassengerMobile => 'kora-passenger',
        };
    }

    /**
     * Résout un segment d'URL (`ops-mobile` ou `ops_mobile`) en application.
     */
    public static function fromRouteKey(string $key): ?self
    {
        $normalized = str_replace('-', '_', strtolower(trim($key)));

        return self::tryFrom($normalized);
    }

    /**
     * Application installée sur un terminal OPS.
     */
    public static function forTerminal(TerminalP501 $terminal): self
    {
        return self::OpsMobile;
    }

    /**
     * Cette application est-elle celle installée sur [terminal] ? Écrit en
     * `match` exhaustif : l'ajout d'une app oblige à trancher.
     */
    public function runsOn(TerminalP501 $terminal): bool
    {
        return match ($this) {
            self::OpsMobile => true,
            self::PassengerMobile => false,
        };
    }
}
