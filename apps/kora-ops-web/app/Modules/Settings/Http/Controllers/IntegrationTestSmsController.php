<?php

namespace App\Modules\Settings\Http\Controllers;

use App\Models\User;
use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\Notifications\Services\NotificationLogRecorder;
use App\Modules\Notifications\Services\Sms\ConexteoSmsClient;
use App\Modules\Settings\Data\SettingsGroup;
use App\Modules\Settings\Http\Requests\SendTestSmsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Throwable;

use function activity;
use function Illuminate\Support\enum_value;

final class IntegrationTestSmsController
{
    public function __invoke(
        SendTestSmsRequest $request,
        ConexteoSmsClient $client,
        NotificationLogRecorder $recorder,
    ): RedirectResponse {
        $actor = $request->user();

        abort_unless($actor instanceof User && enum_value($actor->status) === UserStatus::Active->value, 403);

        Gate::forUser($actor)->authorize(SettingsGroup::Integrations->managePermission());

        if (! $client->isConfigured()) {
            return $this->flash('error', 'Conexteo n’est pas activé ou ses identifiants sont incomplets.');
        }

        $phone = $request->phone();

        try {
            $result = $client->send($phone, 'KORA BUS : ceci est un SMS de test. Aucune action requise.');
            $recorder->recordSms($phone, 'test_sms', 'sent', payload: [
                'message_id' => $result['message_id'],
                'credits_used' => $result['credits_used'],
                'duplicate' => $result['duplicate'],
            ]);
            $status = 'sent';
        } catch (Throwable $exception) {
            $recorder->recordSms($phone, 'test_sms', 'failed', payload: ['error' => $exception->getMessage()]);
            $status = 'failed';
        }

        activity('settings')
            ->causedBy($actor)
            ->event('settings.test_sms')
            ->withProperties(['recipient' => $phone, 'status' => $status])
            ->log('settings.test_sms');

        return $status === 'sent'
            ? $this->flash('success', 'SMS de test envoyé à '.$phone.'.')
            : $this->flash('error', 'L’envoi du SMS de test a échoué.');
    }

    private function flash(string $type, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return back();
    }
}
