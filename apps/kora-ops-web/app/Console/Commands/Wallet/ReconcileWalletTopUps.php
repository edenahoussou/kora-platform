<?php

namespace App\Console\Commands\Wallet;

use App\Modules\Wallet\Actions\ReconcileWalletTopUpAction;
use App\Modules\Wallet\Data\WalletTopUpStatus;
use App\Modules\Wallet\Models\WalletTopUp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReconcileWalletTopUps extends Command
{
    protected $signature = 'wallet:reconcile-topups {--dry-run : Classer les recharges sans appel opérateur ni écriture}';

    protected $description = 'Rapproche les recharges Wallet en attente';

    public function handle(ReconcileWalletTopUpAction $reconcile): int
    {
        $query = WalletTopUp::query()->with('payment')->where('status', WalletTopUpStatus::Pending);
        if (! $this->option('dry-run')) {
            $query->where(fn ($due) => $due->whereNull('next_reconciliation_at')->orWhere('next_reconciliation_at', '<=', now()))->limit(50);
        }

        foreach ($query->orderBy('id')->get() as $topup) {
            if ($this->option('dry-run')) {
                $this->line($topup->uuid.' '.$reconcile->state($topup));

                continue;
            }

            try {
                $reconcile->recover($topup);
            } catch (Throwable $e) {
                Log::warning('Wallet top-up reconciliation interrupted', [
                    'topup_uuid' => $topup->uuid,
                    'exception' => $e::class,
                ]);
            }

            $topup->refresh();
            if ($topup->status === WalletTopUpStatus::Pending) {
                $attempt = $topup->reconciliation_attempts + 1;
                $topup->update([
                    'reconciliation_attempts' => $attempt,
                    'next_reconciliation_at' => now()->addMinutes(min(720, 5 * 2 ** min($attempt, 8))),
                ]);
                if ($attempt === 6) {
                    Log::error('Wallet top-up requires OPS reconciliation', ['topup_uuid' => $topup->uuid, 'payment_uuid' => $topup->payment->uuid]);
                }
            }
        }

        return self::SUCCESS;
    }
}
