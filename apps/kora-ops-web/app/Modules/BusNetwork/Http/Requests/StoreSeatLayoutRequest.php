<?php

namespace App\Modules\BusNetwork\Http\Requests;

use App\Modules\BusNetwork\Data\LayoutCellKind;
use App\Modules\BusNetwork\Data\SeatLayout;
use App\Modules\BusNetwork\Data\SeatType;
use App\Modules\BusNetwork\Models\BusComposition;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSeatLayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', BusComposition::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'grid' => ['required', 'array'],
            'grid.rows' => ['required', 'integer', 'min:1', 'max:40'],
            'grid.cols' => ['required', 'integer', 'min:1', 'max:12'],
            'grid.cells' => ['required', 'array'],
            'grid.cells.*.kind' => ['required', Rule::enum(LayoutCellKind::class)],
            'grid.cells.*.x' => ['required', 'integer', 'min:0'],
            'grid.cells.*.y' => ['required', 'integer', 'min:0'],
            'grid.cells.*.code' => ['nullable', 'string', 'max:8', 'regex:/^[A-Z0-9][A-Z0-9-]*$/i'],
            'grid.cells.*.seatKind' => ['nullable', Rule::enum(SeatType::class)],
            'grid.cells.*.codeLocked' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('grid.cells') || ! is_array($this->input('grid'))) {
                return;
            }

            foreach (SeatLayout::fromGridArray($this->input('grid'))->errors() as $error) {
                $validator->errors()->add('grid', $error);
            }
        });
    }

    /** @return array{name: string, description: string|null, grid: array<string, mixed>} */
    public function payload(): array
    {
        return [
            'name' => $this->string('name')->trim()->value(),
            'description' => $this->filled('description') ? $this->string('description')->trim()->value() : null,
            'grid' => SeatLayout::fromGridArray($this->input('grid'))->toArray(),
        ];
    }
}
