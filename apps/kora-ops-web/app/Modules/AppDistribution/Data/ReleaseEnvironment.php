<?php

declare(strict_types=1);

namespace App\Modules\AppDistribution\Data;

enum ReleaseEnvironment: string
{
    case Staging = 'staging';
    case Production = 'production';
}
