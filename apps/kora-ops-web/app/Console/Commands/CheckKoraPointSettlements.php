<?php

namespace App\Console\Commands;

use App\Modules\KoraPoints\Jobs\SendKoraPointReminderJob;
use App\Modules\KoraPoints\Models\KoraPointSale;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckKoraPointSettlements extends Command
{
    protected $signature = 'kora-points:check-settlements';

    protected $description = 'Signale les échéances partenaires impayées sans modifier le journal financier.';

    public function handle(): int
    {
        KoraPointSale::query()->where('outstanding_amount', '>', 0)->where('due_at', '<=', now())->orderBy('id')->chunkById(100, function ($sales): void {
            foreach ($sales as $sale) {
                $key = now()->utc()->toDateString();
                DB::table('kora_point_reminders')->insertOrIgnore(['sale_id' => $sale->getKey(), 'period_key' => $key, 'created_at' => now(), 'updated_at' => now()]);
                $reminder = DB::table('kora_point_reminders')->where('sale_id', $sale->getKey())->where('period_key', $key)->first();
                if ($reminder->dispatched_at !== null) {
                    continue;
                }
                SendKoraPointReminderJob::dispatch($reminder->id)->afterCommit();
            }
        });

        return self::SUCCESS;
    }
}
