<?php

namespace App\Modules\Notifications\Actions;

use App\Models\User;

class BuildSupportChatVisitorAction
{
    /**
     * Construit l'identité visiteur transmise au dashboard tawk.to
     * (web Inertia et apps mobiles via la configuration de chat).
     *
     * @return array{name: string|null, email: string|null, phone: string|null, jobTitle: string|null, agency: string|null, role: string|null}
     */
    public function execute(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $this->e164Phone($user),
            'jobTitle' => $user->job_title,
            'agency' => $user->agency?->name,
            'role' => $user->roles->pluck('label')->filter()->implode(', ') ?: null,
        ];
    }

    /**
     * Normalise le numéro au format E.164 attendu par tawk.to.
     */
    private function e164Phone(User $user): ?string
    {
        if (! $user->phone) {
            return null;
        }

        $digits = preg_replace('/[^\d+]/', '', $user->phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (! str_starts_with($digits, '+') && $user->country_code) {
            $digits = '+'.ltrim($user->country_code, '+').ltrim($digits, '0');
        }

        return $digits;
    }
}
