<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Actions\BuildSupportChatConfigurationAction;
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
     * @response 200 scenario="Configuration disponible" {"data":{"enabled":true,"property_id":"abc123def456","widget_id":"default","direct_chat_url":"https://tawk.to/chat/abc123def456/default","secure_hash":"hmac-sha256","visitor":{"name":"Awa Kora","email":"awa@kora.test","phone":"+22997000000","jobTitle":null,"agency":null,"role":"Passager"}}}
     * @response 200 scenario="Configuration indisponible" {"data":{"enabled":false}}
     * @response 401 scenario="Non authentifié" {"message":"Unauthenticated."}
     */
    public function show(Request $request, BuildSupportChatConfigurationAction $configuration): JsonResponse
    {
        return response()->json(['data' => $configuration->execute($request->user())]);
    }
}
