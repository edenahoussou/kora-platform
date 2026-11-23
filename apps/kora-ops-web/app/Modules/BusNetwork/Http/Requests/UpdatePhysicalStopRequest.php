<?php

namespace App\Modules\BusNetwork\Http\Requests;

use App\Modules\BusNetwork\Actions\FindOrCreatePhysicalStopAction;
use App\Modules\BusNetwork\Models\PhysicalStop;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePhysicalStopRequest extends FormRequest
{
    public function authorize(): bool
    {
        $physicalStop = $this->route('physicalStop');

        return $physicalStop instanceof PhysicalStop
            && ($this->user()?->can('update', $physicalStop) ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'size:2'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'status' => ['sometimes', 'string', 'in:active,inactive,archived'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['name', 'city', 'country'])) {
                return;
            }

            $physicalStop = $this->route('physicalStop');
            $duplicate = FindOrCreatePhysicalStopAction::findDuplicateByName(
                $this->string('name')->trim()->value(),
                $this->string('city')->trim()->value(),
                $this->string('country')->trim()->value(),
                $physicalStop instanceof PhysicalStop ? $physicalStop->uuid : null,
            );

            if ($duplicate !== null) {
                $validator->errors()->add(
                    'name',
                    "Un autre point d’arrêt « {$duplicate->name} » ({$duplicate->code}) existe déjà à {$duplicate->city}. Utilisez-le plutôt que de créer un doublon.",
                );
            }
        });
    }

    /** @return array{name: string, city: string, country: string, latitude: float|null, longitude: float|null, status?: string} */
    public function payload(): array
    {
        $payload = [
            'name' => $this->string('name')->trim()->value(),
            'city' => $this->string('city')->trim()->value(),
            'country' => strtoupper($this->string('country')->trim()->value()),
            'latitude' => $this->filled('latitude') ? (float) $this->input('latitude') : null,
            'longitude' => $this->filled('longitude') ? (float) $this->input('longitude') : null,
        ];

        if ($this->has('status')) {
            $payload['status'] = $this->string('status')->trim()->value();
        }

        return $payload;
    }
}
