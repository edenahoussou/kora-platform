<?php

namespace App\Modules\KoraPoints\Services;

use App\Models\User;
use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\KoraPoints\Models\KoraPointOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class KoraPointPartnerSessionGuard
{
    public function blockedMessage(User $user): ?string
    {
        if (! $user->hasAnyRole(['kora_point_supervisor', 'kora_point_treasurer'])) {
            return null;
        }

        if ($user->status !== UserStatus::Active) {
            return 'Votre compte partenaire n’est pas actif. Votre session a été fermée. Contactez l’administrateur KORA.';
        }

        $organization = $user->koraPointOrganization()->first();

        if (! $organization instanceof KoraPointOrganization) {
            return $this->unrecognizedSpaceMessage();
        }

        return match ($organization->status) {
            'active' => null,
            'suspended' => 'L’accès à votre organisation est suspendu. Votre session a été fermée. Contactez l’administrateur KORA.',
            'closed' => 'L’espace de votre organisation est clôturé. Votre session a été fermée. Contactez l’administrateur KORA.',
            default => $this->unrecognizedSpaceMessage(),
        };
    }

    public function terminate(Request $request, string $message): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        return redirect()->route('login');
    }

    public function unrecognizedSpaceMessage(): string
    {
        return 'Votre espace partenaire n’est pas reconnu. Votre session a été fermée. Contactez l’administrateur KORA.';
    }
}
