<?php

namespace App\Modules\Settings\Services;

use App\Models\User;
use App\Modules\BusNetwork\Data\LineStatus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\Settings\Contracts\SettingsCoordinator;
use App\Modules\Settings\Data\SettingsGroup;
use App\Modules\Settings\Settings\WalletSettings;
use Illuminate\Support\Facades\Gate;

final class SettingsPagePresenter
{
    public function __construct(
        private readonly SettingsCoordinator $settings,
        private readonly IntegrationProbeHistory $probeHistory,
    ) {}

    /** @return array<string, mixed> */
    public function page(SettingsGroup $group, User $actor): array
    {
        $snapshot = $this->settings->snapshot($group);

        return [
            'group' => $group->value,
            'snapshot' => [
                'group' => $snapshot->group->value,
                'values' => $snapshot->values,
                'sensitiveProperties' => $snapshot->sensitiveProperties,
                'capturedAt' => $snapshot->capturedAt->format(DATE_ATOM),
            ],
            'permissions' => $group === SettingsGroup::Wallet
                ? [
                    'canView' => Gate::forUser($actor)->allows('view', WalletSettings::class),
                    'canManage' => Gate::forUser($actor)->allows('update', WalletSettings::class),
                ]
                : [
                    'canView' => $actor->can($group->viewPermission()),
                    'canManage' => $actor->can($group->managePermission()),
                ],
            'lineOptions' => $group === SettingsGroup::Operations
                ? $this->lineOptions()
                : [],
            'probeResults' => $group === SettingsGroup::Integrations
                ? $this->probeHistory->latest()
                : [],
        ];
    }

    /** @return list<array{uuid: string, code: string, name: string}> */
    private function lineOptions(): array
    {
        return array_values(Line::query()
            ->where('status', LineStatus::Active->value)
            ->orderBy('name')
            ->get(['uuid', 'code', 'name'])
            ->map(fn (Line $line): array => [
                'uuid' => (string) $line->uuid,
                'code' => (string) $line->code,
                'name' => (string) $line->name,
            ])
            ->all());
    }
}
