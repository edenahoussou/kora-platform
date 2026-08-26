<?php

namespace App\Modules\Cash\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;
use LogicException;

class StoreCashMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cash.transfer.create') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'ledger_uuid' => ['required', 'uuid'],
            'amount' => ['required', 'integer', 'min:1'],
            'type' => ['required', 'string', 'in:reversement,deposit,withdrawal'],
            'destination_label' => ['required', 'string', 'max:160'],
            'destination_reference' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'evidence' => [
                'required',
                'file',
                File::types(['jpg', 'jpeg', 'png', 'webp'])->max(5120),
            ],
        ];
    }

    /** @return array{ledger_uuid: string, amount: int, type: string, destination_label: string, destination_reference: string, description: string|null, evidence: UploadedFile} */
    public function movementData(): array
    {
        $data = $this->validated();
        $evidence = $this->file('evidence');
        if (! $evidence instanceof UploadedFile) {
            throw new LogicException('A validated movement evidence file is missing.');
        }

        return [
            'ledger_uuid' => (string) $data['ledger_uuid'],
            'amount' => (int) $data['amount'],
            'type' => (string) $data['type'],
            'destination_label' => (string) $data['destination_label'],
            'destination_reference' => (string) $data['destination_reference'],
            'description' => isset($data['description']) ? (string) $data['description'] : null,
            'evidence' => $evidence,
        ];
    }
}
