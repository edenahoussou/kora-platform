<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AuthorizesPermissions;

class UserPolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'users.view');
    }

    public function view(User $user, User $target): bool
    {
        return $this->allows($user, 'users.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'users.manage');
    }

    public function update(User $user, User $target): bool
    {
        return $this->allows($user, 'users.manage') && $user->getKey() !== $target->getKey();
    }

    public function delete(User $user, User $target): bool
    {
        return $this->allows($user, 'users.manage') && $user->getKey() !== $target->getKey();
    }

    public function restore(User $user, User $target): bool
    {
        return $this->allows($user, 'users.manage') && $user->getKey() !== $target->getKey();
    }

    public function resetPassword(User $user, User $target): bool
    {
        return $this->allows($user, 'users.manage') && $user->getKey() !== $target->getKey();
    }

    public function resetPin(User $user, User $target): bool
    {
        return $this->allows($user, 'users.manage') && $user->getKey() !== $target->getKey();
    }

    public function unlock(User $user, User $target): bool
    {
        return $this->allows($user, 'users.manage') && $user->getKey() !== $target->getKey();
    }
}
