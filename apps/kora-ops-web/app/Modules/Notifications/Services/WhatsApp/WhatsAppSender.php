<?php

namespace App\Modules\Notifications\Services\WhatsApp;

interface WhatsAppSender
{
    /**
     * @param  string|null  $documentData  base64-encoded file content
     * @param  string|null  $documentFilename  filename with extension
     * @return array{message_id: string|null}
     */
    public function send(string $phone, string $text, ?string $documentData = null, ?string $documentFilename = null): array;
}
