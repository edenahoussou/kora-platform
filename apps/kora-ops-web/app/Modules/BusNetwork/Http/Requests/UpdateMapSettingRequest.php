<?php

namespace App\Modules\BusNetwork\Http\Requests;

use App\Modules\BusNetwork\Data\MapProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMapSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('network.manage') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            // Nullable on purpose: submitting no provider clears the admin
            // override and falls back to MAP_PROVIDER from .env.
            'provider' => ['nullable', Rule::enum(MapProvider::class)],
        ];
    }

    /** @return array{provider: string|null} */
    public function payload(): array
    {
        return [
            'provider' => $this->filled('provider') ? $this->string('provider')->value() : null,
        ];
    }
}
