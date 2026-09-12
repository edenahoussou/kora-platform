<?php

namespace App\Modules\Notifications\Services\WhatsApp;

use Illuminate\Support\Facades\Http;

class GenukaWhatsAppSender implements WhatsAppSender
{
    /** @return array{message_id: string|null} */
    public function send(string $phone, string $text, ?string $documentData = null, ?string $documentFilename = null): array
    {
        $payload = ['connectionId' => config('services.genuka.connection_id'), 'to' => $phone];
        if ($documentData !== null) {
            $payload['document'] = [
                'data' => $documentData,
                'mimetype' => 'application/pdf',
                'filename' => $documentFilename ?? 'billet.pdf',
                'caption' => $text,
            ];
        } else {
            $payload['text'] = $text;
        }
        $response = Http::baseUrl((string) config('services.genuka.url'))->withToken((string) config('services.genuka.token'))->timeout(30)->connectTimeout(5)->post('/api/v1/messages', $payload);
        $response->throw();

        return ['message_id' => $response->json('id') ?? $response->json('messageId')];
    }
}
