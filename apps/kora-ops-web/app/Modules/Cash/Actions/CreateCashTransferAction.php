<?php

namespace App\Modules\Cash\Actions;

use App\Models\User;
use App\Modules\Cash\Data\CashRegisterKind;
use App\Modules\Cash\Data\CashRegisterStatus;
use App\Modules\Cash\Data\CashTransferStatus;
use App\Modules\Cash\Models\CashRegister;
use App\Modules\Cash\Models\CashTransfer;
use App\Modules\Cash\Services\CashRegisterBalance;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateCashTransferAction
{
    public function __construct(private readonly CashRegisterBalance $balances) {}

    /** @param array<string, mixed> $attributes */
    public function execute(User $user, array $attributes): CashTransfer
    {
        if ($user->agency_id === null) {
            throw ValidationException::withMessages(['transfer' => 'Votre profil n’est rattaché à aucune agence.']);
        }

        return DB::transaction(function () use ($user, $attributes): CashTransfer {
            $source = $this->register($user, $attributes['source_cash_register_uuid'] ?? null);
            $destination = $this->register($user, $attributes['destination_cash_register_uuid'] ?? null);
            $destinationType = $attributes['destination_type'] ?? null;

            if ($source instanceof CashRegister && $destination instanceof CashRegister) {
                if ($source->isVault() || ! $destination->isVault()) {
                    throw ValidationException::withMessages(['destination_cash_register_uuid' => 'Seul un transfert guichet vers le coffre local est autorisé.']);
                }
            } elseif ($source instanceof CashRegister && $destinationType === 'direction') {
                // Reversement vers le coffre externe de KORA Direction.
            } elseif ($source === null && $destination instanceof CashRegister && $destination->isVault() && $destinationType === 'direction') {
                // Financement entrant depuis KORA Direction.
            } else {
                throw ValidationException::withMessages(['transfer' => 'Le sens du transfert est invalide.']);
            }

            $amount = (int) ($attributes['amount'] ?? 0);
            if ($source instanceof CashRegister && $this->balances->available($source) < $amount) {
                throw ValidationException::withMessages(['amount' => 'Le solde disponible de la caisse est insuffisant.']);
            }

            return CashTransfer::query()->create([
                'agency_id' => $user->agency_id,
                'source_cash_register_id' => $source?->getKey(),
                'destination_cash_register_id' => $destination?->getKey(),
                'destination_type' => $destinationType,
                'amount' => $amount,
                'currency' => strtoupper((string) ($attributes['currency'] ?? 'XOF')),
                'status' => CashTransferStatus::Pending,
                'reference' => $attributes['reference'] ?? null,
                'description' => $attributes['description'] ?? null,
                'created_by' => $user->getKey(),
            ]);
        });
    }

    private function register(User $user, mixed $uuid): ?CashRegister
    {
        if (! is_string($uuid) || $uuid === '') {
            return null;
        }

        $register = CashRegister::query()
            ->where('uuid', $uuid)
            ->where('agency_id', $user->agency_id)
            ->where('status', CashRegisterStatus::Active->value)
            ->first();

        if (! $register instanceof CashRegister) {
            throw ValidationException::withMessages(['transfer' => 'La caisse sélectionnée est introuvable ou inactive.']);
        }

        if ($register->kind === CashRegisterKind::Counter
            && $register->authorizedUsers()->exists()
            && ! $register->authorizedUsers()->whereKey($user->getKey())->exists()) {
            throw ValidationException::withMessages(['transfer' => 'Vous n’êtes pas autorisé à utiliser cette caisse.']);
        }

        return $register;
    }
}
