<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Affecte agent.brice@kora.demo (rôle AgencyAgent) comme convoyeur
 * (`conductor_id`) sur quelques départs à venir, uniquement pour permettre
 * de tester de bout en bout le login + handshake terminal P501 + écran
 * "tournées/manifeste" avec ce compte depuis l'app mobile.
 *
 * ATTENTION — écart volontaire au modèle métier réel :
 * `CrewDepartureScope` (OPS Mobile) ne montre des départs qu'aux
 * utilisateurs qui sont personnellement driver_id/secondary_driver_id/
 * conductor_id/mechanic_id d'un départ. Un agent d'agence n'est
 * normalement jamais convoyeur — ce seeder force cette affectation
 * uniquement à des fins de test avec ce compte précis. À ne pas
 * reproduire pour d'autres agents en environnement de démo "propre".
 *
 * Dépend de KoraDemoUserSeeder (brice) et KoraDepartureProgrammingSeeder
 * (départs publiés). Cible en priorité les créneaux L2/L3 laissés sans
 * convoyeur par KoraDepartureProgrammingSeeder pour ne pas écraser une
 * affectation existante.
 *
 * Il n'accorde PAS la permission `boarding.scan` (réservée au rôle
 * Conductor) : brice verra les tournées et le manifeste, mais un scan
 * d'embarquement avec ce compte restera refusé (403) tant que son rôle
 * reste AgencyAgent.
 */
class KoraTerminalP501DemoCrewSeeder extends Seeder
{
    private const int TARGET_DEPARTURE_COUNT = 5;

    public function run(): void
    {
        $brice = User::query()->where('email', 'agent.brice@kora.demo')->first();

        if ($brice === null) {
            throw new RuntimeException(
                'KoraTerminalP501DemoCrewSeeder requiert KoraDemoUserSeeder (agent.brice@kora.demo introuvable).',
            );
        }

        // Priorité aux créneaux sans convoyeur (L2 retour 18:00, L3 aller/retour
        // — voir KoraDepartureProgrammingSeeder) pour ne rien écraser.
        $departures = Departure::query()
            ->whereNull('conductor_id')
            ->where('status', '!=', DepartureStatus::Cancelled)
            ->whereDate('departure_date', '>=', now()->toDateString())
            ->orderBy('departure_date')
            ->orderBy('departure_time')
            ->limit(self::TARGET_DEPARTURE_COUNT)
            ->get();

        if ($departures->count() < self::TARGET_DEPARTURE_COUNT) {
            // Pas assez de créneaux "libres" à venir : complète avec les
            // prochains départs, quitte à remplacer leur convoyeur existant.
            $missing = self::TARGET_DEPARTURE_COUNT - $departures->count();

            $fallback = Departure::query()
                ->whereNotIn('id', $departures->pluck('id'))
                ->where('status', '!=', DepartureStatus::Cancelled)
                ->whereDate('departure_date', '>=', now()->toDateString())
                ->orderBy('departure_date')
                ->orderBy('departure_time')
                ->limit($missing)
                ->get();

            $departures = $departures->concat($fallback);
        }

        if ($departures->isEmpty()) {
            //  CORRECTION : Retrait du ? car $this->command est non-nullable
            $this->command->warn(
                'KoraTerminalP501DemoCrewSeeder : aucun départ à venir trouvé — as-tu bien lancé KoraDepartureProgrammingSeeder avant ?',
            );

            return;
        }

        foreach ($departures as $departure) {
            $departure->forceFill(['conductor_id' => $brice->id])->save();
        }

        //  CORRECTION : Retrait du ? car $this->command est non-nullable
        $this->command->info(
            "KoraTerminalP501DemoCrewSeeder : {$departures->count()} départ(s) affecté(s) à agent.brice@kora.demo pour test terminal/manifeste.",
        );
    }
}
