<?php

namespace App\Modules\IdentityAccess\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IdentityAccess\Http\Requests\CompleteRegistrationRequest;
use App\Modules\IdentityAccess\Http\Requests\LoginRequest;
use App\Modules\IdentityAccess\Http\Requests\ResetPinRequest;
use App\Modules\IdentityAccess\Http\Resources\AuthUserResource;
use App\Modules\IdentityAccess\Http\Resources\UserDeviceResource;
use App\Modules\IdentityAccess\Services\AuthSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthSessionController extends Controller
{
    public function __construct(private AuthSessionService $authSessionService) {}

    public function completeRegistration(CompleteRegistrationRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->sessionPayload(
            $this->authSessionService->completeRegistration($request->payload()),
        )], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authSessionService->login($request->payload(), $request);

        if (($result['requires_otp'] ?? false) && isset($result['challenge'])) {
            return response()->json([
                'data' => [
                    'requires_otp' => true,
                    'otp_challenge_uuid' => $result['challenge']->uuid,
                    'purpose' => $result['challenge']->purpose->value,
                    'expires_at' => $result['challenge']->expires_at->toISOString(),
                ],
            ], 202);
        }

        return response()->json(['data' => $this->sessionPayload($result)]);
    }

    public function resetPin(ResetPinRequest $request): JsonResponse
    {
        $user = $this->authSessionService->resetPin($request->payload());

        return response()->json(['data' => ['user' => new AuthUserResource($user)]]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => ['user' => new AuthUserResource($request->user())]]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(status: 204);
    }

    /**
     * @param  array<string, mixed>  $session
     * @return array<string, mixed>
     */
    private function sessionPayload(array $session): array
    {
        return [
            'token' => $session['token'],
            'token_type' => 'Bearer',
            'expires_at' => $session['token_expires_at']?->toISOString(),
            'user' => new AuthUserResource($session['user']),
            'device' => new UserDeviceResource($session['device']),
        ];
    }
}
