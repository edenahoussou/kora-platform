<?php

namespace App\Modules\IdentityAccess\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IdentityAccess\Exceptions\AuthSuspendedException;
use App\Modules\IdentityAccess\Http\Requests\OAuthTokenRequest;
use App\Modules\IdentityAccess\Http\Resources\AuthUserResource;
use App\Modules\IdentityAccess\Http\Resources\UserDeviceResource;
use App\Modules\IdentityAccess\Services\AuthSocialService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * @group Auth
 *
 * Authentification sociale OAuth 2.0 — permet aux passagers de se
 * connecter ou créer un compte via Google, Facebook ou TikTok. Le
 * client mobile obtient un jeton via le SDK natif du provider, puis
 * l'échange contre un token Sanctum KORA.
 */
class AuthOAuthController extends Controller
{
    public function __construct(private AuthSocialService $authSocialService) {}

    /**
     * Échange un jeton OAuth provider contre une session KORA.
     *
     * Le client mobile authentifie l'utilisateur via le SDK natif du
     * provider (Google Sign-In, Facebook SDK, TikTok Login Kit), récupère
     * un access token, puis l'envoie à cet endpoint. Le backend valide
     * le jeton via Socialite, retrouve ou crée le compte KORA, lie le
     * compte social, télécharge l'avatar via Spatie Media Library et
     * émet un token Sanctum.
     *
     * @unauthenticated
     *
     * @response 200 {
     *   "data": {
     *     "token": "3|aBcDeFgHiJkLmNoPqRsTuVwXyZ0123456789abcdef",
     *     "token_type": "Bearer",
     *     "expires_at": "2026-08-27T13:05:00.000Z",
     *     "user": {
     *       "uuid": "977e5426-8d13-3824-86aa-b092f8ae52c5",
     *       "name": "Aïcha Hounkpè",
     *       "email": "aicha.hounkpe@gmail.com",
     *       "phone": null,
     *       "country_code": null,
     *       "birth_date": null,
     *       "avatar_url": "https://api.korabus.app/media/avatars/123/thumb.jpg",
     *       "status": "active",
     *       "permissions": ["bookings.create", "bookings.view-own", "tickets.view"]
     *     },
     *     "device": {
     *       "uuid": "a3091549-9179-454e-8440-58179f2208df",
     *       "name": "iPhone 15 Pro",
     *       "platform": "ios",
     *       "trusted_at": "2026-08-20T13:05:00.000Z",
     *       "last_seen_at": "2026-08-20T13:05:00.000Z",
     *       "status": "trusted"
     *     }
     *   }
     * }
     * @response 201 {
     *   "data": {
     *     "token": "1|aBcDeFgHiJkLmNoPqRsTuVwXyZ0123456789abcdef",
     *     "token_type": "Bearer",
     *     "expires_at": "2026-08-27T13:05:00.000Z",
     *     "user": {
     *       "uuid": "977e5426-8d13-3824-86aa-b092f8ae52c5",
     *       "name": "Aïcha Hounkpè",
     *       "email": "aicha.hounkpe@gmail.com",
     *       "phone": null,
     *       "country_code": null,
     *       "birth_date": null,
     *       "avatar_url": "https://api.korabus.app/media/avatars/124/thumb.jpg",
     *       "status": "active",
     *       "permissions": ["bookings.create", "bookings.view-own", "tickets.view"]
     *     },
     *     "device": {
     *       "uuid": "a3091549-9179-454e-8440-58179f2208df",
     *       "name": "iPhone 15 Pro",
     *       "platform": "ios",
     *       "trusted_at": "2026-08-20T13:05:00.000Z",
     *       "last_seen_at": "2026-08-20T13:05:00.000Z",
     *       "status": "trusted"
     *     }
     *   }
     * }
     * @response 422 {"error": {"code": "invalid_oauth_token", "message": "Le jeton OAuth fourni n'est pas valide ou a expiré."}}
     * @response 403 {"error": {"code": "account_suspended", "message": "Le compte est suspendu. Contactez le support."}}
     */
    public function token(OAuthTokenRequest $request): JsonResponse
    {
        try {
            $result = $this->authSocialService->loginWithOAuth($request->payload());
        } catch (AuthSuspendedException $e) {
            return response()->json([
                'error' => [
                    'code' => 'account_suspended',
                    'message' => 'Le compte est suspendu. Contactez le support.',
                ],
            ], 403);
        } catch (Throwable $e) {
            return response()->json([
                'error' => [
                    'code' => 'invalid_oauth_token',
                    'message' => "Le jeton OAuth fourni n'est pas valide ou a expiré.",
                ],
            ], 422);
        }

        return response()->json([
            'data' => [
                'token' => $result['token'],
                'token_type' => 'Bearer',
                'expires_at' => $result['token_expires_at']?->toISOString(),
                'user' => new AuthUserResource($result['user']),
                'device' => new UserDeviceResource($result['device']),
            ],
        ], $result['created'] ? 201 : 200);
    }
}
