<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Services\FirebaseMessagingClient;
use App\Modules\Settings\Settings\IntegrationsSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PassengerFirebaseConfigurationController extends Controller
{
    public function show(Request $request, IntegrationsSettings $settings, FirebaseMessagingClient $firebase): JsonResponse
    {
        $platform = $request->validate(['platform' => ['required', 'in:android,ios']])['platform'];
        $appId = $platform === 'android' ? $settings->firebaseAndroidAppId : $settings->firebaseIosAppId;
        if (! $firebase->isServerConfigured() || blank($settings->firebaseWebApiKey) || blank($settings->firebaseWebMessagingSenderId) || blank($appId)) {
            return response()->json(['data' => ['enabled' => false]]);
        }

        return response()->json(['data' => [
            'enabled' => true,
            'api_key' => $settings->firebaseWebApiKey,
            'project_id' => $settings->firebaseProjectId,
            'messaging_sender_id' => $settings->firebaseWebMessagingSenderId,
            'app_id' => $appId,
        ]]);
    }
}
