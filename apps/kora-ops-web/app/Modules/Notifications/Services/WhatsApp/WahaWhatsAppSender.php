<?php

namespace App\Modules\Notifications\Services\WhatsApp;

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
        $chatId = preg_replace('/\D+/', '', $phone).'@c.us';
        $http = $this->http();

        $this->typing($http, $chatId, 'start');
        $this->sleepRandom();
        $this->typing($http, $chatId, 'stop');

        if ($documentData !== null) {
            $response = $http->post('/api/sendFile', [
                'session' => config('services.waha.session'),
                'chatId' => $chatId,
                'file' => [
                    'mimetype' => 'application/pdf',
                    'filename' => $documentFilename ?? 'billet.pdf',
                    'data' => $documentData,
                ],
                'caption' => $text,
            ]);
            $this->throwIfBlocked($response);

            return ['message_id' => $response->json('id') ?? $response->json('key.id')];
        }

        $response = $http->post('/api/sendText', ['session' => config('services.waha.session'), 'chatId' => $chatId, 'text' => $text]);
        $this->throwIfBlocked($response);

        return ['message_id' => $response->json('id') ?? $response->json('key.id')];
    }

    private function throwIfBlocked(Response $response): void
    {
        $body = $response->body();
        if (preg_match('/\b(463|475)\b/', $body, $match)) {
            throw new WahaReachoutBlockedException((int) $match[1], "WhatsApp a temporairement restreint l'envoi (code {$match[1]}).");
        }
        $response->throw();
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl((string) config('services.waha.url'))
            ->withToken((string) config('services.waha.token'))
            ->timeout(30)
            ->connectTimeout(5);
    }

    private function typing(PendingRequest $http, string $chatId, string $action): void
    {
        try {
            $http->post("/api/{$action}Typing", [
                'session' => config('services.waha.session'),
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
