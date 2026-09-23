<?php

namespace App\Modules\Settings\Http\Requests;

use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\Settings\Data\SettingsGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

use function Illuminate\Support\enum_value;

abstract class SettingsUpdateRequest extends FormRequest
{
    abstract protected function settingsGroup(): SettingsGroup;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && enum_value($user->status) === UserStatus::Active->value
            && $user->can($this->settingsGroup()->managePermission());
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'values' => ['sometimes', 'array'],
            'clearSecrets' => ['sometimes', 'array'],
            'clearSecrets.*' => ['string', 'max:100'],
            'confirmed' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->requiresConfirmation() || $this->boolean('confirmed')) {
                return;
            }

            $validator->errors()->add(
                'confirmed',
                'Confirmez les changements opérationnels avant de les enregistrer.',
            );
        }];
    }

    /** @return array<string, mixed> */
    public function values(): array
    {
        return (array) $this->validated('values', []);
    }

    /** @return list<string> */
    public function clearSecrets(): array
    {
        return array_values((array) $this->validated('clearSecrets', []));
    }

    private function requiresConfirmation(): bool
    {
        if ($this->settingsGroup() !== SettingsGroup::Operations) {
            return false;
        }

        $impactfulKeys = [
            'saleCutoffMinutes',
            'saleCutoffOverrides',
            'paymentTimeoutMinutes',
            'staleDepartureHours',
            'maxDepartureDelayMinutes',
            'opsSessionTtlMinutes',
            'opsSessionMaxLifetimeMinutes',
            'opsSessionTouchIntervalSeconds',
        ];
        $submitted = $this->values();
        $current = app($this->settingsGroup()->settingsClass())->toArray();

        foreach ($impactfulKeys as $key) {
            if (array_key_exists($key, $submitted) && $submitted[$key] != ($current[$key] ?? null)) {
                return true;
            }
        }

        return false;
    }
}
