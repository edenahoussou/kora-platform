<?php

namespace App\Modules\Expenses\Http\Requests;

use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\Departures\Models\Departure;
use App\Modules\Expenses\Data\MaintenanceType;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Models\ExpenseCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Expense::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category_uuid' => ['required', 'uuid', Rule::exists('expense_categories', 'uuid')->where('is_active', true)],
            'ledger_uuid' => ['nullable', 'uuid'],
            'agency_uuid' => ['nullable', 'uuid', Rule::exists('agencies', 'uuid')],
            'bus_uuid' => ['nullable', 'uuid', Rule::exists('buses', 'uuid')],
            'line_uuid' => ['nullable', 'uuid', Rule::exists('lines', 'uuid')],
            'departure_uuid' => ['nullable', 'uuid', Rule::exists('departures', 'uuid')],
            'payment_channel' => ['required', Rule::in(['cash', 'mtn_momo', 'moov_flooz', 'celtiis_cash'])],
            'amount' => ['required', 'integer', 'min:1'],
            'payee_label' => ['required', 'string', 'max:160'],
            'payee_reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'occurred_at' => ['required', 'date'],
            'evidence' => ['required', 'file', File::types(['jpg', 'jpeg', 'png', 'webp', 'pdf'])->max(5120)],
            'maintenance' => ['nullable', 'array'],
            'maintenance.type' => ['required_with:maintenance', Rule::in(MaintenanceType::values())],
            'maintenance.garage_label' => ['nullable', 'string', 'max:160'],
            'maintenance.parts_amount' => ['nullable', 'integer', 'min:0'],
            'maintenance.labor_amount' => ['nullable', 'integer', 'min:0'],
            'maintenance.immobilized_from' => ['nullable', 'date_format:Y-m-d'],
            'maintenance.immobilized_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:maintenance.immobilized_from'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $centers = array_filter([
                $this->input('bus_uuid'),
                $this->input('line_uuid'),
                $this->input('departure_uuid'),
            ]);

            if (count($centers) > 1) {
                $validator->errors()->add('bus_uuid', 'Une dépense ne porte qu’un seul centre de coût.');
            }

            if ($centers !== [] && ! ($this->user()?->can('attachCostCenter', Expense::class) ?? false)) {
                $validator->errors()->add('bus_uuid', 'Votre périmètre ne permet pas de rattacher un bus, une ligne ou un départ.');
            }

            if ($this->user()?->agency_id === null && ! $this->filled('agency_uuid')) {
                $validator->errors()->add('agency_uuid', 'Sélectionnez l’agence support de la dépense.');
            }

            if ($this->filled('departure_uuid')) {
                $departure = Departure::query()->withTrashed()->where('uuid', $this->input('departure_uuid'))->first();

                if ($departure !== null && $this->filled('bus_uuid')) {
                    $bus = Bus::query()->withTrashed()->where('uuid', $this->input('bus_uuid'))->first();
                    if ($bus !== null && $departure->bus_id !== $bus->getKey()) {
                        $validator->errors()->add('bus_uuid', 'Le bus ne correspond pas au départ sélectionné.');
                    }
                }

                if ($departure !== null && $this->filled('line_uuid')) {
                    $line = Line::query()->withTrashed()->where('uuid', $this->input('line_uuid'))->first();
                    if ($line !== null && $departure->line_id !== $line->getKey()) {
                        $validator->errors()->add('line_uuid', 'La ligne ne correspond pas au départ sélectionné.');
                    }
                }
            }

            if ($this->has('maintenance') && $this->input('maintenance') !== null) {
                $categoryCode = ExpenseCategory::query()->where('uuid', $this->input('category_uuid'))->value('code');

                if ($categoryCode !== 'maintenance') {
                    $validator->errors()->add('maintenance', 'Les champs de maintenance ne s’appliquent qu’à la catégorie Maintenance.');
                }
            }
        }];
    }

    /** @return array<string, mixed> */
    public function expenseData(): array
    {
        return [
            'category_uuid' => (string) $this->validated('category_uuid'),
            'ledger_uuid' => $this->validated('ledger_uuid'),
            'agency_uuid' => $this->validated('agency_uuid'),
            'bus_uuid' => $this->validated('bus_uuid'),
            'line_uuid' => $this->validated('line_uuid'),
            'departure_uuid' => $this->validated('departure_uuid'),
            'payment_channel' => (string) $this->validated('payment_channel'),
            'amount' => (int) $this->validated('amount'),
            'payee_label' => (string) $this->validated('payee_label'),
            'payee_reference' => $this->validated('payee_reference'),
            'description' => $this->validated('description'),
            'occurred_at' => $this->date('occurred_at'),
            'evidence' => $this->file('evidence'),
            'maintenance' => $this->validated('maintenance'),
        ];
    }
}
