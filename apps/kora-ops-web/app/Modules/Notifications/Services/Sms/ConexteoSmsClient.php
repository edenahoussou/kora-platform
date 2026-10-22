<?php

namespace App\Modules\Notifications\Services\Sms;

use App\Modules\Settings\Settings\IntegrationsSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class ConexteoSmsClient
{
    private const ENDPOINT = 'https://api.conexteo.com/messages/sms';

    public function __construct(private readonly IntegrationsSettings $settings) {}

    public function isConfigured(): bool
    {
        return (bool) $this->settings->conexteoEnabled
            && filled($this->settings->conexteoAppId)
            && filled($this->settings->conexteoApiKey)
            && filled($this->settings->conexteoSender);
    }

    /**
     * Envoie un SMS ; `duplicate` signale un `external_id` déjà traité (HTTP 409).
     *
     * @return array{message_id: string|null, credits_used: float|null, duplicate: bool}
     */
    public function send(string $recipient, string $content, ?string $externalId = null): array
    {
        $response = $this->post($recipient, $content, $externalId, false);

        if ($response->status() === 409) {
            return ['message_id' => null, 'credits_used' => null, 'duplicate' => true];
        }

        if (! $response->successful()) {
            throw ConexteoSmsException::fromStatus($response->status());
        }

        return [
            'message_id' => $this->stringOrNull($response->json('message_id')),
            'credits_used' => $this->floatOrNull($response->json('credits_used')),
            'duplicate' => false,
        ];
    }

    /**
     * Résumé des crédits sans envoi (`resumeBefore=true`).
     *
     * @return array{credits_used: int|null, credits_remaining: int|null}
     */
    public function preview(string $recipient, string $content): array
    {
        $response = $this->post($recipient, $content, null, true);

        if (! $response->successful()) {
            throw ConexteoSmsException::fromStatus($response->status());
        }

        return [
            'credits_used' => $this->intOrNull($response->json('credits_used') ?? $response->json('credits_required')),
            'credits_remaining' => $this->intOrNull($response->json('credits_remaining') ?? $response->json('credits')),
        ];
    }

    private function post(string $recipient, string $content, ?string $externalId, bool $preview): Response
    {
        $payload = [
            'content' => $content,
            'recipients' => [$this->normalizeRecipient($recipient)],
            'sender' => (string) $this->settings->conexteoSender,
        ];

        if ($externalId !== null) {
            $payload['external_id'] = $externalId;
        }

        try {
            return Http::asJson()
                ->withHeaders([
                    'X-APP-ID' => (string) $this->settings->conexteoAppId,
                    'X-API-KEY' => (string) $this->settings->conexteoApiKey,
                ])
                ->timeout(10)
                ->connectTimeout(5)
                ->post(self::ENDPOINT.($preview ? '?resumeBefore=true' : ''), $payload);
        } catch (ConnectionException $exception) {
            throw ConexteoSmsException::unavailable($exception);
        }
    }

    /** Conexteo route les numéros béninois à l'ancien format à 8 chiffres. */
    private function normalizeRecipient(string $phone): string
    {
        return preg_replace('/^\+?22901(\d{8})$/', '+229$1', trim($phone)) ?? $phone;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }

    private function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function floatOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
