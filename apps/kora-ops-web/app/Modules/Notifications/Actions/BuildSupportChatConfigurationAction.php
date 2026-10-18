<?php

namespace App\Modules\Notifications\Actions;

use App\Models\User;
use App\Modules\Settings\Settings\IntegrationsSettings;

class BuildSupportChatConfigurationAction
{
    public function __construct(
        private readonly IntegrationsSettings $settings,
        private readonly BuildSupportChatVisitorAction $visitor,
    ) {}

    /**
     * Configuration publique tawk.to pour les apps mobiles (passager et OPS).
     * Aucun secret : le `secure_hash` est un HMAC de l'email, jamais la clé API.
     *
     * @return array<string, mixed>
     */
    public function execute(?User $user): array
    {
        $settings = $this->settings;

        if (! $settings->tawktoEnabled || blank($settings->tawktoPropertyId) || blank($settings->tawktoWidgetId)) {
            return ['enabled' => false];
        }

        $email = $user?->email;

        return [
            'enabled' => true,
            'property_id' => $settings->tawktoPropertyId,
            'widget_id' => $settings->tawktoWidgetId,
            'direct_chat_url' => "https://tawk.to/chat/{$settings->tawktoPropertyId}/{$settings->tawktoWidgetId}",
            'secure_hash' => $email && $settings->tawktoApiKey
                ? hash_hmac('sha256', $email, $settings->tawktoApiKey)
                : null,
            'visitor' => $user ? $this->visitor->execute($user) : null,
        ];
    }
}
