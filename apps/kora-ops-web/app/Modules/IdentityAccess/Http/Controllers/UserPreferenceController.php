<?php

namespace App\Modules\IdentityAccess\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IdentityAccess\Http\Requests\UpdatePreferencesRequest;
use App\Modules\IdentityAccess\Http\Resources\UserPreferenceResource;
use App\Modules\IdentityAccess\Models\UserPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Auth
 *
 * User preferences: notification channels, locale and theme.
 */
class UserPreferenceController extends Controller
{
    /**
     * Get the authenticated user's notification and display preferences.
     *
     * @authenticated
     *
     * @response 200 scenario="User preferences" {
     *   "data": {
     *     "push_notifications": true,
     *     "whatsapp_notifications": true,
     *     "sms_notifications": true,
     *     "email_notifications": true,
     *     "locale": "fr",
     *     "theme": "light"
     *   }
     * }
     * @response 401 scenario="Unauthenticated" {
     *   "message": "Unauthenticated."
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $preference = $this->getOrCreate($request);

        return response()->json(['data' => new UserPreferenceResource($preference)]);
    }

    /**
     * Update the authenticated user's notification and display preferences.
     *
     * @authenticated
     *
     * @response 200 scenario="Preferences updated" {
     *   "data": {
     *     "push_notifications": false,
     *     "whatsapp_notifications": true,
     *     "sms_notifications": false,
     *     "email_notifications": true,
     *     "locale": "en",
     *     "theme": "dark"
     *   }
     * }
     * @response 401 scenario="Unauthenticated" {
     *   "message": "Unauthenticated."
     * }
     * @response 422 scenario="Validation error" {
     *   "message": "The given data was invalid.",
     *   "errors": {"locale": ["The selected locale is invalid."]}
     * }
     */
    public function update(UpdatePreferencesRequest $request): JsonResponse
    {
        $preference = $this->getOrCreate($request);
        $preference->update($request->payload());
        if ($preference->wasChanged('push_notifications') && ! $preference->push_notifications) {
            $request->user()->devices()->update(['push_token' => null]);
        }

        return response()->json(['data' => new UserPreferenceResource($preference->refresh())]);
    }

    private function getOrCreate(Request $request): UserPreference
    {
        return UserPreference::query()->firstOrCreate(
            ['user_id' => $request->user()->id],
            [
                'push_notifications' => UserPreference::defaultPushNotificationsFor($request->user()),
                'whatsapp_notifications' => true,
                'sms_notifications' => true,
                'email_notifications' => true,
                'locale' => 'fr',
                'theme' => 'light',
            ],
        );
    }
}
