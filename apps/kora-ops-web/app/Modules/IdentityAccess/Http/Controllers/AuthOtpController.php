<?php

namespace App\Modules\IdentityAccess\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IdentityAccess\Http\Requests\RequestOtpRequest;
use App\Modules\IdentityAccess\Http\Requests\VerifyOtpRequest;
use App\Modules\IdentityAccess\Services\AuthOtpService;
use Illuminate\Http\JsonResponse;

class AuthOtpController extends Controller
{
    public function __construct(private AuthOtpService $authOtpService) {}

    public function request(RequestOtpRequest $request): JsonResponse
    {
        $challenge = $this->authOtpService->request(
            $request->string('email')->toString(),
            $request->string('purpose')->toString(),
            $request,
        );

        return response()->json([
            'data' => [
                'otp_challenge_uuid' => $challenge->uuid,
                'purpose' => $challenge->purpose->value,
                'email' => $challenge->email,
                'expires_at' => $challenge->expires_at->toISOString(),
            ],
        ], 202);
    }

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
