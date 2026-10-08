<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ConfirmSensitiveActionRequest;
use App\Modules\IdentityAccess\Actions\RevokeOpsSessionAction;
use App\Modules\IdentityAccess\Actions\RevokeOtherWebSessionsAction;
use App\Modules\IdentityAccess\Actions\RevokeWebSessionAction;
use App\Modules\IdentityAccess\Data\OpsSessionRevocationReason;
use App\Modules\IdentityAccess\Data\SecurityAlertEvent;
use App\Modules\IdentityAccess\Models\TerminalSession;
use App\Modules\IdentityAccess\Models\UserDevice;
use App\Modules\IdentityAccess\Services\UserDeviceService;
use App\Modules\IdentityAccess\Services\UserSessionService;
use App\Notifications\IdentityAccess\SecurityAlertNotification;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProfileSessionController extends Controller
{
    public function __construct(
        private readonly UserSessionService $sessions,
        private readonly UserDeviceService $devices,
        private readonly RevokeWebSessionAction $revokeWebSession,
        private readonly RevokeOtherWebSessionsAction $revokeOtherWebSessions,
        private readonly RevokeOpsSessionAction $revokeOpsSession,
    ) {}

    /**
     * Déconnecte une session de navigateur (identifiant hashé exposé au frontend).
     */
    public function destroySession(ConfirmSensitiveActionRequest $request, string $session): RedirectResponse
    {
        $user = $request->user();
        $sessionId = $this->sessions->resolveSessionId($user, $session);

        if ($sessionId === null) {
            throw new NotFoundHttpException;
        }

        $this->revokeWebSession->execute($user, $sessionId, $request->session()->getId());

        $this->alert($request, SecurityAlertEvent::WebSessionRevoked);

        return $this->done('Session déconnectée.');
    }

    /**
     * Déconnecte toutes les autres sessions de navigateur du compte.
     */
    public function destroyOthers(ConfirmSensitiveActionRequest $request): RedirectResponse
    {
        $this->revokeOtherWebSessions->execute(
            $request->user(),
            $request->string('password')->toString(),
            $request->session()->getId(),
        );

        $this->alert($request, SecurityAlertEvent::OtherSessionsRevoked);

        return $this->done('Toutes les autres sessions ont été déconnectées.');
    }

    /**
     * Retire un appareil de confiance (résolu par UUID, propriété vérifiée).
     */
    public function destroyDevice(ConfirmSensitiveActionRequest $request, string $device): RedirectResponse
    {
        $userDevice = UserDevice::query()
            ->where('uuid', $device)
            ->firstOrFail();

        $this->devices->revokeForUser($request->user(), $userDevice);

        $this->alert($request, SecurityAlertEvent::DeviceRevoked);

        return $this->done('Appareil retiré.');
    }

    /**
     * Révoque une session terminal P501 active (résolue par UUID).
     */
    public function destroyTerminal(ConfirmSensitiveActionRequest $request, string $terminal): RedirectResponse
    {
        $terminalSession = TerminalSession::query()
            ->where('uuid', $terminal)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $this->revokeOpsSession->execute(
            $terminalSession,
            OpsSessionRevocationReason::Logout,
            $request->user(),
        );

        $this->alert($request, SecurityAlertEvent::TerminalSessionRevoked);

        return $this->done('Session terminal révoquée.');
    }

    private function alert(ConfirmSensitiveActionRequest $request, SecurityAlertEvent $event): void
    {
        $request->user()->notify(new SecurityAlertNotification($event, [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]));
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __($message)]);

        return back();
    }
}
