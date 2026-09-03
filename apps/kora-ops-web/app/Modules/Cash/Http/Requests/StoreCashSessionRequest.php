<?php

namespace App\Modules\Cash\Http\Requests;

use App\Modules\Cash\Models\CashSession;
use App\Modules\Cash\Rules\RecentCameraCapture;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;
use LogicException;

class StoreCashSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CashSession::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'ledgers' => ['required', 'array', 'min:1'],
            'ledgers.*.kind' => ['required', 'string', 'in:cash,mobile_money'],
            'ledgers.*.account_uuid' => ['nullable', 'uuid'],
            'ledgers.*.opening_balance' => ['required', 'integer', 'min:0'],
            'ledgers.*.opening_comment' => ['nullable', 'string', 'max:2000'],
            'ledgers.*.evidence' => [
                'required',
                'file',
                File::types(['jpg', 'jpeg', 'png', 'webp'])->max(5120),
            ],
            // Captured client-side at the exact moment the live snapshot is
            // taken (see CameraCapture.tsx). There is no file-picker path
            // that can populate this field with a plausible recent value,
            // which is what makes it a meaningful proxy for "this evidence
            // came from a live photo" rather than an uploaded file.
            'ledgers.*.evidence_captured_at' => ['required', 'date', new RecentCameraCapture],
        ];
    }

    /** @return list<array{kind: string, account_uuid: string|null, opening_balance: int, opening_comment: string|null, evidence: UploadedFile, evidence_captured_at: string}> */
    public function ledgerData(): array
    {
        $data = $this->validated();

        $result = [];
        foreach ((array) ($data['ledgers'] ?? []) as $index => $ledger) {
            $values = is_array($ledger) ? $ledger : [];

            $result[] = [
                'kind' => (string) ($values['kind'] ?? ''),
                'account_uuid' => isset($values['account_uuid']) ? (string) $values['account_uuid'] : null,
                'opening_balance' => (int) ($values['opening_balance'] ?? 0),
                'opening_comment' => isset($values['opening_comment']) ? (string) $values['opening_comment'] : null,
                'evidence' => $this->evidenceFor('ledgers.'.(int) $index.'.evidence'),
                'evidence_captured_at' => (string) ($values['evidence_captured_at'] ?? ''),
            ];
        }

        return $result;
    }

    private function evidenceFor(string $key): UploadedFile
    {
        $file = $this->file($key);
        if (! $file instanceof UploadedFile) {
            throw new LogicException('A validated cash ledger evidence file is missing.');
        }

        return $file;
    }
}
