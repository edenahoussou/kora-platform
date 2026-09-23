<?php

namespace App\Modules\Settings\Services;

use Spatie\Activitylog\Models\Activity;

final class IntegrationProbeHistory
{
    /** @return array<string, array<string, mixed>> */
    public function latest(): array
    {
        $latest = [];

        Activity::query()
            ->where('event', 'settings.connection_tested')
            ->latest('id')
            ->limit(100)
            ->get()
            ->each(function (Activity $activity) use (&$latest): void {
                $provider = (string) data_get($activity->properties, 'provider');

                if ($provider === '' || isset($latest[$provider])) {
                    return;
                }

                $latest[$provider] = [
                    'provider' => $provider,
                    'status' => (string) data_get($activity->properties, 'status', 'failed'),
                    'environment' => (string) data_get($activity->properties, 'environment', ''),
                    'checkedAt' => $activity->created_at?->toIso8601String(),
                    'durationMs' => (int) data_get($activity->properties, 'durationMs', 0),
                    'safeMessage' => (string) data_get($activity->properties, 'safeMessage', ''),
                ];
            });

        return $latest;
    }
}
