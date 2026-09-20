<?php

namespace App\Modules\Notifications\Http\Requests;

use App\Modules\Notifications\Data\OpsNotificationSeverity;
use App\Modules\Notifications\Data\OpsNotificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class ListOpsNotificationsRequest extends FormRequest
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
            'unread' => ['sometimes', 'boolean'],
            'type' => ['sometimes', Rule::enum(OpsNotificationType::class)],
            'severity' => ['sometimes', Rule::enum(OpsNotificationSeverity::class)],
            'since' => ['sometimes', 'date'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.(int) config('ops_notifications.pagination.max', 100)],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function perPage(): int
    {
        return (int) $this->integer('per_page', (int) config('ops_notifications.pagination.default', 30));
    }

    /**
     * @return array{unread: bool, type: string|null, severity: string|null, since: Carbon|null}
     */
    public function filters(): array
    {
        return [
            'unread' => $this->boolean('unread'),
            'type' => $this->filled('type') ? $this->string('type')->toString() : null,
            'severity' => $this->filled('severity') ? $this->string('severity')->toString() : null,
            'since' => $this->filled('since') ? Carbon::parse($this->string('since')->toString())->setTimezone((string) config('app.timezone')) : null,
        ];
    }
}
