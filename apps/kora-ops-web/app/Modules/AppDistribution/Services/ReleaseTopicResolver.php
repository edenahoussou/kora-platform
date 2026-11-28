<?php

declare(strict_types=1);

namespace App\Modules\AppDistribution\Services;

use App\Modules\AppDistribution\Data\ReleaseEnvironment;
use App\Modules\AppDistribution\Data\ReleasePlatform;

/**
 * Conventions de topic FCM partagées entre le mobile Passager et le backend.
 *
 * Le topic suit le contrat mobile : `passenger-{platform}-{prod|staging}`.
 */
final class ReleaseTopicResolver
{
    public function resolve(ReleasePlatform $platform, ReleaseEnvironment $environment): string
    {
        return sprintf(
            'passenger-%s-%s',
            $platform->value,
            $environment === ReleaseEnvironment::Production ? 'prod' : 'staging',
        );
    }
}
