<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IdentityAccess\Data\DevicePlatform;
use App\Modules\IdentityAccess\Data\UserDeviceStatus;
use App\Modules\IdentityAccess\Models\UserDevice;
use App\Modules\Notifications\Services\FirebaseMessagingClient;
use App\Modules\Settings\Settings\IntegrationsSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OpsWebPushPreferenceController extends Controller
{
    public function configuration(IntegrationsSettings $settings, FirebaseMessagingClient $firebase): JsonResponse
    {
        $configured = $firebase->isServerConfigured()
            && filled($settings->firebaseWebApiKey)
            && filled($settings->firebaseWebMessagingSenderId)
            && filled($settings->firebaseWebAppId)
            && filled($settings->firebaseWebVapidKey);

        return response()->json([
            'configured' => $configured,
            'firebase' => $configured ? [
                'apiKey' => $settings->firebaseWebApiKey,
                'authDomain' => $settings->firebaseWebAuthDomain,
                'projectId' => $settings->firebaseProjectId,
                'storageBucket' => $settings->firebaseWebStorageBucket,
                'messagingSenderId' => $settings->firebaseWebMessagingSenderId,
                'appId' => $settings->firebaseWebAppId,
                'vapidKey' => $settings->firebaseWebVapidKey,
            ] : null,
        ]);
    }

    public function update(Request $request, FirebaseMessagingClient $firebase): JsonResponse
    {
        $data = $request->validate([
            'device_fingerprint' => ['required', 'uuid'],
            'device_name' => ['required', 'string', 'max:120'],
            'push_token' => ['nullable', 'string', 'max:4096'],
        ]);

        if ($data['push_token'] !== null && ! $firebase->isServerConfigured()) {
            throw ValidationException::withMessages(['push_token' => 'Les notifications push ne sont pas configurées sur ce serveur.']);
        }

        $device = UserDevice::query()->updateOrCreate(
            [
                'user_id' => $request->user()->getKey(),
                'device_fingerprint_hash' => hash('sha256', $data['device_fingerprint']),
            ],
            [
                'name' => $data['device_name'],
                'platform' => DevicePlatform::Web,
                'push_token' => $data['push_token'],
                'status' => UserDeviceStatus::Active,
                'trusted_at' => now(),
                'last_seen_at' => now(),
                'revoked_at' => null,
            ],
        );

        return response()->json(['data' => ['enabled' => $device->push_token !== null]]);
    }
}
