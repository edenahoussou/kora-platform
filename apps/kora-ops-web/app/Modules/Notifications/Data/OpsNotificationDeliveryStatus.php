<?php

namespace App\Modules\Notifications\Data;

enum OpsNotificationDeliveryStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';

    /** Non envoyée volontairement (préférence du destinataire). */
    case Skipped = 'skipped';
}
