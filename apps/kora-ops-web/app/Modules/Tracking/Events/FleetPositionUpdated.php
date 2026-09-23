<?php

namespace App\Modules\Tracking\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/**
 * Diffusion temps réel d'une position acceptée vers OPS Web. Le corps est
 * celui de l'API (FleetTrackingResource) : un client qui écoute l'événement
 * et un client qui interroge fleet/tracking voient la même chose. Sans
 * pilote temps réel configuré (`BROADCAST_CONNECTION=null`), rien n'est
 * diffusé et l'API HTTP reste le contrat principal (voir ticket MEZ-100).
 *
 * Canal par agence (pas par utilisateur), plus le canal `network` : c'est le
 * périmètre déjà utilisé pour lire fleet/tracking, donc l'autorisation du
 * canal (routes/channels.php) applique exactement la même règle des deux
 * côtés. Diffusé sur les deux à la fois : le canal réseau (pour Directeur
 * des opérations, Directeur commercial, Direction générale, Admin système)
 * et, si le terminal est rattaché à une agence, le canal de cette agence
 * (pour son gestionnaire).
 */
class FleetPositionUpdated implements ShouldBroadcast
{
    /**
     * @param  array<string, mixed>  $position
     */
    public function __construct(
        public readonly ?string $agencyUuid,
        public readonly array $position,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('fleet-tracking.network')];

        if ($this->agencyUuid !== null) {
            $channels[] = new PrivateChannel('fleet-tracking.'.$this->agencyUuid);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'fleet.tracking.position-updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['position' => $this->position];
    }
}
