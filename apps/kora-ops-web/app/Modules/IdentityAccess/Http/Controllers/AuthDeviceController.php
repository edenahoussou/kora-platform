<?php

namespace App\Modules\IdentityAccess\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IdentityAccess\Http\Resources\UserDeviceResource;
use App\Modules\IdentityAccess\Models\UserDevice;
use App\Modules\IdentityAccess\Services\UserDeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthDeviceController extends Controller
{
    public function __construct(private UserDeviceService $userDeviceService) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => UserDeviceResource::collection(
                $this->userDeviceService->listForUser($request->user()),
            ),
        ]);
    }

    public function destroy(Request $request, UserDevice $device): JsonResponse
    {
        $this->userDeviceService->revokeForUser($request->user(), $device);

        return response()->json(status: 204);
    }
}
