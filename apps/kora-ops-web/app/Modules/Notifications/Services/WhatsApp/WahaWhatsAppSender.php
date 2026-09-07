<?php

namespace App\Modules\Notifications\Services\WhatsApp;

use Illuminate\Support\Facades\Http;

class WahaWhatsAppSender implements WhatsAppSender
{
    /** @return array{message_id: string|null} */
    public function send(string $phone, string $text, ?string $documentUrl = null): array
    {
        $chatId = preg_replace('/\D+/', '', $phone).'@c.us';
        $http = Http::baseUrl((string) config('services.waha.url'))->withToken((string) config('services.waha.token'))->timeout(15)->connectTimeout(5);
        if ($documentUrl !== null) {
            $http->post('/api/sendFile', ['session' => config('services.waha.session'), 'chatId' => $chatId, 'file' => ['url' => $documentUrl], 'caption' => $text])->throw();
        }
        $response = $http->post('/api/sendText', ['session' => config('services.waha.session'), 'chatId' => $chatId, 'text' => $text]);
        $response->throw();

        return ['message_id' => $response->json('id') ?? $response->json('key.id')];
    }
}
