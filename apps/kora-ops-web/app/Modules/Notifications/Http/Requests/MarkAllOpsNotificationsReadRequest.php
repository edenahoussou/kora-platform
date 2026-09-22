<?php

namespace App\Modules\Notifications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class MarkAllOpsNotificationsReadRequest extends FormRequest
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
            // Ne marque lues que les notifications créées jusqu'à cet instant :
            // celles reçues depuis restent non lues.
            'before' => ['sometimes', 'date'],
        ];
    }

    public function before(): ?Carbon
    {
        // Database timestamps are stored in UTC; preserve the instant instead
        // of converting to the display timezone before the SQL comparison.
        return $this->filled('before') ? Carbon::parse($this->string('before')->toString())->utc() : null;
    }
}
