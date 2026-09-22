<?php

namespace App\Modules\Departures\Http\Requests;

use App\Modules\Departures\Models\Departure;
use App\Modules\Settings\Settings\OperationsSettings;
use Illuminate\Foundation\Http\FormRequest;

class RecordDepartureDelayRequest extends FormRequest
{
    public function authorize(): bool
    {
        $departure = $this->route('departure');

        return $departure instanceof Departure
            && ($this->user()?->can('recordDelay', $departure) ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'delay_minutes' => [
                'required',
                'integer',
                'min:0',
                'max:'.app(OperationsSettings::class)->maxDepartureDelayMinutes,
            ],
            'delay_reason' => ['required', 'string', 'max:160'],
        ];
    }

    /** @return array{delay_minutes: int, delay_reason: string} */
    public function payload(): array
    {
        return [
            'delay_minutes' => $this->integer('delay_minutes'),
            'delay_reason' => $this->string('delay_reason')->trim()->value(),
        ];
    }
}
