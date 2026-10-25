<?php

namespace App\Modules\AppDistribution\Data;

/**
 * Permissions du module de distribution hors store (MEZ-150) — attribuées
 * à `system_admin` (voir `SystemRole::allPermissions()` et la migration
 * `add_app_release_permissions`).
 */
enum AppReleasePermission: string
{
    /** Consulter l'écran « Versions de l'application » et la distribution du parc. */
    case View = 'app-releases.view';

    /** Publier un APK, modifier notes/obligation, archiver ou restaurer une release. */
    case Manage = 'app-releases.manage';
}
