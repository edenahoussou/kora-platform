<?php

namespace App\Modules\Notifications\Services\WhatsApp;

interface WhatsAppSender
{
    /** @return array{message_id: string|null} */
    public function send(string $phone, string $text, ?string $documentUrl = null): array;
}
