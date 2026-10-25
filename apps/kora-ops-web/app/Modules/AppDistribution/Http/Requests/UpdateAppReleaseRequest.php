<?php

namespace App\Modules\AppDistribution\Http\Requests;

use App\Modules\AppDistribution\Models\AppRelease;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAppReleaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $release = $this->route('release');

        return $release instanceof AppRelease
            && ($this->user()?->can('update', $release) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_mandatory' => $this->boolean('is_mandatory')]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'release_notes' => ['nullable', 'string', 'max:5000'],
            'is_mandatory' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'release_notes' => 'notes de version',
            'is_mandatory' => 'mise à jour obligatoire',
        ];
    }

    /**
     * @return array{release_notes: string|null, is_mandatory: bool}
     */
    public function payload(): array
    {
        $notes = $this->string('release_notes')->trim()->value();

        return [
            'release_notes' => $notes !== '' ? $notes : null,
            'is_mandatory' => $this->boolean('is_mandatory'),
        ];
    }
}
