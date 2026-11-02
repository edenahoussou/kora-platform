<?php

namespace App\Modules\IdentityAccess\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IdentityAccess\Exceptions\AuthBusinessException;
use App\Modules\IdentityAccess\Exceptions\UserNotFoundException;
use App\Modules\IdentityAccess\Http\Requests\RequestOtpRequest;
use App\Modules\IdentityAccess\Http\Requests\VerifyOtpRequest;
use App\Modules\IdentityAccess\Services\AuthOtpService;
use Illuminate\Http\JsonResponse;

/**
 * @group Auth
 *
 * OTP challenge request and verification for registration, login, PIN reset and account unlock.
 */
class AuthOtpController extends Controller
{
    public function __construct(private AuthOtpService $authOtpService) {}

    /**
     * Request an OTP challenge for registration, login, PIN reset or account unlock.
     *
     * Sends a one-time code to WhatsApp when the account has a phone number, with email
     * fallback. The returned `otp_challenge_uuid` must be submitted to the verify endpoint.
     *
     * @bodyParam identifier string required Email or phone number. For registration, this
     * must be an email. Existing clients may use the `email` alias instead.
     * @bodyParam email string Legacy alias for `identifier`; do not send both fields. Example: agent@kora.example
     *
     * @unauthenticated
     *
     * @response 202 scenario="OTP sent" {
     *   "data": {
     *     "otp_challenge_uuid": "b1234567-89ab-cdef-0123-456789abcdef",
     *     "purpose": "registration",
     *     "email": "aicha.hounkpe@gmail.com",
     *     "expires_at": "2026-08-20T13:10:00.000Z"
     *   }
     * }
     * @response 422 scenario="Validation error" {
     *   "message": "The given data was invalid.",
     *   "errors": {"identifier": ["L'email ou le numéro de téléphone est obligatoire."]}
     * }
     * @response 404 scenario="User not found" {
     *   "error": {
     *     "code": "user_not_found",
     *     "message": "Utilisateur introuvable."
     *   }
     * }
     * @response 429 scenario="Too many requests" {
     *   "message": "Too many requests. Please try again later."
     * }
     */
    public function request(RequestOtpRequest $request): JsonResponse
    {
        try {
            $payload = $request->payload();
            $challenge = $this->authOtpService->request(
                $payload['identifier'],
                $payload['purpose'],
                $request,
            );
        } catch (UserNotFoundException $e) {
            return response()->json([
                'error' => [
                    'code' => 'user_not_found',
                    'message' => $e->getMessage(),
                ],
            ], 404);
        } catch (AuthBusinessException $e) {
            return response()->json([
                'error' => [
                    'code' => $e->errorCode,
                    'message' => $e->getMessage(),
                ],
            ], $e->status);
        }

        return response()->json([
            'data' => [
                'otp_challenge_uuid' => $challenge->uuid,
                'purpose' => $challenge->purpose->value,
                'identifier' => $challenge->identifier,
                'email' => $challenge->user()->value('email') ?? (filter_var($challenge->identifier, FILTER_VALIDATE_EMAIL) !== false ? $challenge->identifier : null),
                'expires_at' => $challenge->expires_at->toISOString(),
            ],
        ], 202);
    }

    /**
     * Verify an OTP code and obtain a verification token.
     *
     * The `verification_token` is used to complete registration, reset PIN, or unlock
     * an account via the corresponding endpoints.
     *
     * @unauthenticated
     *
     * @response 200 scenario="OTP verified" {
     *   "data": {
     *     "otp_challenge_uuid": "b1234567-89ab-cdef-0123-456789abcdef",
     *     "purpose": "registration",
     *     "verification_token": "vrf_aBcDeFgHiJkLmNoPqRsTuVwXyZ0123456789",
     *     "verified_at": "2026-08-20T13:07:00.000Z"
     *   }
     * }
     * @response 422 scenario="Invalid code" {
     *   "message": "The given data was invalid.",
     *   "errors": {"code": ["The OTP code is invalid or expired."]}
     * }
     */
    public function verify(VerifyOtpRequest $request): JsonResponse
    {
        $result = $this->authOtpService->verify(
            $request->string('otp_challenge_uuid')->toString(),
            $request->string('purpose')->toString(),
            $request->string('code')->toString(),
        );

        return response()->json([
            'data' => [
                'otp_challenge_uuid' => $result['challenge']->uuid,
                'purpose' => $result['challenge']->purpose->value,
                'verification_token' => $result['verification_token'],
                'verified_at' => $result['challenge']->verified_at?->toISOString(),
            ],
        ]);
    }
}
