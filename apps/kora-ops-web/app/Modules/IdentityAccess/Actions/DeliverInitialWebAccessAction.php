<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Jobs\SendAccountWelcomeJob;
use App\Modules\IdentityAccess\Models\KoraPointWebAccessInvitation;
use SensitiveParameter;

class DeliverInitialWebAccessAction
{
    public function __construct(private readonly IssueKoraPointWebAccessAction $issueKoraPointWebAccess) {}

    public function execute(User $user, User $actor, #[SensitiveParameter] string $password): ?KoraPointWebAccessInvitation
    {
        if ($user->isKoraPointUser()) {
            return $this->issueKoraPointWebAccess->execute($user, $actor, $password);
        }

        SendAccountWelcomeJob::dispatch($user->uuid, $password, $user->email)->afterCommit();

        return null;
    }
}
