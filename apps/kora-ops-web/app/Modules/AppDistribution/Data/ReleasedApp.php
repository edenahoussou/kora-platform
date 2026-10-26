<?php

namespace App\Modules\AppDistribution\Data;

use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

/**
 * Applications distribuées hors store par KORA OPS Web (MEZ-150).
 *
 * Modèle générique : la table `app_releases` porte un champ `app`, de sorte
 * que l'app passager puisse réutiliser la même distribution plus tard en
 * ajoutant simplement un cas ici. En V1, seule l'app convoyeur
 * (`apps/kora-ops-mobile`, terminaux P501 + téléphones) est concernée.
 *
 * - `value` : identifiant stocké en base et exposé dans le bloc `update`
 *   des réponses OPS Mobile (`ops_mobile`).
 * - `slug()` : forme lisible utilisée dans les URL publiques de
 *   téléchargement (`/downloads/apps/ops-mobile`). Les deux formes sont
 *   acceptées en entrée (voir [fromRouteKey]).
 */
enum ReleasedApp: string
{
    use HasEnumUtilities;

    case OpsMobile = 'ops_mobile';

    public function label(): string
    {
        return match ($this) {
            self::OpsMobile => 'KORA OPS Mobile',
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
        };
    }

    /**
     * Description courte (meta description / Open Graph) de la page publique.
     */
    public function description(): string
    {
        return match ($this) {
            self::OpsMobile => 'Téléchargez la dernière version de KORA OPS Mobile, l’application terrain des équipes KORA EXPRESS : embarquement, manifestes, synchronisation et suivi des départs.',
        };
    }

    /**
     * Préfixe du nom de fichier proposé au téléchargement
     * (`kora-ops-mobile-1.4.0-42.apk`).
     */
    public function fileBasename(): string
    {
        return match ($this) {
            self::OpsMobile => 'kora-ops-mobile',
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
     * Application installée sur un terminal OPS. P501 et téléphones en mode
     * caméra font tourner la même app convoyeur — le paramètre est conservé
     * pour le jour où un autre type de terminal embarquera une autre app.
     */
    public static function forTerminal(TerminalP501 $terminal): self
    {
        return self::OpsMobile;
    }

    /**
     * Cette application est-elle celle installée sur [terminal] ? Écrit en
     * `match` exhaustif : l'ajout d'une app (passager…) oblige à trancher.
     */
    public function runsOn(TerminalP501 $terminal): bool
    {
        return match ($this) {
            self::OpsMobile => true,
        };
    }
}
