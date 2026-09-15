<?php

namespace App\Modules\Sync\Http\Requests;

use App\Modules\Sync\Data\SyncOperationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitSyncBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'batch_uuid' => ['required', 'uuid'],
            'operations' => ['required', 'array', 'min:1', 'max:200'],
            'operations.*.operation_uuid' => ['required', 'uuid', 'distinct'],
            'operations.*.operation_type' => ['required', Rule::enum(SyncOperationType::class)],
            'operations.*.local_recorded_at' => ['required', 'date'],
            'operations.*.payload' => ['required', 'array'],
        ];
    }

    public function batchUuid(): string
    {
        return $this->string('batch_uuid')->value();
    }

    /**
     * @return array<int, array{operation_uuid: string, operation_type: string, local_recorded_at: string, payload: array<string, mixed>}>
     */
    public function operations(): array
    {
        return $this->input('operations', []);
    }
}
