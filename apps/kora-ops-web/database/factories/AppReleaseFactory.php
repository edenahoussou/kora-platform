<?php

namespace Database\Factories;

use App\Modules\AppDistribution\Data\ReleaseChannel;
use App\Modules\AppDistribution\Data\ReleasedApp;
use App\Modules\AppDistribution\Data\ReleaseEnvironment;
use App\Modules\AppDistribution\Data\ReleasePlatform;
use App\Modules\AppDistribution\Models\AppRelease;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AppRelease>
 */
class AppReleaseFactory extends Factory
{
    protected $model = AppRelease::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $uuid = (string) Str::uuid();
        $build = fake()->unique()->numberBetween(1, 900000);

        return [
            'uuid' => $uuid,
            'app' => ReleasedApp::OpsMobile,
            'channel' => ReleaseChannel::OpsApk,
            'platform' => null,
            'store_url' => null,
            'environment' => ReleaseEnvironment::Production,
            'version' => sprintf('1.%d.0', fake()->numberBetween(0, 20)),
            'build_number' => $build,
            'artifact_path' => ReleasedApp::OpsMobile->value.'/'.$uuid.'.apk',
            'original_filename' => 'app-release.apk',
            'artifact_size' => fake()->numberBetween(10_000_000, 60_000_000),
            'sha256' => hash('sha256', $uuid),
            'release_notes' => fake()->sentence(),
            'is_mandatory' => false,
            'published_at' => now()->subMinute(),
            'archived_at' => null,
            'publicly_confirmed_at' => null,
            'rollout_completed_at' => null,
            'created_by' => null,
        ];
    }

    public function version(string $version, int $build): static
    {
        return $this->state(fn (): array => [
            'version' => $version,
            'build_number' => $build,
        ]);
    }

    public function mandatory(): static
    {
        return $this->state(fn (): array => ['is_mandatory' => true]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['archived_at' => now()]);
    }

    public function storeRelease(ReleasePlatform $platform, ReleaseEnvironment $environment = ReleaseEnvironment::Production): static
    {
        return $this->state(fn (): array => [
            'app' => ReleasedApp::PassengerMobile,
            'channel' => ReleaseChannel::Store,
            'platform' => $platform,
            'environment' => $environment,
            'store_url' => match ($platform) {
                ReleasePlatform::Android => 'https://play.google.com/store/apps/details?id=app.kora.passenger',
                ReleasePlatform::Ios => 'https://apps.apple.com/app/kora-passenger/id000000000',
            },
            'artifact_path' => '',
            'original_filename' => null,
            'artifact_size' => 0,
            'sha256' => '',
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (): array => [
            'publicly_confirmed_at' => now()->subMinute(),
            'rollout_completed_at' => now()->subMinute(),
        ]);
    }
}
