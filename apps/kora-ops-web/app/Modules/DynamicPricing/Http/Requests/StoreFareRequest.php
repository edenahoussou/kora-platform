<?php

namespace App\Modules\DynamicPricing\Http\Requests;

use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\DynamicPricing\Data\FareDirection;
use App\Modules\DynamicPricing\Models\Fare;
use App\Modules\DynamicPricing\Models\ServiceClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Fare::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'line_id' => ['required', 'string', 'exists:lines,uuid'],
            'service_class_id' => ['nullable', 'string', 'exists:service_classes,uuid'],
            'origin_stop_id' => ['required', 'string', 'exists:stops,uuid'],
            'destination_stop_id' => ['required', 'string', 'exists:stops,uuid', 'different:origin_stop_id'],
            'direction' => ['nullable', 'string', Rule::in(array_column(FareDirection::cases(), 'value'))],
            'base_amount' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
            'effective_from' => ['required', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ];
    }

    /**
     * Validate that origin and destination belong to the selected line and are ordered correctly.
     */
    protected function passedValidation(): void
    {
        $line = Line::query()->where('uuid', $this->string('line_id')->value())->firstOrFail();
        $origin = Stop::query()->where('uuid', $this->string('origin_stop_id')->value())->firstOrFail();
        $destination = Stop::query()->where('uuid', $this->string('destination_stop_id')->value())->firstOrFail();

        if ($origin->line_id !== $line->id) {
            $this->getValidatorInstance()->errors()->add('origin_stop_id', __('L\'arrêt d\'origine n\'appartient pas à cette ligne.'));
        }

        if ($destination->line_id !== $line->id) {
            $this->getValidatorInstance()->errors()->add('destination_stop_id', __('L\'arrêt de destination n\'appartient pas à cette ligne.'));
        }

        if ($origin->stop_order >= $destination->stop_order) {
            $this->getValidatorInstance()->errors()->add('destination_stop_id', __('La destination doit être après l\'origine dans l\'itinéraire.'));
        }
    }

    /**
     * @return array{line_id: int, service_class_id: int, origin_stop_id: int, destination_stop_id: int, direction: string, base_amount: int, currency: string, effective_from: string, effective_until: string|null}
     */
    public function payload(): array
    {
        $line = Line::query()->where('uuid', $this->string('line_id')->value())->firstOrFail();
        $serviceClass = $this->filled('service_class_id')
            ? ServiceClass::query()->where('uuid', $this->string('service_class_id')->value())->firstOrFail()
            : ServiceClass::standard();
        $origin = Stop::query()->where('uuid', $this->string('origin_stop_id')->value())->firstOrFail();
        $destination = Stop::query()->where('uuid', $this->string('destination_stop_id')->value())->firstOrFail();

        $direction = $this->string('direction')->value();
        if (! in_array($direction, array_column(FareDirection::cases(), 'value'), true)) {
            $direction = FareDirection::Both->value;
        }

        return [
            'line_id' => $line->id,
            'service_class_id' => $serviceClass->id,
            'origin_stop_id' => $origin->id,
            'destination_stop_id' => $destination->id,
            'direction' => $direction,
            'base_amount' => (int) $this->input('base_amount'),
            'currency' => strtoupper($this->string('currency')->trim()->value()),
            'effective_from' => $this->string('effective_from')->value(),
            'effective_until' => $this->filled('effective_until') ? $this->string('effective_until')->value() : null,
        ];
    }
}
