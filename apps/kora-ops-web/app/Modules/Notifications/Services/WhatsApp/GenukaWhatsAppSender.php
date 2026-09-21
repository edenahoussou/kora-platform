<?php

namespace App\Modules\Notifications\Services\WhatsApp;

use App\Modules\Settings\Settings\IntegrationsSettings;
use Illuminate\Support\Facades\Http;

class GenukaWhatsAppSender implements WhatsAppSender
{
    /** @return array{message_id: string|null} */
    public function send(string $phone, string $text, ?string $documentData = null, ?string $documentFilename = null): array
    {
        $settings = app(IntegrationsSettings::class);
        $payload = ['connectionId' => $settings->genukaConnectionId, 'to' => $phone];
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
        $response = Http::baseUrl($settings->genukaUrl)->withToken((string) ($settings->genukaToken ?? ''))->timeout(30)->connectTimeout(5)->post('/api/v1/messages', $payload);
        $response->throw();

        return ['message_id' => $response->json('id') ?? $response->json('messageId')];
    }
}
