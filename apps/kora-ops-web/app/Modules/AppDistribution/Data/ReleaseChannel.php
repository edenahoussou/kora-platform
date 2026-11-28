<?php

declare(strict_types=1);

namespace App\Modules\AppDistribution\Data;

enum ReleaseChannel: string
{
    case OpsApk = 'ops_apk';
    case Store = 'store';
}
