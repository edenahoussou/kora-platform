<?php

use App\Modules\AppDistribution\Data\ReleaseEnvironment;
use App\Modules\AppDistribution\Data\ReleasePlatform;
use App\Modules\AppDistribution\Services\ReleaseTopicResolver;
use Tests\TestCase;

uses(TestCase::class);

it('resolves the frozen mobile topic convention', function (ReleasePlatform $platform, ReleaseEnvironment $environment, string $expected): void {
    expect(app(ReleaseTopicResolver::class)->resolve($platform, $environment))->toBe($expected);
})->with([
    [ReleasePlatform::Android, ReleaseEnvironment::Production, 'passenger-android-prod'],
    [ReleasePlatform::Ios, ReleaseEnvironment::Production, 'passenger-ios-prod'],
    [ReleasePlatform::Android, ReleaseEnvironment::Staging, 'passenger-android-staging'],
    [ReleasePlatform::Ios, ReleaseEnvironment::Staging, 'passenger-ios-staging'],
]);
