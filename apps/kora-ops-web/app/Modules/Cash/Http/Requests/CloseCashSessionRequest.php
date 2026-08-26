<?php

namespace App\Modules\Cash\Http\Requests;

use App\Modules\Cash\Models\CashSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;
use LogicException;

class CloseCashSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $session = $this->route('cashSession');

        return $session instanceof CashSession
            && ($this->user()?->can('close', $session) ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'ledgers' => ['required', 'array', 'min:1'],
            'ledgers.*.uuid' => ['required', 'uuid'],
            'ledgers.*.actual_balance' => ['required', 'integer', 'min:0'],
            'ledgers.*.closing_comment' => ['nullable', 'string', 'max:2000'],
            'ledgers.*.evidence' => [
                'required',
                'file',
                File::types(['jpg', 'jpeg', 'png', 'webp'])->max(5120),
            ],
        ];
    }

    /** @return list<array{uuid: string, actual_balance: int, closing_comment: string|null, evidence: UploadedFile}> */
    public function ledgerData(): array
    {
        $data = $this->validated();

        $result = [];
        foreach ((array) ($data['ledgers'] ?? []) as $index => $ledger) {
            $values = is_array($ledger) ? $ledger : [];

            $result[] = [
                'uuid' => (string) ($values['uuid'] ?? ''),
                'actual_balance' => (int) ($values['actual_balance'] ?? 0),
                'closing_comment' => isset($values['closing_comment']) ? (string) $values['closing_comment'] : null,
                'evidence' => $this->evidenceFor('ledgers.'.(int) $index.'.evidence'),
            ];
        }

        return $result;
    }

    private function evidenceFor(string $key): UploadedFile
    {
        $file = $this->file($key);
        if (! $file instanceof UploadedFile) {
            throw new LogicException('A validated closing evidence file is missing.');
        }

        return $file;
    }
}
