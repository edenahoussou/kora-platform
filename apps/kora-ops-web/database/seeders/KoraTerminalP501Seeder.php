<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\IdentityAccess\Data\TerminalP501Status;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\IdentityAccess\Models\TerminalAssignment;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\IdentityAccess\Models\TerminalSession;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Peuple les tables liées aux terminaux KORA OPS Mobile (P501) :
 * `terminals_p501`, `terminal_assignments`, `terminal_sessions`.
 *
 * Dépend de KoraDemoUserSeeder (personnel terrain) et
 * KoraBusReferenceSeeder (agence CTN).
 *
 * - 15 terminaux nominatifs, un par membre du personnel terrain démo
 *   (6 chauffeurs, 6 convoyeurs, 2 agents d'agence, 1 manager d'agence).
 * - 2 terminaux hors dotation nominative : un terminal de stock (jamais
 *   affecté) et un terminal déclaré perdu (historique d'affectation puis
 *   déclaration de perte), pour couvrir ces statuts dans les écrans admin.
 * - Historique de réaffectation sur 3 terminaux (ancien titulaire ->
 *   titulaire actuel) pour peupler `terminal_assignments` avec plusieurs
 *   lignes par terminal.
 * - Sessions (`terminal_sessions`) simulant des cycles handshake/logout,
 *   avec une session active pour les terminaux actifs.
 */
class KoraTerminalP501Seeder extends Seeder
{
    private ?User $opsDirector = null;

    public function run(): void
    {
        $this->opsDirector = User::query()->where('email', 'ops.director@kora.demo')->first();

        if ($this->opsDirector === null) {
            throw new RuntimeException(
                'KoraTerminalP501Seeder requiert KoraDemoUserSeeder (ops.director@kora.demo introuvable).',
            );
        }

        $agency = Agency::query()->where('code', 'CTN')->first();

        if ($agency === null) {
            throw new RuntimeException(
                'KoraTerminalP501Seeder requiert KoraBusReferenceSeeder (agence CTN introuvable).',
            );
        }

        $terminals = $this->seedNominativeTerminals($agency);
        $this->seedReassignmentHistory($terminals);
        $this->seedStockAndLostTerminals($agency);
        $this->seedSessions($terminals);
    }

    /**
     * 15 terminaux, un par membre du personnel terrain démo.
     * Statuts : 12 actifs, 1 en maintenance, 1 inactif, 1 assigné mais
     * jamais synchronisé (terminal tout juste remis en main propre).
     *
     * @return array<string, TerminalP501> indexé par email du titulaire
     */
    private function seedNominativeTerminals(Agency $agency): array
    {
        // email => statut (défaut : Active si non précisé ci-dessous)
        $statusOverrides = [
            'conductor.eric@kora.demo' => TerminalP501Status::Maintenance,
            'driver.herve@kora.demo' => TerminalP501Status::Inactive,
        ];

        // Emails créés par KoraDemoUserSeeder::seedFieldStaff(), dans le
        // même ordre (6 chauffeurs, 6 convoyeurs, 2 agents, 1 manager).
        $fieldStaffEmails = [
            'driver.jb@kora.demo',
            'driver.paulin@kora.demo',
            'driver.koffi@kora.demo',
            'driver.mensah@kora.demo',
            'driver.basil@kora.demo',
            'driver.herve@kora.demo',
            'conductor.marc@kora.demo',
            'conductor.luc@kora.demo',
            'conductor.aime@kora.demo',
            'conductor.david@kora.demo',
            'conductor.serge@kora.demo',
            'conductor.eric@kora.demo',
            'agent.sandrine@kora.demo',
            'agent.brice@kora.demo',
            'manager.adjoua@kora.demo',
        ];

        // Les agents/manager d'agence sont rattachés à l'agence CTN ; le
        // personnel roulant (chauffeur/convoyeur) n'est pas lié à une
        // agence fixe.
        $agencyBoundEmails = [
            'agent.sandrine@kora.demo',
            'agent.brice@kora.demo',
            'manager.adjoua@kora.demo',
        ];

        $terminals = [];

        foreach ($fieldStaffEmails as $index => $email) {
            $user = User::query()->where('email', $email)->first();

            if ($user === null) {
                throw new RuntimeException("KoraTerminalP501Seeder : utilisateur introuvable ({$email}).");
            }

            $status = $statusOverrides[$email] ?? TerminalP501Status::Active;
            $serialNumber = sprintf('P501-BJ-%04d', $index + 1);

            $isUsable = $status === TerminalP501Status::Active;

            $terminal = TerminalP501::query()->firstOrCreate(
                ['serial_number' => $serialNumber],
                [
                    'uuid' => (string) Str::uuid(),
                    'assigned_user_id' => $user->id,
                    'agency_id' => in_array($email, $agencyBoundEmails, true) ? $agency->id : null,
                    'status' => $status,
                    'last_sync_at' => $isUsable ? now()->subHours(random_int(1, 48)) : null,
                    'last_handshake_at' => $isUsable ? now()->subHours(random_int(1, 12)) : now()->subDays(random_int(3, 20)),
                ],
            );

            $terminals[$email] = $terminal;

            TerminalAssignment::query()->firstOrCreate(
                ['terminal_p501_id' => $terminal->id, 'unassigned_at' => null],
                [
                    'uuid' => (string) Str::uuid(),
                    'assigned_user_id' => $user->id,
                    'agency_id' => $terminal->agency_id,
                    'assigned_by' => $this->opsDirector->id,
                    'assigned_at' => now()->subDays(random_int(30, 180)),
                    'reason' => 'Dotation initiale',
                ],
            );
        }

        return $terminals;
    }

    /**
     * Historique de réaffectation sur 3 terminaux : un précédent titulaire
     * (ligne close, `unassigned_at` renseigné) avant le titulaire actuel
     * déjà créé dans seedNominativeTerminals().
     *
     * @param  array<string, TerminalP501>  $terminals
     */
    private function seedReassignmentHistory(array $terminals): void
    {
        $reassignments = [
            // terminal actuel => [ancien titulaire, motif du retrait]
            [
                'terminal_email' => 'conductor.marc@kora.demo',
                'previous_user_email' => 'conductor.david@kora.demo',
                'reason' => 'Mutation vers la ligne L3',
            ],
            [
                'terminal_email' => 'driver.paulin@kora.demo',
                'previous_user_email' => 'driver.mensah@kora.demo',
                'reason' => 'Terminal remplacé (écran défectueux)',
            ],
            [
                'terminal_email' => 'agent.brice@kora.demo',
                'previous_user_email' => 'agent.sandrine@kora.demo',
                'reason' => 'Réaffectation suite à ouverture de poste',
            ],
        ];

        foreach ($reassignments as $data) {
            $terminal = $terminals[$data['terminal_email']];
            $previousUser = User::query()->where('email', $data['previous_user_email'])->first();

            if ($previousUser === null) {
                continue;
            }

            $assignedAt = now()->subDays(random_int(200, 300));
            $unassignedAt = $assignedAt->copy()->addDays(random_int(30, 90));

            TerminalAssignment::query()->firstOrCreate(
                [
                    'terminal_p501_id' => $terminal->id,
                    'assigned_user_id' => $previousUser->id,
                ],
                [
                    'uuid' => (string) Str::uuid(),
                    'agency_id' => $terminal->agency_id,
                    'assigned_by' => $this->opsDirector->id,
                    'assigned_at' => $assignedAt,
                    'unassigned_at' => $unassignedAt,
                    'reason' => $data['reason'],
                ],
            );
        }
    }

    /**
     * Terminal de stock (jamais affecté) + terminal déclaré perdu
     * (affecté par le passé, puis retiré suite à déclaration de perte).
     */
    private function seedStockAndLostTerminals(Agency $agency): void
    {
        TerminalP501::query()->firstOrCreate(
            ['serial_number' => 'P501-BJ-0016'],
            [
                'uuid' => (string) Str::uuid(),
                'assigned_user_id' => null,
                'agency_id' => $agency->id,
                'status' => TerminalP501Status::Inactive,
                'last_sync_at' => null,
                'last_handshake_at' => null,
            ],
        );

        $lostTerminal = TerminalP501::query()->firstOrCreate(
            ['serial_number' => 'P501-BJ-0017'],
            [
                'uuid' => (string) Str::uuid(),
                'assigned_user_id' => null,
                'agency_id' => null,
                'status' => TerminalP501Status::Lost,
                'last_sync_at' => now()->subDays(45),
                'last_handshake_at' => now()->subDays(45),
            ],
        );

        $previousHolder = User::query()->where('email', 'conductor.aime@kora.demo')->first();

        if ($previousHolder !== null) {
            TerminalAssignment::query()->firstOrCreate(
                ['terminal_p501_id' => $lostTerminal->id, 'assigned_user_id' => $previousHolder->id],
                [
                    'uuid' => (string) Str::uuid(),
                    'agency_id' => null,
                    'assigned_by' => $this->opsDirector->id,
                    'assigned_at' => now()->subDays(120),
                    'unassigned_at' => now()->subDays(45),
                    'reason' => 'Terminal déclaré perdu',
                ],
            );
        }
    }

    /**
     * Sessions handshake/logout par terminal. Les terminaux actifs ont une
     * session en cours (`revoked_at` null) ; les autres n'ont que de
     * l'historique révoqué.
     *
     * @param  array<string, TerminalP501>  $terminals
     */
    private function seedSessions(array $terminals): void
    {
        $revokedReasons = ['logout', 'token_expired', 'handshake_renewed'];

        foreach ($terminals as $email => $terminal) {
            $user = User::query()->where('email', $email)->first();

            if ($user === null) {
                continue;
            }

            $isActive = $terminal->status === TerminalP501Status::Active;
            $sessionCount = random_int(2, 3);

            for ($i = 0; $i < $sessionCount; $i++) {
                $isLastSession = $i === $sessionCount - 1;
                $startedAt = now()->subDays(($sessionCount - $i) * random_int(2, 6));

                $isCurrentlyActive = $isActive && $isLastSession;

                TerminalSession::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'terminal_p501_id' => $terminal->id,
                    'user_id' => $user->id,
                    'personal_access_token_id' => null,
                    'started_at' => $startedAt,
                    'last_seen_at' => $isCurrentlyActive
                        ? now()->subMinutes(random_int(1, 90))
                        : $startedAt->copy()->addHours(random_int(1, 10)),
                    'revoked_at' => $isCurrentlyActive ? null : $startedAt->copy()->addHours(random_int(1, 10)),
                    'revoked_reason' => $isCurrentlyActive ? null : $revokedReasons[array_rand($revokedReasons)],
                ]);
            }
        }

        // Le terminal perdu garde une trace de sa dernière session, close
        // au moment de la déclaration de perte.
        $lostTerminal = TerminalP501::query()->where('serial_number', 'P501-BJ-0017')->first();
        $previousHolder = User::query()->where('email', 'conductor.aime@kora.demo')->first();

        if ($lostTerminal !== null && $previousHolder !== null) {
            TerminalSession::query()->create([
                'uuid' => (string) Str::uuid(),
                'terminal_p501_id' => $lostTerminal->id,
                'user_id' => $previousHolder->id,
                'personal_access_token_id' => null,
                'started_at' => now()->subDays(46),
                'last_seen_at' => now()->subDays(45),
                'revoked_at' => now()->subDays(45),
                'revoked_reason' => 'terminal_lost',
            ]);
        }
    }
}
