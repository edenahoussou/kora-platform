<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Services\FirebaseMessagingClient;
use App\Modules\Settings\Settings\IntegrationsSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Passager — Notifications
 *
 * Expose uniquement la configuration Firebase cliente nécessaire à FCM.
 */
class PassengerFirebaseConfigurationController extends Controller
{
    /**
     * Retourne la configuration Firebase publique correspondant à la plateforme.
     *
     * Cette route est publique : le client mobile doit pouvoir initialiser FCM
     * avant la connexion. Elle ne renvoie que des identifiants client déjà
     * présents en clair dans l'APK, jamais de compte de service ou de secret serveur.
     *
     * @unauthenticated
     *
     * @queryParam platform string required Plateforme cliente : android ou ios. Example: android
     *
     * @response 200 scenario="Configuration disponible" {"data":{"enabled":true,"api_key":"AIzaSyExamplePublicKey","project_id":"kora-bus-prod","messaging_sender_id":"123456789012","app_id":"1:123456789012:android:abcdef123456"}}
     * @response 200 scenario="Configuration indisponible" {"data":{"enabled":false}}
     * @response 422 scenario="Plateforme invalide" {"message":"The given data was invalid.","errors":{"platform":["The selected platform is invalid."]}}
     * @response 429 scenario="Limite de débit dépassée" {"message":"Too Many Requests"}
     */
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
