<?php

namespace App\Modules\Notifications\Services\WhatsApp;

use Illuminate\Support\Facades\Http;

class GenukaWhatsAppSender implements WhatsAppSender
{
    /** @return array{message_id: string|null} */
    public function send(string $phone, string $text, ?string $documentUrl = null): array
    {
        $payload = ['connectionId' => config('services.genuka.connection_id'), 'to' => $phone, 'text' => $text];
        if ($documentUrl !== null) {
            $payload['document'] = ['link' => $documentUrl, 'caption' => $text];
        }
        $response = Http::baseUrl((string) config('services.genuka.url'))->withToken((string) config('services.genuka.token'))->timeout(15)->connectTimeout(5)->post('/api/v1/messages', $payload);
        $response->throw();

        return ['message_id' => $response->json('id') ?? $response->json('messageId')];
    }
}
