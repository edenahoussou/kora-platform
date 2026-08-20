<?php

namespace App\Modules\Departures\Http\Requests;

use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeDepartureStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $departure = $this->route('departure');
        $departure = $departure instanceof Departure ? $departure : new Departure;
        $targetStatus = $this->string('status')->trim()->value();

        if ($targetStatus === DepartureStatus::Cancelled->value) {
            return $this->user()?->can('delete', $departure) ?? false;
        }

        return $this->user()?->can('update', $departure) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(DepartureStatus::class)]];
    }

    public function status(): DepartureStatus
    {
        return DepartureStatus::from($this->string('status')->trim()->value());
    }
}
