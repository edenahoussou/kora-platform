<?php

namespace App\Modules\Notifications\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/**
 * Diffusion temps réel d'une notification au canal privé de son destinataire.
 * Le corps est celui de l'API (OpsNotificationResource) : un client qui reçoit
 * l'événement et un client qui interroge l'API voient exactement la même chose.
 * Sans pilote temps réel configuré (`BROADCAST_CONNECTION=null`), rien n'est
 * diffusé et la récupération par l'API reste la référence.
 */
class OpsNotificationPublished implements ShouldBroadcast
{
    /**
     * @param  array<string, mixed>  $notification
     */
    public function __construct(
        public readonly string $userUuid,
        public readonly array $notification,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('ops-notifications.'.$this->userUuid)];
    }

    public function broadcastAs(): string
    {
        return 'ops.notification.published';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['notification' => $this->notification];
    }
}
