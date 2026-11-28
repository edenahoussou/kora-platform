<?php

declare(strict_types=1);

namespace App\Modules\AppDistribution\Data;

enum ReleasePlatform: string
{
    case Android = 'android';
    case Ios = 'ios';
}
