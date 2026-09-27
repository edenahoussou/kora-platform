<?php

namespace App\Modules\Notifications\Data;

enum OpsNotificationChannel: string
{
    /** Notification persistée, récupérable par l'API (canal de référence). */
    case InApp = 'in_app';

    /** Diffusion temps réel via Broadcasting, quand un pilote est configuré. */
    case Realtime = 'realtime';

    case Mail = 'mail';

    case Push = 'push';
}
