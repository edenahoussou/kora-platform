<?php

namespace App\Modules\IdentityAccess\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\IdentityAccess\Models\UserDevice;
use App\Modules\Notifications\Services\FirebaseMessagingClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthPushTokenController extends Controller
{
    public function update(Request $request, UserDevice $device, FirebaseMessagingClient $firebase): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($device->user_id === $user->getKey() && $device->status->value === 'active', 404);
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'push_token' => ['required_if:enabled,true', 'nullable', 'string', 'max:4096'],
        ]);

        if ($validated['enabled'] && ! $user->preference?->push_notifications) {
            throw ValidationException::withMessages(['enabled' => 'Activez les notifications push dans vos préférences avant d’inscrire cet appareil.']);
        }
        if ($validated['enabled'] && ! $firebase->isServerConfigured()) {
            throw ValidationException::withMessages(['enabled' => 'Les notifications push ne sont pas disponibles actuellement.']);
        }

        $device->forceFill(['push_token' => $validated['enabled'] ? $validated['push_token'] : null])->save();

        return response()->json(['data' => ['enabled' => $device->push_token !== null]]);
    }
}
