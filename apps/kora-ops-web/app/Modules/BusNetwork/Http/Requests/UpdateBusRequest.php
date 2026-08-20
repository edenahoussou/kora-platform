<?php

namespace App\Modules\BusNetwork\Http\Requests;

use App\Modules\BusNetwork\Data\BusStatus;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\BusComposition;
use App\Modules\BusNetwork\Models\BusModel;
use App\Modules\IdentityAccess\Models\Agency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('bus')) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        /** @var Bus|null $bus */
        $bus = $this->route('bus');

        return [
            'registration_number' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9\s\-_]+$/', Rule::unique('buses', 'registration_number')->ignore($bus?->id)],
            'bus_model_id' => ['required', 'integer', Rule::exists(BusModel::class, 'id')->whereNull('deleted_at')],
            'composition_id' => ['required', 'integer', Rule::exists(BusComposition::class, 'id')->whereNull('deleted_at')],
            'agency_id' => ['required', 'integer', Rule::exists(Agency::class, 'id')->whereNull('deleted_at')],
            'status' => ['required', Rule::enum(BusStatus::class)],
        ];
    }

    /** @return array{registration_number: string, bus_model_id: int, composition_id: int, agency_id: int, status: string} */
    public function payload(): array
    {
        return [
            'registration_number' => strtoupper($this->string('registration_number')->trim()->value()),
            'bus_model_id' => $this->integer('bus_model_id'),
            'composition_id' => $this->integer('composition_id'),
            'agency_id' => $this->integer('agency_id'),
            'status' => $this->string('status')->trim()->value(),
        ];
    }
}
