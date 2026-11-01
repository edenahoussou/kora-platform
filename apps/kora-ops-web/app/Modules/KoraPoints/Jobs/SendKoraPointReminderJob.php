<?php

namespace App\Modules\KoraPoints\Jobs;

use App\Models\User;
use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\KoraPoints\Models\KoraPointSale;
use App\Notifications\KoraPointSettlementOverdue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class SendKoraPointReminderJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $reminderId) {}

    /**
     * Sends the overdue-settlement reminder once to point agents, organization officers and finance.
     */
    public function handle(): void
    {
        DB::transaction(function (): void {
            $reminder = DB::table('kora_point_reminders')->where('id', $this->reminderId)->lockForUpdate()->first();
            if ($reminder === null || $reminder->dispatched_at !== null) {
                return;
            }
            $sale = KoraPointSale::query()->with('point')->whereKey($reminder->sale_id)->first();
            if ($sale === null || $sale->outstanding_amount <= 0 || $sale->due_at->isFuture()) {
                DB::table('kora_point_reminders')->where('id', $this->reminderId)->update(['dispatched_at' => now()]);

                return;
            }
            $eventUuid = substr(hash('sha256', 'kora-point-reminder:'.$sale->uuid.':'.$reminder->period_key), 0, 32);
            $recipients = User::query()->where('status', UserStatus::Active->value)->where(function (Builder $query) use ($sale): void {
                $query->where(function (Builder $query) use ($sale): void {
                    $query->where('agency_id', $sale->point->agency_id)->whereHas('roles', fn (Builder $roles) => $roles->where('name', 'kora_point_agent'));
                });
                if ($sale->organization_id !== null) {
                    $query->orWhere(function (Builder $query) use ($sale): void {
                        $query->where('kora_point_organization_id', $sale->organization_id)->whereHas('roles', fn (Builder $roles) => $roles->whereIn('name', ['kora_point_supervisor', 'kora_point_treasurer']));
                    });
                }
                $query->orWhereHas('roles', fn (Builder $roles) => $roles->whereIn('name', ['daf', 'system_admin']));
            })->get();
            Notification::sendNow($recipients, new KoraPointSettlementOverdue($sale->uuid, $sale->point->uuid, $eventUuid));
            DB::table('kora_point_reminders')->where('id', $this->reminderId)->update(['dispatched_at' => now()]);
        });
    }
}
