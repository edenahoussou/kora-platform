<?php

namespace App\Console\Commands\Payments;

use App\Modules\Payments\Actions\ApplyProviderResponseAction;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\PaymentManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReconcileMtnMomoPayments extends Command
{
    protected $signature = 'payments:reconcile-mtn-momo
        {--limit=50 : Maximum number of payments to reconcile in one run}
        {--stale-minutes=5 : Only reconcile payments not verified during this window}
        {--max-age-hours=24 : Ignore payments older than this horizon}
        {--dry-run : List candidates without calling MTN or writing}';

    protected $description = 'Reconcile pending MTN MoMo payments when callbacks are missing or delayed';

    public function handle(PaymentManager $manager, ApplyProviderResponseAction $applyResponse): int
    {
        $limit = max(1, min(200, (int) $this->option('limit')));
        $staleMinutes = max(1, min(1440, (int) $this->option('stale-minutes')));
        $maxAgeHours = max(1, min(720, (int) $this->option('max-age-hours')));
        $dryRun = (bool) $this->option('dry-run');

        $payments = Payment::query()
            ->where('provider', 'mtn_momo')
            ->where('status', PaymentStatus::Pending)
            ->whereNotNull('provider_reference')
            ->where('created_at', '>=', now()->subHours($maxAgeHours))
            ->where(function ($query) use ($staleMinutes): void {
                $query->whereNull('last_verified_at')
                    ->orWhere('last_verified_at', '<=', now()->subMinutes($staleMinutes));
            })
            ->orderByRaw('last_verified_at IS NULL DESC')
            ->orderBy('last_verified_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($payments->isEmpty()) {
            $this->info('No pending MTN MoMo payment to reconcile.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            foreach ($payments as $payment) {
                $this->line($payment->uuid.' pending');
            }

            return self::SUCCESS;
        }

        $adapter = $manager->resolve('mtn_momo');

        foreach ($payments as $payment) {
            try {
                $response = $adapter->verify($payment);
                $updated = $applyResponse->handle($payment, $response);

                if ($updated->status === PaymentStatus::Pending) {
                    $updated->update(['last_verified_at' => now()]);
                }
            } catch (Throwable $e) {
                Log::warning('MTN MoMo reconciliation interrupted', [
                    'payment_uuid' => $payment->uuid,
                    'exception' => $e::class,
                ]);

                $payment->update(['last_verified_at' => now()]);
            }
        }

        return self::SUCCESS;
    }
}
