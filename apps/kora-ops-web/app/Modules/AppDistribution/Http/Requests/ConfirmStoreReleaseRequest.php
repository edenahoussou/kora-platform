<?php

namespace App\Modules\AppDistribution\Http\Requests;

use App\Modules\AppDistribution\Models\AppRelease;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Confirmation Ops d'une release store (#342).
 */
class ConfirmStoreReleaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $release = $this->route('release');

        return $release instanceof AppRelease
            && ($this->user()?->can('update', $release) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'rollout_completed' => ['required', 'boolean'],
        ];
    }

    public function rolloutCompleted(): bool
    {
        return $this->boolean('rollout_completed');
    }
}
