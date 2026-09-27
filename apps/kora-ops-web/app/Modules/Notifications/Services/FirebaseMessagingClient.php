<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Settings\Settings\IntegrationsSettings;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\Messaging\SenderIdMismatch;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use RuntimeException;

class FirebaseMessagingClient
{
    public function __construct(private readonly IntegrationsSettings $settings) {}

    public function isServerConfigured(): bool
    {
        return $this->settings->firebasePushEnabled
            && $this->serviceAccount() !== null;
    }

    /** @param array{title: string, body: string, link?: string} $message */
    public function send(string $token, array $message): bool
    {
        if ($token === '') {
            return false;
        }

        $serviceAccount = $this->serviceAccount();
        if (! $this->settings->firebasePushEnabled || $serviceAccount === null) {
            return false;
        }

        try {
            $messaging = (new Factory)
                ->withServiceAccount($serviceAccount)
                ->createMessaging();

            $cloudMessage = CloudMessage::new()
                ->withToken($token)
                ->withNotification([
                    'title' => $message['title'],
                    'body' => $message['body'],
                ]);

            if (isset($message['link'])) {
                $cloudMessage = $cloudMessage->withWebPushConfig([
                    'fcm_options' => ['link' => $this->requireNonEmptyLink($message['link'])],
                ]);
            }

            $messaging->send($cloudMessage);

            return true;
        } catch (NotFound|SenderIdMismatch) {
            return false;
        }
    }

    /** @return non-empty-string */
    private function requireNonEmptyLink(string $link): string
    {
        if ($link === '') {
            throw new RuntimeException('A Web Push link cannot be empty.');
        }

        return $link;
    }

    /** @return array<string, mixed>|null */
    private function serviceAccount(): ?array
    {
        if (blank($this->settings->firebaseProjectId) || blank($this->settings->firebaseServiceAccountJson)) {
            return null;
        }

        $serviceAccount = json_decode($this->settings->firebaseServiceAccountJson, true);

        if (! is_array($serviceAccount)
            || ($serviceAccount['type'] ?? null) !== 'service_account'
            || ($serviceAccount['project_id'] ?? null) !== $this->settings->firebaseProjectId
            || blank($serviceAccount['client_email'] ?? null)
            || blank($serviceAccount['private_key'] ?? null)) {
            throw new RuntimeException('The Firebase service account settings are incomplete or inconsistent.');
        }

        return $serviceAccount;
    }
}
