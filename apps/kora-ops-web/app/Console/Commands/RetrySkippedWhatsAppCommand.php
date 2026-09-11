<?php

namespace App\Console\Commands;

use App\Jobs\SendReservationWhatsAppNotificationJob;
use App\Modules\Notifications\Data\WhatsAppDeliveryStatus;
use App\Modules\Notifications\Models\WhatsAppDelivery;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RetrySkippedWhatsAppCommand extends Command
{
    protected $signature = 'whatsapp:retry-skipped
                            {--hours=2 : Âge minimum (en heures) des livraisons Skipped à relancer}
                            {--limit=50 : Nombre maximum de livraisons à relancer par exécution}';

    protected $description = 'Re-dispatche les livraisons WhatsApp Skipped (WAHA 463/475) vieilles de +--hours heures.';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $limit = (int) $this->option('limit');
        $cutoff = Carbon::now()->subHours($hours);

        $deliveries = WhatsAppDelivery::query()
            ->where('status', WhatsAppDeliveryStatus::Skipped->value)
            ->where('updated_at', '<=', $cutoff)
            ->limit($limit)
            ->orderBy('updated_at')
            ->get();

        if ($deliveries->isEmpty()) {
            $this->info('Aucune livraison WhatsApp Skipped à relancer.');

            return self::SUCCESS;
        }

        $count = 0;
        foreach ($deliveries as $delivery) {
            $delivery->update([
                'status' => WhatsAppDeliveryStatus::Pending,
                'last_error' => null,
            ]);

            SendReservationWhatsAppNotificationJob::dispatch(
                $delivery->reservation_id,
                $delivery->ticket_id,
                $delivery->recipient_phone,
            );
            $count++;
        }

        $this->info("{$count} livraison(s) WhatsApp Skipped re-dispatchée(s) vers le queue.");

        return self::SUCCESS;
    }
}
