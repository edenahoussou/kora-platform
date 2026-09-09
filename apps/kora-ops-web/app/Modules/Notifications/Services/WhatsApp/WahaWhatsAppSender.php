<?php

namespace App\Modules\Notifications\Services\WhatsApp;

use Illuminate\Support\Facades\Http;

class WahaWhatsAppSender implements WhatsAppSender
{
    /** @return array{message_id: string|null} */
    public function send(string $phone, string $text, ?string $documentData = null, ?string $documentFilename = null): array
    {
        $chatId = preg_replace('/\D+/', '', $phone).'@c.us';
        $http = Http::baseUrl((string) config('services.waha.url'))->withToken((string) config('services.waha.token'))->timeout(30)->connectTimeout(5);
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
            $response->throw();

            return ['message_id' => $response->json('id') ?? $response->json('key.id')];
        }

        $response = $http->post('/api/sendText', ['session' => config('services.waha.session'), 'chatId' => $chatId, 'text' => $text]);
        $response->throw();

        return ['message_id' => $response->json('id') ?? $response->json('key.id')];
    }
}
