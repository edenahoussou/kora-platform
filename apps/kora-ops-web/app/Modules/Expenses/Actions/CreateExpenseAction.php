<?php

namespace App\Modules\Expenses\Actions;

use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\Cash\Models\CashLedger;
use App\Modules\Departures\Models\Departure;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Models\ExpenseCategory;
use App\Modules\IdentityAccess\Models\Agency;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateExpenseAction
{
    /** @param array<string, mixed> $data */
    public function execute(User $actor, array $data): Expense
    {
        return DB::transaction(function () use ($actor, $data): Expense {
            $category = ExpenseCategory::query()->where('uuid', $data['category_uuid'])->where('is_active', true)->first();
            if ($category === null) {
                throw ValidationException::withMessages(['category_uuid' => 'La catégorie sélectionnée est inactive.']);
            }

            $agency = $this->resolveAgency($actor, $data);
            $bus = $this->resolveBus($data);
            $line = $this->resolveLine($data);
            $departure = $this->resolveDeparture($data);

            if ($departure !== null) {
                $bus ??= Bus::query()->withTrashed()->find($departure->bus_id);
                $line ??= Line::query()->withTrashed()->find($departure->line_id);
            }

            $ledger = null;
            if (! empty($data['ledger_uuid'])) {
                $ledger = CashLedger::query()->with('cashSession')
                    ->where('uuid', $data['ledger_uuid'])
                    ->whereHas('cashSession', fn ($query) => $query->where('agency_id', $agency->getKey()))
                    ->first();
                if ($ledger === null) {
                    throw ValidationException::withMessages(['ledger_uuid' => 'Le sous-journal sélectionné est invalide.']);
                }
            }

            $expense = Expense::query()->create([
                'agency_id' => $agency->getKey(),
                'bus_id' => $bus?->getKey(),
                'line_id' => $line?->getKey(),
                'departure_id' => $departure?->getKey(),
                'expense_category_id' => $category->getKey(),
                'cash_session_id' => $ledger?->cash_session_id,
                'cash_ledger_id' => $ledger?->getKey(),
                'status' => 'pending',
                'payment_channel' => $data['payment_channel'],
                'amount' => $data['amount'],
                'currency' => 'XOF',
                'payee_label' => $data['payee_label'],
                'payee_reference' => $data['payee_reference'] ?? null,
                'description' => $data['description'] ?? null,
                'occurred_at' => $data['occurred_at'],
                'created_by' => $actor->getKey(),
            ]);

            if (is_array($data['maintenance'] ?? null)) {
                $expense->maintenanceDetails()->create([
                    'maintenance_type' => $data['maintenance']['type'],
                    'garage_label' => $data['maintenance']['garage_label'] ?? null,
                    'parts_amount' => $data['maintenance']['parts_amount'] ?? null,
                    'labor_amount' => $data['maintenance']['labor_amount'] ?? null,
                    'immobilized_from' => $data['maintenance']['immobilized_from'] ?? null,
                    'immobilized_to' => $data['maintenance']['immobilized_to'] ?? null,
                ]);
            }

            $expense->addMedia($data['evidence'])->toMediaCollection('evidence');
            activity('expenses')->performedOn($expense)->causedBy($actor)->withProperties(['reference' => $expense->reference])->log('expense_created');

            return $expense;
        }, attempts: 3);
    }

    /** @param array<string, mixed> $data */
    private function resolveAgency(User $actor, array $data): Agency
    {
        if ($actor->agency_id !== null) {
            return Agency::query()->findOrFail($actor->agency_id);
        }

        return Agency::query()->where('uuid', $data['agency_uuid'] ?? null)->first()
            ?? throw ValidationException::withMessages(['agency_uuid' => 'L’agence support de la dépense est invalide.']);
    }

    /** @param array<string, mixed> $data */
    private function resolveBus(array $data): ?Bus
    {
        if (empty($data['bus_uuid'])) {
            return null;
        }

        return Bus::query()->withTrashed()->where('uuid', $data['bus_uuid'])->first()
            ?? throw ValidationException::withMessages(['bus_uuid' => 'Le bus sélectionné est invalide.']);
    }

    /** @param array<string, mixed> $data */
    private function resolveLine(array $data): ?Line
    {
        if (empty($data['line_uuid'])) {
            return null;
        }

        return Line::query()->withTrashed()->where('uuid', $data['line_uuid'])->first()
            ?? throw ValidationException::withMessages(['line_uuid' => 'La ligne sélectionnée est invalide.']);
    }

    /** @param array<string, mixed> $data */
    private function resolveDeparture(array $data): ?Departure
    {
        if (empty($data['departure_uuid'])) {
            return null;
        }

        return Departure::query()->withTrashed()->where('uuid', $data['departure_uuid'])->first()
            ?? throw ValidationException::withMessages(['departure_uuid' => 'Le départ sélectionné est invalide.']);
    }
}
