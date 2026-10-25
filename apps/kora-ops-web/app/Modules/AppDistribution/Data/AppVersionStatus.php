<?php

namespace App\Modules\AppDistribution\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

/**
 * Statut de version d'une installation (terminal ou appareil qui vérifie au
 * lancement), calculé côté serveur par `AppReleaseCatalog::check()`.
 *
 * - `up_to_date`       : version installée ≥ dernière release publiée ;
 * - `update_available` : une release plus récente existe, aucune n'est
 *                        obligatoire au-delà de la version installée ;
 * - `update_required`  : une release obligatoire plus récente que la version
 *                        installée existe → écran bloquant côté mobile ;
 * - `unknown`          : l'appareil n'a jamais remonté de version lisible ;
 * - `not_tracked`      : aucune release publiée pour cette application.
 */
enum AppVersionStatus: string
{
    use HasEnumUtilities;

    case UpToDate = 'up_to_date';
    case UpdateAvailable = 'update_available';
    case UpdateRequired = 'update_required';
    case Unknown = 'unknown';
    case NotTracked = 'not_tracked';

    public function label(): string
    {
        return match ($this) {
            self::UpToDate => 'À jour',
            self::UpdateAvailable => 'MAJ disponible',
            self::UpdateRequired => 'MAJ requise',
            self::Unknown => 'Version inconnue',
            self::NotTracked => 'Non suivi',
        };
    }
}
