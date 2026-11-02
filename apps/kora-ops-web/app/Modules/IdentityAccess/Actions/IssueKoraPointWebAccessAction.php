<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Jobs\SendKoraPointWebAccessJob;
use App\Modules\IdentityAccess\Models\KoraPointWebAccessInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IssueKoraPointWebAccessAction
{
    public function __construct(private readonly RevokeUserAccessAction $revokeAccess) {}

    public function execute(User $user, User $actor, ?string $temporaryPassword = null): KoraPointWebAccessInvitation
    {
        $temporaryPassword ??= Str::password(32);

        return DB::transaction(function () use ($user, $actor, $temporaryPassword): KoraPointWebAccessInvitation {
            $lockedUser = User::query()->lockForUpdate()->whereKey($user->getKey())->firstOrFail();

            KoraPointWebAccessInvitation::query()
                ->where('user_id', $lockedUser->getKey())
                ->whereIn('status', [
                    KoraPointWebAccessInvitation::STATUS_PENDING_DELIVERY,
                    KoraPointWebAccessInvitation::STATUS_SENT,
                ])
                ->update(['status' => KoraPointWebAccessInvitation::STATUS_REPLACED]);

            $lockedUser->forceFill([
                'password' => $temporaryPassword,
                'must_change_password' => true,
                'password_changed_at' => null,
            ])->save();

            $this->revokeAccess->execute($lockedUser, $actor);

            $invitation = KoraPointWebAccessInvitation::query()->create([
                'user_id' => $lockedUser->getKey(),
                'status' => KoraPointWebAccessInvitation::STATUS_PENDING_DELIVERY,
            ]);

            SendKoraPointWebAccessJob::dispatch($invitation->uuid, $temporaryPassword)->afterCommit();

            activity('identity-access')
                ->performedOn($lockedUser)
                ->causedBy($actor)
                ->withProperties([
                    'role' => $lockedUser->roles()->value('name'),
                    'invitation_uuid' => $invitation->uuid,
                ])
                ->log('kora_point_web_access_issued');

            return $invitation;
        }, 3);
    }
}
