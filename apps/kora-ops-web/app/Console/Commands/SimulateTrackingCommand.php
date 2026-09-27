<?php

namespace App\Console\Commands;

use App\Modules\Departures\Models\Departure;
use App\Modules\IdentityAccess\Data\TerminalP501Status;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\IdentityAccess\Models\TerminalSession;
use App\Modules\Tracking\Actions\IngestTrackingPositionsBatchAction;
use App\Modules\Tracking\Actions\OpenTrackingSessionAction;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SimulateTrackingCommand extends Command
{
    protected $signature = 'tracking:simulate
        {departure : UUID du départ en circulation}
        {--count=60 : nombre de points générés le long de l\'itinéraire}
        {--interval=8 : secondes simulées entre deux positions}
        {--watch : diffuser les positions en continu jusqu\'à interruption}
        {--force : autoriser l\'exécution en production}';

    protected $description = 'Injecte des positions GPS simulées dans le vrai pipeline d\'ingestion tracking (session, dénormalisation, broadcast)';

    public function handle(
        OpenTrackingSessionAction $open,
        IngestTrackingPositionsBatchAction $ingest,
    ): int {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Refusé en production. Rejouez avec --force si c\'est vraiment voulu.');

            return self::FAILURE;
        }

        $departure = Departure::query()->where('uuid', $this->argument('departure'))->first();

        if ($departure === null || $departure->conductor === null) {
            $this->error('Départ introuvable ou sans convoyeur affecté.');

            return self::FAILURE;
        }

        $terminal = TerminalP501::query()
            ->where('assigned_user_id', $departure->conductor_id)
            ->where('status', TerminalP501Status::Active)
            ->first();

        if ($terminal === null) {
            $this->error('Aucun terminal actif assigné au convoyeur '.$departure->conductor->name.'.');

            return self::FAILURE;
        }

        $session = $open->execute($departure, $terminal);

        if ($session === null) {
            $this->error('Impossible d\'ouvrir la session de suivi.');

            return self::FAILURE;
        }

        // Le resolver d'état lit l'activité du terminal : une session OPS
        // simulée garde le bus « en ligne » pendant la démo.
        $terminalSession = TerminalSession::query()
            ->where('terminal_p501_id', $terminal->id)
            ->active()
            ->latest('started_at')
            ->first() ?? TerminalSession::query()->create([
                'terminal_p501_id' => $terminal->id,
                'user_id' => $departure->conductor_id,
                'started_at' => now(),
                'expires_at' => now()->addHours(8),
            ]);
        $terminalSession->forceFill(['last_seen_at' => now()])->save();

        $path = $this->path($departure, (int) $this->option('count'));
        $sequence = ($session->last_sequence ?? 0) + 1;
        $interval = (int) $this->option('interval');

        if (! $this->option('watch')) {
            $positions = [];

            foreach ($path as $index => [$lat, $lng, $heading]) {
                $positions[] = $this->position($sequence + $index, $lat, $lng, $heading, now()->subSeconds((count($path) - 1 - $index) * $interval));
            }

            $result = $ingest->execute($session->refresh(), $terminal, $departure->conductor, $positions);

            $this->info("Session {$session->uuid} : {$result['accepted']} acceptées, {$result['duplicate']} doublons, {$result['rejected']} rejetées.");

            return self::SUCCESS;
        }

        $this->info("Diffusion en continu (Ctrl+C pour arrêter) — session {$session->uuid}.");
        $index = 0;

        while (true) { // @phpstan-ignore while.alwaysTrue
            [$lat, $lng, $heading] = $path[$index];
            $session->refresh();

            $result = $ingest->execute(
                $session,
                $terminal,
                $departure->conductor,
                [$this->position($sequence++, $lat, $lng, $heading, now())],
            );

            $terminalSession->forceFill(['last_seen_at' => now()])->save();
            $this->line(sprintf('#%d → %.5f, %.5f (%d acceptée(s))', $sequence - 1, $lat, $lng, $result['accepted']));

            // Itinéraire parcouru : on repart en sens inverse pour garder
            // la carte vivante (ping-pong entre les extrémités).
            if (++$index >= count($path)) {
                $path = array_reverse($path);
                $index = 0;
            }

            sleep($interval);
        }
    }

    /**
     * Points interpolés le long des arrêts géolocalisés de la ligne ;
     * repli sur une boucle autour de Cotonou si l'itinéraire n'en a pas.
     *
     * @return array<int, array{0: float, 1: float, 2: float}> lat, lng, cap
     */
    private function path(Departure $departure, int $count): array
    {
        $waypoints = $departure->line->stops
            ->filter(fn ($stop): bool => $stop->latitude !== null && $stop->longitude !== null)
            ->map(fn ($stop): array => [(float) $stop->latitude, (float) $stop->longitude])
            ->values()
            ->all();

        if (count($waypoints) < 2) {
            $waypoints = [[6.3654, 2.4183], [6.3703, 2.4280], [6.3882, 2.4554]];
        }

        $points = [];
        $perSegment = max(1, intdiv($count, count($waypoints) - 1));

        for ($i = 0; $i < count($waypoints) - 1; $i++) {
            [$latA, $lngA] = $waypoints[$i];
            [$latB, $lngB] = $waypoints[$i + 1];
            $heading = $this->heading($latA, $lngA, $latB, $lngB);

            for ($j = 0; $j < $perSegment; $j++) {
                $t = $j / $perSegment;
                $points[] = [$latA + ($latB - $latA) * $t, $lngA + ($lngB - $lngA) * $t, $heading];
            }
        }

        $points[] = [...$waypoints[count($waypoints) - 1], $points[count($points) - 1][2] ?? 0];

        return $points;
    }

    private function heading(float $latA, float $lngA, float $latB, float $lngB): float
    {
        return (rad2deg(atan2($lngB - $lngA, $latB - $latA)) + 360) % 360;
    }

    /**
     * @return array{position_uuid: string, sequence: int, recorded_at: string, latitude: float, longitude: float, accuracy_meters: float, speed_kmh: float, heading_degrees: float, source: string, status: string}
     */
    private function position(int $sequence, float $lat, float $lng, float $heading, CarbonInterface $recordedAt): array
    {
        return [
            'position_uuid' => (string) Str::uuid(),
            'sequence' => $sequence,
            'recorded_at' => $recordedAt->toISOString(),
            'latitude' => round($lat, 6),
            'longitude' => round($lng, 6),
            'accuracy_meters' => round(mt_rand(40, 120) / 10, 1),
            'speed_kmh' => round(mt_rand(350, 850) / 10, 1),
            'heading_degrees' => round($heading, 1),
            'source' => 'gps',
            'status' => 'fix',
        ];
    }
}
