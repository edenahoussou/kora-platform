<?php

namespace App\Notifications;

use App\Models\User;
use App\Modules\KoraPoints\Models\KoraPointOrganization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class KoraPointSettlementOverdue extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $saleUuid, public string $pointUuid, public string $eventUuid)
    {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['opsDatabase'];
    }

    /** @return array<string, mixed> */
    public function toArray(User $notifiable): array
    {
        $organizationUuid = $notifiable->kora_point_organization_id === null ? null : KoraPointOrganization::query()->whereKey($notifiable->kora_point_organization_id)->value('uuid');
        $url = $organizationUuid === null ? route('kora-points.show', $this->pointUuid) : route('kora-points.organizations.show', $organizationUuid);

        return ['event_uuid' => $this->eventUuid, 'title' => 'Reversement KORA POINT en retard',
            'type' => 'kora_point_settlement_overdue', 'severity' => 'warning', 'action' => 'view_kora_point',
            'message' => 'Une échéance de reversement est dépassée. Consultez la situation de votre Point.',
            'data' => ['sale_uuid' => $this->saleUuid, 'point_uuid' => $this->pointUuid],
            'url' => $url];
    }
}
