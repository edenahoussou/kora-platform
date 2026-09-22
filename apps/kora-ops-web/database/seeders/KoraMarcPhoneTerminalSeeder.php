<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\IdentityAccess\Actions\AssignTerminalAction;
use App\Modules\IdentityAccess\Data\SystemRole;
use App\Modules\IdentityAccess\Data\TerminalP501Status;
use App\Modules\IdentityAccess\Data\TerminalType;
use App\Modules\IdentityAccess\Models\TerminalP501;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Affecte le téléphone de test au convoyeur Marc (mode « téléphone » de KORA
 * OPS Mobile : PDF A4 du manifeste, scan caméra).
 *
 * Lancer (ré-exécutable sans doublon) :
 *
 *   php artisan db:seed --class=KoraMarcPhoneTerminalSeeder
 *
 * Prérequis : KoraDemoUserSeeder (convoyeur `conductor.marc@kora.demo`, ou
 * `border_demo.conductor_email` si défini) et KoraBusRolePermissionSeeder.
 *
 * Numéro de série du téléphone : le serveur le stocke en MAJUSCULES, au
 * format `TEL-ANDROID-<empreinte de l'appareil>`. Deux façons de l'indiquer :
 *
 * 1. FULL_SERIAL ci-dessous porte le numéro complet (le plus fiable) — c'est le cas ;
 * 2. sinon, le seeder cherche le terminal déjà enregistré (l'app l'enregistre
 *    au premier lancement) dont le numéro commence par SERIAL_PREFIX et finit
 *    par SERIAL_SUFFIX. Une seule correspondance est acceptée : zéro ou
 *    plusieurs, le seeder s'arrête en listant ce qu'il a trouvé.
 *
 * Ce que fait le seeder :
 * - crée le terminal (type `phone`, actif) s'il n'existe pas encore ;
 * - le remet en service (`active`) s'il était inactif, perdu ou en maintenance ;
 * - l'affecte à Marc via AssignTerminalAction : historique d'affectation
 *   tenu à jour et, si le téléphone appartenait à quelqu'un d'autre, ses
 *   sessions OPS sont révoquées (motif « terminal réaffecté »).
 *
 * À savoir : une seule session OPS active par convoyeur. Se connecter sur le
 * téléphone ferme donc la session éventuelle de Marc sur son P501.
 */
class KoraMarcPhoneTerminalSeeder extends Seeder
{
    /** Numéro complet du téléphone de Marc. Laisser vide pour rechercher par début/fin (SERIAL_PREFIX / SERIAL_SUFFIX). */
    private const string FULL_SERIAL = 'TEL-ANDROID-AP3A240905015A2';

    private const string SERIAL_PREFIX = 'TEL-ANDROID-AP3';

    private const string SERIAL_SUFFIX = '5A2';

    public function run(AssignTerminalAction $assign): void
    {
        $this->withModelEvents(fn () => $this->assignPhone($assign));
    }

    private function assignPhone(AssignTerminalAction $assign): void
    {
        $marc = $this->resolveConductor();
        $serial = $this->resolveSerial();

        $terminal = TerminalP501::query()->where('serial_number', $serial)->first();
        $created = $terminal === null;

        $terminal ??= TerminalP501::query()->create([
            'uuid' => (string) Str::uuid(),
            'serial_number' => $serial,
            'type' => TerminalType::Phone,
            'status' => TerminalP501Status::Active,
            'agency_id' => $marc->agency_id,
            'last_handshake_at' => now(),
        ]);

        if ($terminal->type !== TerminalType::Phone || $terminal->status !== TerminalP501Status::Active) {
            $terminal->forceFill([
                'type' => TerminalType::Phone,
                'status' => TerminalP501Status::Active,
            ])->save();
        }

        $alreadyAssigned = $terminal->assigned_user_id === $marc->id;

        if (! $alreadyAssigned) {
            $assign->execute($terminal, [
                'assigned_user_id' => $marc->id,
                'agency_id' => $marc->agency_id,
                'reason' => 'Seeder de démonstration : téléphone de test de Marc',
            ], $this->actor($marc));
        }

        // ✅ CORRECTION : Retrait de l'opérateur nullsafe (?->) car $this->command est non-nullable
        $this->command->info(sprintf(
            'KoraMarcPhoneTerminalSeeder : %s %s — affecté à %s (%s).',
            $terminal->serial_number,
            $created ? 'créé' : 'existant',
            $marc->email,
            $alreadyAssigned ? 'déjà affecté' : 'affectation enregistrée',
        ));
    }

    /**
     * `DatabaseSeeder` utilise `WithoutModelEvents` : les événements Eloquent sont coupés pendant tout le
     * seeding, donc le hook de KoraModel qui génère le `uuid` ne s'exécute pas (SQLSTATE 23502 : `uuid`
     * NULL). Ce seeder rétablit le dispatcher le temps de son exécution, puis remet l'état d'origine.
     */
    private function withModelEvents(callable $callback): void
    {
        $previous = Model::getEventDispatcher();
        Model::setEventDispatcher(app('events'));

        try {
            $callback();
        } finally {
            if ($previous === null) {
                Model::unsetEventDispatcher();
            } else {
                Model::setEventDispatcher($previous);
            }
        }
    }

    private function resolveConductor(): User
    {
        $email = (string) config('border_demo.conductor_email', 'conductor.marc@kora.demo');
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            throw new RuntimeException("Convoyeur {$email} introuvable : lancez d'abord KoraDemoUserSeeder.");
        }

        return $user;
    }

    private function resolveSerial(): string
    {
        $full = strtoupper(trim(self::FULL_SERIAL));

        if ($full !== '') {
            return $full;
        }

        $matches = TerminalP501::query()
            ->where('serial_number', 'like', self::SERIAL_PREFIX.'%'.self::SERIAL_SUFFIX)
            ->orderBy('id')
            ->pluck('serial_number');

        if ($matches->count() === 1) {
            return (string) $matches->first();
        }

        if ($matches->isEmpty()) {
            throw new RuntimeException(sprintf(
                "Aucun terminal « %s…%s » en base. Lancez l'app sur le téléphone une fois (enregistrement automatique), "
                    .'ou complétez FULL_SERIAL dans ce seeder avec le numéro complet.',
                self::SERIAL_PREFIX,
                self::SERIAL_SUFFIX,
            ));
        }

        throw new RuntimeException(
            'Plusieurs terminaux correspondent : '.$matches->implode(', ').'. Complétez FULL_SERIAL dans ce seeder.',
        );
    }

    /** Auteur de l'affectation : un super-administrateur si présent, sinon Marc lui-même. */
    private function actor(User $fallback): User
    {
        return User::query()
            ->whereHas('roles', fn ($roles) => $roles->where('name', SystemRole::SystemAdmin->value))
            ->orderBy('id')
            ->first() ?? $fallback;
    }
}
