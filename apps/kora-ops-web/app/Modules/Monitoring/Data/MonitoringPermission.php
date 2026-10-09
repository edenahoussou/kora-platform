<?php

namespace App\Modules\Monitoring\Data;

/**
 * Permissions du module Monitoring (écran de supervision, log viewer, Horizon).
 */
enum MonitoringPermission: string
{
    case View = 'monitoring.view';
}
