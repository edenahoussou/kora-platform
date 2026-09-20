<?php

namespace App\Modules\Notifications\Data;

enum OpsNotificationSeverity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Critical = 'critical';
}
