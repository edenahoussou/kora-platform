<?php

namespace App\Modules\IdentityAccess\Http\Resources;

use App\Modules\IdentityAccess\Models\UserPreference;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserPreference
 */
class UserPreferenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'push_notifications' => $this->push_notifications,
            'whatsapp_notifications' => $this->whatsapp_notifications,
            'email_notifications' => $this->email_notifications,
            'locale' => $this->locale,
            'theme' => $this->theme,
        ];
    }
}
