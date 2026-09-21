<?php

namespace App\Modules\Notifications\Data;

/**
 * Action que le client (mobile ou web) propose à la réception d'une
 * notification — un code stable, jamais un texte à interpréter.
 */
enum OpsNotificationAction: string
{
    case None = 'none';
    case OpenDeparture = 'open_departure';
    case RefreshManifest = 'refresh_manifest';
    case Reauthenticate = 'reauthenticate';
    case ReviewScanConflict = 'review_scan_conflict';
    case ReviewRejectedOperations = 'review_rejected_operations';
    case RetrySync = 'retry_sync';
    case CheckPrinter = 'check_printer';
}
