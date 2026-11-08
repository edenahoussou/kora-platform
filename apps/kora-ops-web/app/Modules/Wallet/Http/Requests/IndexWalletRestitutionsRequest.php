<?php

namespace App\Modules\Wallet\Http\Requests;

use App\Modules\Wallet\Data\RestitutionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexWalletRestitutionsRequest extends FormRequest
{
    /**
     * Allows authorized reviewers and agents to view their restitution workflow.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(RestitutionStatus::class)],
            'source' => ['nullable', 'uuid'],
        ];
    }
}
