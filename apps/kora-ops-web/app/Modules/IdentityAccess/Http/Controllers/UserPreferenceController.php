<?php

namespace App\Modules\IdentityAccess\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IdentityAccess\Http\Requests\UpdatePreferencesRequest;
use App\Modules\IdentityAccess\Http\Resources\UserPreferenceResource;
use App\Modules\IdentityAccess\Models\UserPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserPreferenceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $preference = $this->getOrCreate($request);

        return response()->json(['data' => new UserPreferenceResource($preference)]);
    }

    public function update(UpdatePreferencesRequest $request): JsonResponse
    {
        $preference = $this->getOrCreate($request);
        $preference->update($request->payload());

        return response()->json(['data' => new UserPreferenceResource($preference->refresh())]);
    }

    private function getOrCreate(Request $request): UserPreference
    {
        return UserPreference::query()->firstOrCreate(
            ['user_id' => $request->user()->id],
            [
                'push_notifications' => true,
                'whatsapp_notifications' => true,
                'email_notifications' => true,
                'locale' => 'fr',
                'theme' => 'light',
            ],
        );
    }
}
