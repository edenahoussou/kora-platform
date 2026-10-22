<?php

namespace App\Modules\IdentityAccess\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\IdentityAccess\Models\UserDevice;
use App\Modules\IdentityAccess\Models\UserPreference;
use App\Modules\Notifications\Services\FirebaseMessagingClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @group Auth
 *
 * Push token management for authenticated passenger devices.
 */
class AuthPushTokenController extends Controller
{
    /**
     * Register or remove the FCM token for one of the authenticated user's devices.
     *
     * Push must be enabled in the user's preferences and Firebase must be configured
     * before a token can be registered.
     *
     * @authenticated
     *
     * @urlParam device_uuid string required UUID of the device returned by the auth session. Example: a3091549-9179-454e-8440-58179f2208df
     *
     * @bodyParam enabled boolean required Whether push is enabled for this device. Send false to remove its token. Example: true
     * @bodyParam push_token string FCM registration token for this app installation. Required only when enabled is true; maximum 4096 characters. Example: fcm-registration-token
     *
     * @response 200 scenario="Token registered" {"data":{"enabled":true}}
     * @response 200 scenario="Token removed" {"data":{"enabled":false}}
     * @response 401 scenario="Unauthenticated" {"message":"Unauthenticated."}
     * @response 404 scenario="Device does not belong to the authenticated user or is inactive" {"message":"Not found."}
     * @response 422 scenario="Push preference is disabled, Firebase is not configured, or input is invalid" {"message":"The given data was invalid.","errors":{"enabled":["Activez les notifications push dans vos préférences avant d’inscrire cet appareil."]}}
     */
    public function update(Request $request, UserDevice $device, FirebaseMessagingClient $firebase): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($device->user_id === $user->getKey() && $device->status->value === 'active', 404);
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'push_token' => ['required_if:enabled,true', 'nullable', 'string', 'max:4096'],
        ]);

        if ($validated['enabled'] && ! $this->pushAllowed($user)) {
            throw ValidationException::withMessages(['enabled' => 'Activez les notifications push dans vos préférences avant d’inscrire cet appareil.']);
        }
        if ($validated['enabled'] && ! $firebase->isServerConfigured()) {
            throw ValidationException::withMessages(['enabled' => 'Les notifications push ne sont pas disponibles actuellement.']);
        }

        $device->forceFill(['push_token' => $validated['enabled'] ? $validated['push_token'] : null])->save();

        return response()->json(['data' => ['enabled' => $device->push_token !== null]]);
    }

    /**
     * Sans préférence enregistrée, un compte OPS reçoit le push par défaut ;
     * un passager doit l'avoir activé explicitement.
     */
    private function pushAllowed(User $user): bool
    {
        return $user->preference?->push_notifications ?? UserPreference::defaultPushNotificationsFor($user);
    }
}
