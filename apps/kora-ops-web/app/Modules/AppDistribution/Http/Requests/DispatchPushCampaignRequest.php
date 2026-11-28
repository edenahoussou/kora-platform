<?php

namespace App\Modules\AppDistribution\Http\Requests;

use App\Modules\AppDistribution\Models\AppRelease;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Préparation d'une campagne FCM par topic pour une release store (#342).
 * Seul le responsable ayant la permission manage peut déclencher l'envoi.
 */
class DispatchPushCampaignRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:2000'],
            'data' => ['nullable', 'array'],
            'data.*' => ['string'],
        ];
    }

    /**
     * @return array{title: string, body: string, data: array<string, string>}
     */
    public function message(): array
    {
        $data = [];
        foreach ($this->input('data', []) as $key => $value) {
            if (is_string($key) && $key !== '' && is_string($value)) {
                $data[$key] = $value;
            }
        }

        return [
            'title' => $this->string('title')->trim()->value(),
            'body' => $this->string('body')->trim()->value(),
            'data' => $data,
        ];
    }
}
