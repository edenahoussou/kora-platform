<?php

namespace App\Modules\IdentityAccess\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\IdentityAccess\Actions\RevokeOpsSessionAction;
use App\Modules\IdentityAccess\Data\OpsSessionRevocationReason;
use App\Modules\IdentityAccess\Exceptions\AuthLockedException;
use App\Modules\IdentityAccess\Exceptions\AuthSuspendedException;
use App\Modules\IdentityAccess\Http\Requests\ChangePinRequest;
use App\Modules\IdentityAccess\Http\Requests\CompleteRegistrationRequest;
use App\Modules\IdentityAccess\Http\Requests\LoginRequest;
use App\Modules\IdentityAccess\Http\Requests\ResetPinRequest;
use App\Modules\IdentityAccess\Http\Requests\UnlockAccountRequest;
use App\Modules\IdentityAccess\Http\Requests\UpdateProfileRequest;
use App\Modules\IdentityAccess\Http\Resources\AuthUserResource;
use App\Modules\IdentityAccess\Http\Resources\UserDeviceResource;
use App\Modules\IdentityAccess\Models\TerminalSession;
use App\Modules\IdentityAccess\Services\AuthSessionService;
use App\Modules\IdentityAccess\Services\OpsSessionTokenReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @group Auth
 *
 * Authentication session management: registration, login, PIN, profile and logout.
 */
class AuthSessionController extends Controller
{
    public function __construct(private AuthSessionService $authSessionService) {}

    /**
     * Complete a registration started via OTP verification.
     *
     * Creates the user account from a verified OTP challenge and returns a Sanctum session token.
     *
     * @unauthenticated
     *
     * @response 201 scenario="Registration completed" {
     *   "data": {
     *     "token": "3|aBcDeFgHiJkLmNoPqRsTuVwXyZ0123456789abcdef",
     *     "token_type": "Bearer",
     *     "expires_at": "2026-08-27T13:05:00.000Z",
     *     "user": {
     *       "uuid": "977e5426-8d13-3824-86aa-b092f8ae52c5",
     *       "name": "Aïcha Hounkpè",
     *       "email": "aicha.hounkpe@gmail.com",
     *       "phone": "+22901000000",
     *       "country_code": "BJ",
     *       "birth_date": "1990-05-15",
     *       "avatar_url": null,
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
     * @response 422 scenario="Validation error" {
     *   "message": "The given data was invalid.",
     *   "errors": {"verification_token": ["The verification token is invalid or expired."]}
     * }
     */
    public function completeRegistration(CompleteRegistrationRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->sessionPayload(
            $this->authSessionService->completeRegistration($request->payload()),
        )], 201);
    }

    /**
     * Authenticate a user with email/phone and PIN.
     *
     * Returns a Sanctum session token. If OTP is required, returns a 202 with an OTP challenge
     * instead of a token.
     *
     * @unauthenticated
     *
     * @response 200 scenario="Login successful" {
     *   "data": {
     *     "token": "3|aBcDeFgHiJkLmNoPqRsTuVwXyZ0123456789abcdef",
     *     "token_type": "Bearer",
     *     "expires_at": "2026-08-27T13:05:00.000Z",
     *     "user": {
     *       "uuid": "977e5426-8d13-3824-86aa-b092f8ae52c5",
     *       "name": "Aïcha Hounkpè",
     *       "email": "aicha.hounkpe@gmail.com",
     *       "phone": "+22901000000",
     *       "country_code": "BJ",
     *       "birth_date": "1990-05-15",
     *       "avatar_url": null,
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
     * @response 202 scenario="OTP required" {
     *   "data": {
     *     "requires_otp": true,
     *     "otp_challenge_uuid": "b1234567-89ab-cdef-0123-456789abcdef",
     *     "purpose": "login",
     *     "expires_at": "2026-08-20T13:10:00.000Z"
     *   }
     * }
     * @response 422 scenario="Invalid credentials" {
     *   "error": {
     *     "code": "invalid_credentials",
     *     "message": "Identifiants invalides."
     *   }
     * }
     * @response 423 scenario="Account locked" {
     *   "error": {
     *     "code": "account_locked",
     *     "message": "Le compte est temporairement bloqué après plusieurs tentatives incorrectes.",
     *     "locked_until": "2026-08-20T13:15:00.000Z"
     *   }
     * }
     * @response 403 scenario="Account suspended" {
     *   "error": {
     *     "code": "account_suspended",
     *     "message": "Le compte est suspendu. Contactez le support."
     *   }
     * }
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authSessionService->login($request->payload(), $request);
        } catch (AuthLockedException $e) {
            return response()->json([
                'error' => [
                    'code' => 'account_locked',
                    'message' => 'Le compte est temporairement bloqué après plusieurs tentatives incorrectes.',
                    'locked_until' => $e->lockedUntil?->toISOString(),
                ],
            ], 423);
        } catch (AuthSuspendedException $e) {
            return response()->json([
                'error' => [
                    'code' => 'account_suspended',
                    'message' => 'Le compte est suspendu. Contactez le support.',
                ],
            ], 403);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => [
                    'code' => 'invalid_credentials',
                    'message' => 'Identifiants invalides.',
                ],
            ], 422);
        }

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

    /**
     * Reset the user's PIN after OTP verification.
     *
     * @unauthenticated
     *
     * @response 200 scenario="PIN reset" {
     *   "data": {
     *     "user": {
     *       "uuid": "977e5426-8d13-3824-86aa-b092f8ae52c5",
     *       "name": "Aïcha Hounkpè",
     *       "email": "aicha.hounkpe@gmail.com",
     *       "phone": "+22901000000",
     *       "country_code": "BJ",
     *       "birth_date": "1990-05-15",
     *       "avatar_url": null,
     *       "status": "active",
     *       "permissions": ["bookings.create", "bookings.view-own", "tickets.view"]
     *     }
     *   }
     * }
     * @response 422 scenario="Validation error" {
     *   "message": "The given data was invalid.",
     *   "errors": {"verification_token": ["The verification token is invalid or expired."]}
     * }
     */
    public function resetPin(ResetPinRequest $request): JsonResponse
    {
        $user = $this->authSessionService->resetPin($request->payload());

        return response()->json(['data' => ['user' => new AuthUserResource($user)]]);
    }

    /**
     * Unlock a locked account after OTP verification.
     *
     * @unauthenticated
     *
     * @response 200 scenario="Account unlocked" {
     *   "data": {
     *     "user": {
     *       "uuid": "977e5426-8d13-3824-86aa-b092f8ae52c5",
     *       "name": "Aïcha Hounkpè",
     *       "email": "aicha.hounkpe@gmail.com",
     *       "phone": "+22901000000",
     *       "country_code": "BJ",
     *       "birth_date": "1990-05-15",
     *       "avatar_url": null,
     *       "status": "active",
     *       "permissions": ["bookings.create", "bookings.view-own", "tickets.view"]
     *     }
     *   }
     * }
     * @response 422 scenario="Validation error" {
     *   "message": "The given data was invalid.",
     *   "errors": {"verification_token": ["The verification token is invalid or expired."]}
     * }
     */
    public function unlockAccount(UnlockAccountRequest $request): JsonResponse
    {
        $user = $this->authSessionService->unlockAccount($request->payload());

        return response()->json(['data' => ['user' => new AuthUserResource($user)]]);
    }

    /**
     * Get the authenticated user's profile.
     *
     * @authenticated
     *
     * @response 200 scenario="Current user" {
     *   "data": {
     *     "user": {
     *       "uuid": "977e5426-8d13-3824-86aa-b092f8ae52c5",
     *       "name": "Aïcha Hounkpè",
     *       "email": "aicha.hounkpe@gmail.com",
     *       "phone": "+22901000000",
     *       "country_code": "BJ",
     *       "birth_date": "1990-05-15",
     *       "avatar_url": null,
     *       "status": "active",
     *       "permissions": ["bookings.create", "bookings.view-own", "tickets.view"]
     *     }
     *   }
     * }
     * @response 401 scenario="Unauthenticated" {
     *   "message": "Unauthenticated."
     * }
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => ['user' => new AuthUserResource($request->user())]]);
    }

    /**
     * Update the authenticated user's profile.
     *
     * @authenticated
     *
     * @bodyParam birth_date string Birth date in Y-m-d format. Example: 1990-05-15
     *
     * @response 200 scenario="Profile updated" {
     *   "data": {
     *     "user": {
     *       "uuid": "977e5426-8d13-3824-86aa-b092f8ae52c5",
     *       "name": "Aïcha Hounkpè",
     *       "email": "aicha.hounkpe@gmail.com",
     *       "phone": "+22901000000",
     *       "country_code": "BJ",
     *       "birth_date": "1990-05-15",
     *       "avatar_url": null,
     *       "status": "active",
     *       "permissions": ["bookings.create", "bookings.view-own", "tickets.view"]
     *     }
     *   }
     * }
     * @response 401 scenario="Unauthenticated" {
     *   "message": "Unauthenticated."
     * }
     * @response 422 scenario="Validation error" {
     *   "message": "The given data was invalid.",
     *   "errors": {"email": ["The email has already been taken."]}
     * }
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->authSessionService->updateProfile($request->user(), $request->payload());

        return response()->json(['data' => ['user' => new AuthUserResource($user)]]);
    }

    /**
     * Change the authenticated user's PIN.
     *
     * @authenticated
     *
     * @response 200 scenario="PIN changed" {
     *   "data": {
     *     "user": {
     *       "uuid": "977e5426-8d13-3824-86aa-b092f8ae52c5",
     *       "name": "Aïcha Hounkpè",
     *       "email": "aicha.hounkpe@gmail.com",
     *       "phone": "+22901000000",
     *       "country_code": "BJ",
     *       "birth_date": "1990-05-15",
     *       "avatar_url": null,
     *       "status": "active",
     *       "permissions": ["bookings.create", "bookings.view-own", "tickets.view"]
     *     }
     *   }
     * }
     * @response 401 scenario="Unauthenticated" {
     *   "message": "Unauthenticated."
     * }
     * @response 422 scenario="Validation error" {
     *   "message": "The given data was invalid.",
     *   "errors": {"current_pin": ["The current PIN is incorrect."]}
     * }
     */
    public function changePin(ChangePinRequest $request): JsonResponse
    {
        $user = $this->authSessionService->changePin($request->user(), $request->payload());

        return response()->json(['data' => ['user' => new AuthUserResource($user)]]);
    }

    /**
     * Revoke the current access token.
     *
     * @authenticated
     *
     * @response 204 scenario="Logged out"
     * @response 401 scenario="Unauthenticated" {
     *   "message": "Unauthenticated."
     * }
     */
    public function logout(Request $request, OpsSessionTokenReader $tokens, RevokeOpsSessionAction $revokeSession): JsonResponse
    {
        $user = $request->user();
        $tokenId = $tokens->tokenId($request);

        // Une session OPS liée à ce jeton est fermée avec lui (motif `logout`),
        // sinon elle resterait « active » en base alors que son jeton n'existe plus.
        if ($user instanceof User && $tokenId !== null) {
            $sessions = TerminalSession::query()
                ->active()
                ->where('personal_access_token_id', $tokenId)
                ->where('user_id', $user->id)
                ->get();

            foreach ($sessions as $session) {
                $revokeSession->execute($session, OpsSessionRevocationReason::Logout, $user);
            }
        }

        $user?->currentAccessToken()?->delete();

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
