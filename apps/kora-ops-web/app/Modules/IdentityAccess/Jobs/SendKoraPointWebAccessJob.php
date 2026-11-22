<?php

namespace App\Modules\IdentityAccess\Jobs;

use App\Models\User;
use App\Modules\IdentityAccess\Data\SystemRole;
use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\IdentityAccess\Models\KoraPointWebAccessInvitation;
use App\Modules\IdentityAccess\Services\KoraPointWebAccessDelivery;
use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\KoraPoints\Models\KoraPointOrganization;
use App\Notifications\IdentityAccess\KoraPointWebAccessNotification;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use SensitiveParameter;
use Throwable;

class SendKoraPointWebAccessJob implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 3600;

    /** @var array<int, int> */
    public array $backoff = [15, 90, 300];

    public function __construct(
        public readonly string $invitationUuid,
        #[SensitiveParameter] public readonly string $temporaryPassword,
    ) {}

    public function uniqueId(): string
    {
        return $this->invitationUuid;
    }

    public function handle(KoraPointWebAccessDelivery $delivery): void
    {
        $invitation = KoraPointWebAccessInvitation::query()
            ->with(['user.roles', 'user.agency'])
            ->where('uuid', $this->invitationUuid)
            ->first();

        if (
            $invitation === null
            || $invitation->status !== KoraPointWebAccessInvitation::STATUS_PENDING_DELIVERY
            || $invitation->user === null
            || $invitation->user->status !== UserStatus::Active
            || ! $invitation->user->must_change_password
            || ! Hash::check($this->temporaryPassword, $invitation->user->password)
        ) {
            return;
        }

        $user = $invitation->user;
        $notification = new KoraPointWebAccessNotification(
            name: $user->name,
            role: $this->roleLabel($user),
            pointOrOrganization: $this->pointOrOrganization($user),
            email: $user->email,
            temporaryPassword: $this->temporaryPassword,
            loginUrl: route('login'),
        );

        try {
            $delivery->sendEmail($user->email, $notification);
        } catch (Throwable $exception) {
            Log::warning('KORA POINT web access could not be delivered.', [
                'user_uuid' => $user->uuid,
                'invitation_uuid' => $invitation->uuid,
                'email_exception' => $exception::class,
            ]);

            throw new RuntimeException('La livraison de l’accès web KORA POINT a échoué.');
        }

        DB::transaction(function (): void {
            $currentInvitation = KoraPointWebAccessInvitation::query()
                ->lockForUpdate()
                ->where('uuid', $this->invitationUuid)
                ->first();

            if (
                $currentInvitation === null
                || $currentInvitation->status !== KoraPointWebAccessInvitation::STATUS_PENDING_DELIVERY
            ) {
                return;
            }

            $currentUser = User::query()->lockForUpdate()->find($currentInvitation->user_id);

            if ($currentUser === null || ! Hash::check($this->temporaryPassword, $currentUser->password)) {
                return;
            }

            $sentAt = now();
            $currentInvitation->update([
                'status' => KoraPointWebAccessInvitation::STATUS_SENT,
                'delivery_channel' => 'email',
                'sent_at' => $sentAt,
                'expires_at' => $sentAt->copy()->addHours(72),
            ]);
        });
    }

    public function failed(): void
    {
        KoraPointWebAccessInvitation::query()
            ->where('uuid', $this->invitationUuid)
            ->where('status', KoraPointWebAccessInvitation::STATUS_PENDING_DELIVERY)
            ->update(['status' => KoraPointWebAccessInvitation::STATUS_FAILED]);
    }

    private function roleLabel(User $user): string
    {
        $roleName = (string) $user->roles->first()?->name;

        return SystemRole::tryFrom($roleName)?->translatedLabel() ?? 'Utilisateur KORA POINT';
    }

    private function pointOrOrganization(User $user): string
    {
        if ($user->agency_id !== null) {
            $point = KoraPoint::query()->with(['agency', 'organization'])->where('agency_id', $user->agency_id)->first();

            if ($point !== null) {
                $label = 'Point : '.$point->agency->name;

                if ($point->agency?->code) {
                    $label .= ' ('.$point->agency->code.')';
                }

                if ($point->organization !== null) {
                    $label .= ' · Organisation : '.$point->organization->name;
                }

                return $label;
            }

            if ($user->agency !== null) {
                return 'Point : '.$user->agency->name.' ('.$user->agency->code.')';
            }
        }

        if ($user->kora_point_organization_id !== null) {
            $organization = KoraPointOrganization::query()->find($user->kora_point_organization_id);

            if ($organization !== null) {
                return 'Organisation : '.$organization->name;
            }
        }

        return 'Réseau KORA POINT · affectation à finaliser';
    }
}
