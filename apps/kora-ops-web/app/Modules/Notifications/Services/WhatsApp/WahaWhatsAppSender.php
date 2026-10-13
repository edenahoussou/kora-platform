<?php

namespace App\Modules\Notifications\Services\WhatsApp;

use App\Modules\Settings\Settings\IntegrationsSettings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * @see https://waha.devlike.pro/docs/overview/how-to-avoid-blocking/
 */
class WahaWhatsAppSender implements WhatsAppSender
{
    private const TYPING_DELAY_MIN_MS = 800;

    private const TYPING_DELAY_MAX_MS = 2500;

    /** @return array{message_id: string|null} */
    public function send(string $phone, string $text, ?string $documentData = null, ?string $documentFilename = null): array
    {
        $settings = app(IntegrationsSettings::class);
        $chatId = preg_replace('/\D+/', '', $phone).'@c.us';
        $http = $this->http();

        $this->typing($http, $chatId, 'start');
        $this->sleepRandom();
        $this->typing($http, $chatId, 'stop');

        if ($documentData !== null) {
            $response = $http->post('/api/sendFile', [
                'session' => $settings->wahaSession,
                'chatId' => $chatId,
                'file' => [
                    'mimetype' => 'application/pdf',
                    'filename' => $documentFilename ?? 'billet.pdf',
                    'data' => $documentData,
                ],
                'caption' => $text,
            ]);
            $this->throwIfBlocked($response, $phone);

            return ['message_id' => $response->json('id') ?? $response->json('key.id')];
        }

        $response = $http->post('/api/sendText', ['session' => $settings->wahaSession, 'chatId' => $chatId, 'text' => $text]);
        $this->throwIfBlocked($response, $phone);

        return ['message_id' => $response->json('id') ?? $response->json('key.id')];
    }

    private function throwIfBlocked(Response $response, string $phone): void
    {
        $code = $response->status();
        $body = $response->body();
        if (str_contains($body, 'no LID found')) {
            throw new InvalidWhatsAppNumberException($phone);
        }
        if (in_array($code, [463, 475], true) || preg_match('/\b(463|475)\b/', $body, $match)) {
            $wahaCode = (int) ($match[1] ?? $code);
            throw new WahaReachoutBlockedException($wahaCode, "WhatsApp a temporairement restreint l'envoi (code {$wahaCode}).");
        }
        $response->throw();
    }

    private function http(): PendingRequest
    {
        $settings = app(IntegrationsSettings::class);

        return Http::baseUrl($settings->wahaUrl)
            ->withHeaders(['X-Api-Key' => (string) ($settings->wahaToken ?? '')])
            ->timeout(30)
            ->connectTimeout(5);
    }

    private function typing(PendingRequest $http, string $chatId, string $action): void
    {
        try {
            $settings = app(IntegrationsSettings::class);
            $http->post("/api/{$action}Typing", [
                'session' => $settings->wahaSession,
                'chatId' => $chatId,
            ]);
        } catch (\Throwable) {
        }
    }

    private function sleepRandom(): void
    {
        usleep(random_int(self::TYPING_DELAY_MIN_MS, self::TYPING_DELAY_MAX_MS) * 1000);
    }
}
