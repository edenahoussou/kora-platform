<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use Illuminate\Support\Str;

class ResetUserPasswordAction
{
    public function __construct(
        private readonly RevokeUserAccessAction $revokeAccess,
    ) {}

    public function execute(User $user, User $actor): string
    {
        $temporaryPassword = Str::password(32);

        $user->forceFill([
            'password' => $temporaryPassword,
            'must_change_password' => true,
        ])->save();

        $this->revokeAccess->execute($user, $actor);

        activity('identity-access')
            ->performedOn($user)
            ->causedBy($actor)
            ->log('user_password_reset');

        return $temporaryPassword;
    }
}
