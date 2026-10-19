<?php

namespace App\Modules\Cash\Http\Requests;

use App\Modules\Cash\Models\CashSession;
use App\Modules\Cash\Services\CashJournalQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCashMovementsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', CashSession::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:200'],
            'channel' => ['nullable', Rule::in(['all', ...array_keys(CashJournalQuery::CHANNELS)])],
            'status' => ['nullable', Rule::in(['all', ...array_keys(CashJournalQuery::STATUSES)])],
            'direction' => ['nullable', Rule::in(['all', 'in', 'out'])],
            'nature' => ['nullable', Rule::in(CashJournalQuery::NATURES)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'agent' => ['nullable', 'uuid'],
            'session_uuid' => ['nullable', 'uuid'],
            'session_detail' => ['nullable', 'uuid'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array{q: string, channel: string, status: string, direction: string, nature: string, from: string, to: string, agent: string, session_uuid: string} */
    public function filters(): array
    {
        return [
            'q' => trim((string) $this->validated('q', '')),
            'channel' => $this->validated('channel') ?: 'all',
            'status' => $this->validated('status') ?: 'all',
            'direction' => $this->validated('direction') ?: 'all',
            'nature' => $this->validated('nature') ?: 'all',
            'from' => trim((string) $this->validated('from', '')),
            'to' => trim((string) $this->validated('to', '')),
            'agent' => trim((string) $this->validated('agent', '')),
            'session_uuid' => trim((string) $this->validated('session_uuid', '')),
        ];
    }
}
