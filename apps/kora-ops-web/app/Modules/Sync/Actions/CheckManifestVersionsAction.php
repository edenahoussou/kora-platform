<?php

namespace App\Modules\Sync\Actions;

use App\Models\User;
use App\Modules\Departures\Actions\ComputeManifestVersionAction;
use App\Modules\Departures\Actions\ResolveManifestSegmentsAction;
use App\Modules\Departures\Services\CrewDepartureScope;

/**
 * Vérifie en un seul aller-retour, pour plusieurs départs à la fois, si
 * le manifeste que le terminal a déjà en cache est encore à jour — évite
 * de retélécharger le manifeste complet de chaque départ juste pour
 * savoir s'il a changé. Le re-téléchargement effectif reste la
 * responsabilité de GET /api/v1/ops/departures/{departure}/manifest
 * (déjà existant) une fois la péremption détectée : "atomique" au sens
 * où cet appel renvoie l'état complet en une fois, jamais partiel.
 */
class CheckManifestVersionsAction
{
    public function __construct(
        private CrewDepartureScope $crewScope,
        private ResolveManifestSegmentsAction $resolveSegments,
        private ComputeManifestVersionAction $computeVersion,
    ) {}

    /**
     * @param  array<int, array{departure_uuid: string, known_version: string|null}>  $requested
     * @return array<int, array{departureUuid: string, currentVersion: string|null, stale: bool, accessible: bool}>
     */
    public function execute(User $user, array $requested): array
    {
        $accessibleDepartures = $this->crewScope->query($user)
            ->whereIn('uuid', array_column($requested, 'departure_uuid'))
            ->with('line:id,uuid,code,name,type')
            ->get()
            ->keyBy('uuid');

        return array_map(function (array $item) use ($accessibleDepartures) {
            $departure = $accessibleDepartures->get($item['departure_uuid']);

            if ($departure === null) {
                // Départ inconnu, annulé, ou hors périmètre pour cet
                // utilisateur : rien à synchroniser pour lui, sans
                // révéler s'il existe (même traitement que viewAsCrew).
                return [
                    'departureUuid' => $item['departure_uuid'],
                    'currentVersion' => null,
                    'stale' => false,
                    'accessible' => false,
                ];
            }

            [$segments, $ticketsByPassengerId] = $this->resolveSegments->execute($departure);
            $currentVersion = $this->computeVersion->execute($departure, $segments, $ticketsByPassengerId);

            return [
                'departureUuid' => $departure->uuid,
                'currentVersion' => $currentVersion,
                'stale' => $item['known_version'] !== $currentVersion,
                'accessible' => true,
            ];
        }, $requested);
    }
}
