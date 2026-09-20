<?php

namespace App\Modules\Notifications\Services;

use App\Models\User;
use App\Modules\Departures\Models\Departure;
use App\Modules\IdentityAccess\Data\SystemRole;
use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\Notifications\Data\OpsNotificationChannel;
use App\Modules\Notifications\Data\OpsNotificationDraft;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Détermine qui reçoit quoi. Le périmètre est appliqué ici, à la
 * publication : un convoyeur n'est destinataire que des événements de SES
 * départs et de SON terminal ; les directeurs des opérations que de ceux de
 * leur agence ; les super-administrateurs que des alertes.
 */
class OpsNotificationRecipientResolver
{
    public function __construct(private OpsNotificationPreferences $preferences) {}

    /**
     * Équipage du départ (chauffeur, second chauffeur, convoyeur, mécanicien).
     *
     * @return Collection<int, User>
     */
    public function crewOf(Departure $departure): Collection
    {
        $ids = array_values(array_unique(array_filter([
            $departure->driver_id,
            $departure->secondary_driver_id,
            $departure->conductor_id,
            $departure->mechanic_id,
        ])));

        if ($ids === []) {
            return new Collection;
        }

        return User::query()->whereIn('id', $ids)->get();
    }

    /**
     * Directeurs des opérations de l'agence du convoyeur à l'origine de l'alerte.
     *
     * @return Collection<int, User>
     */
    public function operationsDirectorsOf(?User $subject): Collection
    {
        $agencyId = $subject?->agency_id;

        if ($agencyId === null) {
            return new Collection;
        }

        return User::query()
            ->whereHas('roles', fn (Builder $roles) => $roles->where('name', SystemRole::OperationsDirector->value))
            ->where('status', UserStatus::Active->value)
            ->where('agency_id', $agencyId)
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    public function superAdmins(): Collection
    {
        return User::query()
            ->whereHas('roles', fn (Builder $roles) => $roles->where('name', SystemRole::SystemAdmin->value))
            ->where('status', UserStatus::Active->value)
            ->get();
    }

    /**
     * Destinataires de la notification (canal in_app) : les concernés, les
     * directeurs des opérations de l'agence du convoyeur et, pour les
     * alertes, les super-administrateurs — dédoublonnés, actifs, autorisés.
     *
     * @return Collection<int, User>
     */
    public function inAppRecipients(OpsNotificationDraft $draft): Collection
    {
        $recipients = $draft->primaryRecipients;

        if ($draft->type->notifiesOperationsDirectors()) {
            $recipients = $recipients->merge($this->operationsDirectorsOf($draft->subject));
        }

        if ($draft->type->isAlert()) {
            $recipients = $recipients->merge($this->superAdmins());
        }

        return $this->eligible($recipients);
    }

    /**
     * Destinataires de l'e-mail d'alerte : les directeurs des opérations de
     * l'agence du convoyeur (destinataires), les super-administrateurs (en
     * copie). Sans directeur d'agence connu, les super-administrateurs
     * deviennent destinataires. Les préférences e-mail sont respectées
     * (sauf pour une alerte critique).
     *
     * @return array{to: Collection<int, User>, cc: Collection<int, User>}
     */
    public function mailRecipients(OpsNotificationDraft $draft): array
    {
        $directors = $this->eligibleForMail($this->operationsDirectorsOf($draft->subject), $draft);
        $superAdmins = $this->eligibleForMail($this->superAdmins(), $draft);

        if ($directors->isEmpty()) {
            return ['to' => $superAdmins, 'cc' => new Collection];
        }

        return [
            'to' => $directors,
            'cc' => $superAdmins->reject(fn (User $admin): bool => $directors->contains('id', $admin->id))->values(),
        ];
    }

    /**
     * @param  Collection<int, User>  $users
     * @return Collection<int, User>
     */
    private function eligible(Collection $users): Collection
    {
        return $users
            ->unique('id')
            ->filter(fn (User $user): bool => $this->canReceive($user))
            ->values();
    }

    /**
     * @param  Collection<int, User>  $users
     * @return Collection<int, User>
     */
    private function eligibleForMail(Collection $users, OpsNotificationDraft $draft): Collection
    {
        return $this->eligible($users)
            ->filter(fn (User $user): bool => $user->email !== '')
            ->filter(fn (User $user): bool => $this->preferences->allows($user, $draft->type, OpsNotificationChannel::Mail))
            ->values();
    }

    public function canReceive(User $user): bool
    {
        return $user->status === UserStatus::Active && $user->can('notifications.view');
    }
}
