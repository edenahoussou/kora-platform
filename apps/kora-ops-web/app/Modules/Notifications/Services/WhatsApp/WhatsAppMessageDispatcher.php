<?php

namespace App\Modules\Notifications\Services\WhatsApp;

use App\Modules\Settings\Settings\IntegrationsSettings;
use Illuminate\Http\Client\RequestException;
use Throwable;

class WhatsAppMessageDispatcher
{
    public function __construct(
        private readonly GenukaWhatsAppSender $genuka,
        private readonly WahaWhatsAppSender $waha,
        private readonly IntegrationsSettings $settings,
    ) {}

    /**
     * @return array{provider: string, message_id: string|null, fallback_error: string|null}
     */
    public function send(string $phone, string $text, ?string $documentData = null, ?string $documentFilename = null): array
    {
        $fallbackError = null;

        if ($this->isGenukaConfigured()) {
            try {
                $result = $this->genuka->send($phone, $text, $documentData, $documentFilename);

                return [
                    'provider' => 'genuka',
                    'message_id' => $result['message_id'],
                    'fallback_error' => null,
                ];
            } catch (Throwable $exception) {
                if (! $this->shouldFallback($exception)) {
                    throw $exception;
                }

                $fallbackError = $exception->getMessage();
            }
        } else {
            $fallbackError = 'Genuka non configuré (GENUKA_TOKEN manquant) — bascule directe vers WAHA.';
        }

        $result = $this->waha->send($phone, $text, $documentData, $documentFilename);

        return [
            'provider' => 'waha',
            'message_id' => $result['message_id'],
            'fallback_error' => $fallbackError,
        ];
    }

    private function isGenukaConfigured(): bool
    {
        return (string) ($this->settings->genukaToken ?? '') !== ''
            && (string) ($this->settings->genukaConnectionId ?? '') !== '';
    }

    private function shouldFallback(Throwable $exception): bool
    {
        $status = $exception instanceof RequestException ? $exception->response->status() : null;

        return $status === null || $status === 401 || $status === 403 || $status === 408 || $status === 429 || $status >= 500;
    }
}
