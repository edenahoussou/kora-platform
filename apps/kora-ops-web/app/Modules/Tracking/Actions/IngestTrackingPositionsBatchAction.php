<?php

namespace App\Modules\Tracking\Actions;

use App\Models\User;
use App\Modules\BusNetwork\Data\StopStatus;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\Settings\Settings\OperationsSettings;
use App\Modules\Tracking\Data\TrackingPositionQuality;
use App\Modules\Tracking\Data\TrackingPositionStatus;
use App\Modules\Tracking\Events\FleetPositionUpdated;
use App\Modules\Tracking\Exceptions\TrackingException;
use App\Modules\Tracking\Models\TrackingPosition;
use App\Modules\Tracking\Models\TrackingSession;
use App\Modules\Tracking\Services\FleetTrackingQuery;
use App\Modules\Tracking\Services\TrackingPositionCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

use function activity;

/**
 * Point d'entrée unique de l'ingestion d'un lot de positions (ticket
 * MEZ-100). Idempotent par position (`uuid` fourni par le terminal) : un lot
 * rejoué en tout ou partie ne crée jamais de doublon, chaque position étant
 * traitée indépendamment des autres — un lot partiel (coupure réseau en
 * cours d'envoi) ne bloque donc pas les positions déjà passées.
 *
 * Tolère les positions hors séquence : chaque position est historisée telle
 * qu'elle arrive, mais la « dernière position » dénormalisée sur la session
 * (donc ce que voit fleet/tracking) n'avance que si la séquence reçue est
 * plus récente que celle déjà enregistrée — un paquet en retard ne fait
 * jamais régresser l'état affiché.
 *
 * Un horodatage impossible (trop dans le futur ou trop ancien — seuils
 * `trackingMax*` de OperationsSettings) est rejeté individuellement ; le
 * reste du lot est traité normalement.
 */
class IngestTrackingPositionsBatchAction
{
    public function __construct(
        private TrackingPositionCache $cache,
        private FleetTrackingQuery $fleetQuery,
    ) {}

    /**
     * @param  array<int, array{position_uuid: string, sequence: int, recorded_at: string, latitude: ?float, longitude: ?float, accuracy_meters: ?float, speed_kmh: ?float, heading_degrees: ?float, source: string, status: string}>  $positions
     * @return array{accepted: int, duplicate: int, rejected: int, rejected_details: list<array{uuid: string, reason: string}>}
     */
    public function execute(TrackingSession $session, TerminalP501 $terminal, User $agent, array $positions): array
    {
        // Protection anti-usurpation, en plus de la garde ops.session : cette
        // action n'écrit jamais pour un terminal ou un convoyeur qui ne sont
        // pas ceux de LA session résolue par le contrôleur.
        if ($session->terminal_p501_id !== $terminal->id || $session->conductor_id !== $agent->id) {
            throw TrackingException::notAssigned();
        }

        $tally = ['accepted' => 0, 'duplicate' => 0, 'rejected' => 0];
        $rejectedDetails = [];
        $advanced = false;

        foreach ($positions as $item) {
            $outcome = DB::transaction(fn () => $this->processOne($session, $item));

            $tally[$outcome['status']]++;

            if ($outcome['status'] === 'rejected') {
                $rejectedDetails[] = ['uuid' => $item['position_uuid'], 'reason' => $outcome['reason']];
            } elseif ($outcome['status'] === 'accepted' && $outcome['advanced']) {
                $advanced = true;
            }
        }

        $session->refresh();
        $this->cache->put($session);

        activity()
            ->performedOn($session)
            ->causedBy($agent)
            ->withProperties(['tally' => $tally, 'serial_number' => $terminal->serial_number])
            ->log('tracking_batch_ingested');

        if ($advanced) {
            $this->broadcast($session);
        }

        return [
            'accepted' => $tally['accepted'],
            'duplicate' => $tally['duplicate'],
            'rejected' => $tally['rejected'],
            'rejected_details' => $rejectedDetails,
        ];
    }

    /**
     * @param  array{position_uuid: string, sequence: int, recorded_at: string, latitude: ?float, longitude: ?float, accuracy_meters: ?float, speed_kmh: ?float, heading_degrees: ?float, source: string, status: string}  $item
     * @return array{status: 'duplicate'}|array{status: 'rejected', reason: string}|array{status: 'accepted', advanced: bool}
     */
    private function processOne(TrackingSession $session, array $item): array
    {
        $existing = TrackingPosition::query()->where('uuid', $item['position_uuid'])->first();

        if ($existing !== null) {
            return ['status' => 'duplicate'];
        }

        $recordedAt = Carbon::parse($item['recorded_at']);
        $rejection = $this->rejectionReason($recordedAt);

        if ($rejection !== null) {
            return ['status' => 'rejected', 'reason' => $rejection];
        }

        $positionStatus = TrackingPositionStatus::from($item['status']);
        $accuracy = $item['accuracy_meters'] ?? null;
        $quality = $accuracy !== null && $accuracy > (float) app(OperationsSettings::class)->trackingDegradedAccuracyMeters
            ? TrackingPositionQuality::Degraded
            : TrackingPositionQuality::Accurate;

        $position = TrackingPosition::query()->create([
            'tracking_session_id' => $session->id,
            'uuid' => $item['position_uuid'],
            'sequence' => $item['sequence'],
            'status' => $positionStatus,
            'latitude' => $positionStatus === TrackingPositionStatus::Fix ? $item['latitude'] : null,
            'longitude' => $positionStatus === TrackingPositionStatus::Fix ? $item['longitude'] : null,
            'accuracy_meters' => $accuracy,
            'speed_kmh' => $item['speed_kmh'] ?? null,
            'heading_degrees' => $item['heading_degrees'] ?? null,
            'source' => $item['source'],
            'quality' => $quality,
            'recorded_at' => $recordedAt,
            'server_received_at' => now(),
        ]);

        // Tolérance à l'ordre : une séquence plus ancienne que la dernière
        // déjà connue ne fait pas régresser l'état affiché sur fleet/tracking.
        $advanced = $session->last_sequence === null || $item['sequence'] > $session->last_sequence;

        if ($advanced) {
            $update = [
                'last_position_id' => $position->id,
                'last_position_at' => $recordedAt,
                'last_accuracy_meters' => $accuracy,
                'last_speed_kmh' => $item['speed_kmh'] ?? null,
                'last_heading_degrees' => $item['heading_degrees'] ?? null,
                'last_quality' => $quality,
                'last_status' => $positionStatus,
                'last_sequence' => $item['sequence'],
            ];

            // Un heartbeat "GPS indisponible" met à jour le statut affiché
            // mais ne doit jamais effacer la dernière position réelle connue.
            if ($positionStatus === TrackingPositionStatus::Fix) {
                $update['last_latitude'] = $item['latitude'];
                $update['last_longitude'] = $item['longitude'];
            }

            $session->forceFill($update)->save();
        }

        return ['status' => 'accepted', 'advanced' => $advanced];
    }

    private function rejectionReason(Carbon $recordedAt): ?string
    {
        $skew = app(OperationsSettings::class)->trackingMaxClockSkewSeconds;

        if ($recordedAt->gt(now()->addSeconds($skew))) {
            return 'timestamp_in_future';
        }

        $maxAge = app(OperationsSettings::class)->trackingMaxPositionAgeMinutes;

        if ($recordedAt->lt(now()->subMinutes($maxAge))) {
            return 'timestamp_too_old';
        }

        return null;
    }

    private function broadcast(TrackingSession $session): void
    {
        $session->loadMissing([
            'departure.line.stops' => fn ($stops) => $stops->where('status', StopStatus::Active->value)->orderBy('stop_order'),
            'departure.conductor',
            'departure.driver',
            'departure.secondaryDriver',
            'departure.mechanic',
            'departure.incidents.creator',
            'bus.busModel',
            'terminal.agency',
            'conductor',
            'lastPosition',
        ]);

        // Même charge utile que la page et l'API (FleetTrackingQuery::present)
        // : un client abonné au canal voit exactement ce que voit un client
        // qui interroge fleet/tracking.
        $payload = $this->fleetQuery->present(collect([$session]))->first();

        Event::dispatch(new FleetPositionUpdated($session->terminal->agency?->uuid, $payload ?? []));
    }
}
