<?php

declare(strict_types=1);

namespace App\Modules\AppDistribution\Data;

enum ReleasePushStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Failed = 'failed';
    case Uncertain = 'uncertain';
}
