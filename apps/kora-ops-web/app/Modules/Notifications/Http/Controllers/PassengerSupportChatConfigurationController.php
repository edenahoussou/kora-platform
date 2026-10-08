<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Settings\IntegrationsSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Passager — Notifications
 *
 * Expose la configuration publique tawk.to nécessaire au chat support.
 */
class PassengerSupportChatConfigurationController extends Controller
{
    /**
     * Retourne la configuration tawk.to publique pour l'app mobile.
     *
     * @authenticated
     *
     * @response 200 scenario="Configuration disponible" {"data":{"enabled":true,"property_id":"abc123def456","widget_id":"default","direct_chat_url":"https://tawk.to/chat/abc123def456/default","secure_hash":"hmac-sha256"}}
     * @response 200 scenario="Configuration indisponible" {"data":{"enabled":false}}
     * @response 401 scenario="Non authentifié" {"message":"Unauthenticated."}
     */
    public function show(Request $request, IntegrationsSettings $settings): JsonResponse
    {
        if (! $settings->tawktoEnabled || blank($settings->tawktoPropertyId) || blank($settings->tawktoWidgetId)) {
            return response()->json(['data' => ['enabled' => false]]);
        }

        $email = $request->user()?->email;

        return response()->json(['data' => [
            'enabled' => true,
            'property_id' => $settings->tawktoPropertyId,
            'widget_id' => $settings->tawktoWidgetId,
            'direct_chat_url' => "https://tawk.to/chat/{$settings->tawktoPropertyId}/{$settings->tawktoWidgetId}",
            // Mode sécurisé tawk.to : HMAC de l'email du passager, la clé
            // API ne quitte jamais le serveur.
            'secure_hash' => $email && $settings->tawktoApiKey
                ? hash_hmac('sha256', $email, $settings->tawktoApiKey)
                : null,
        ]]);
    }
}
