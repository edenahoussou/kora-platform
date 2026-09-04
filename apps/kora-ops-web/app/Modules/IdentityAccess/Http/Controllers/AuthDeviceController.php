<?php

namespace App\Modules\IdentityAccess\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IdentityAccess\Http\Resources\UserDeviceResource;
use App\Modules\IdentityAccess\Models\UserDevice;
use App\Modules\IdentityAccess\Services\UserDeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Auth
 *
 * Trusted device management for the authenticated user.
 */
class AuthDeviceController extends Controller
{
    public function __construct(private UserDeviceService $userDeviceService) {}

    /**
     * List the authenticated user's trusted devices.
     *
     * @authenticated
     *
     * @response 200 scenario="Device list" {
     *   "data": [
     *     {
     *       "uuid": "a3091549-9179-454e-8440-58179f2208df",
     *       "name": "iPhone 15 Pro",
     *       "platform": "ios",
     *       "trusted_at": "2026-08-20T13:05:00.000Z",
     *       "last_seen_at": "2026-08-20T13:05:00.000Z",
     *       "status": "trusted"
     *     }
     *   ]
     * }
     * @response 401 scenario="Unauthenticated" {
     *   "message": "Unauthenticated."
     * }
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => UserDeviceResource::collection(
                $this->userDeviceService->listForUser($request->user()),
            ),
        ]);
    }

    /**
     * Revoke a trusted device.
     *
     * @authenticated
     *
     * @response 204 scenario="Device revoked"
     * @response 401 scenario="Unauthenticated" {
     *   "message": "Unauthenticated."
     * }
     * @response 404 scenario="Device not found" {
     *   "message": "No query results for model [App\\Modules\\IdentityAccess\\Models\\UserDevice]."
     * }
     */
    public function destroy(Request $request, UserDevice $device): JsonResponse
    {
        $this->userDeviceService->revokeForUser($request->user(), $device);

        return response()->json(status: 204);
    }
}
