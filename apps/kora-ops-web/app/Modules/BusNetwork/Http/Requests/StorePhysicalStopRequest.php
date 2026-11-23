<?php

namespace App\Modules\BusNetwork\Http\Requests;

use App\Modules\BusNetwork\Actions\FindOrCreatePhysicalStopAction;
use App\Modules\BusNetwork\Models\PhysicalStop;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StorePhysicalStopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PhysicalStop::class) ?? false;
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
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['name', 'city', 'country'])) {
                return;
            }

            $duplicate = FindOrCreatePhysicalStopAction::findDuplicateByName(
                $this->string('name')->trim()->value(),
                $this->string('city')->trim()->value(),
                $this->string('country')->trim()->value(),
            );

            if ($duplicate !== null) {
                $validator->errors()->add(
                    'name',
                    "Un point d’arrêt « {$duplicate->name} » ({$duplicate->code}) existe déjà à {$duplicate->city}. Utilisez-le plutôt que de créer un doublon.",
                );
            }
        });
    }

    /** @return array{name: string, city: string, country: string, latitude: float|null, longitude: float|null} */
    public function payload(): array
    {
        return [
            'name' => $this->string('name')->trim()->value(),
            'city' => $this->string('city')->trim()->value(),
            'country' => strtoupper($this->string('country')->trim()->value()),
            'latitude' => $this->filled('latitude') ? (float) $this->input('latitude') : null,
            'longitude' => $this->filled('longitude') ? (float) $this->input('longitude') : null,
        ];
    }
}
