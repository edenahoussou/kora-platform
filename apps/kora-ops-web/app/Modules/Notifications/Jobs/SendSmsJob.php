<?php

namespace App\Modules\Notifications\Jobs;

use App\Modules\Notifications\Models\NotificationLog;
use App\Modules\Notifications\Services\NotificationLogRecorder;
use App\Modules\Notifications\Services\Sms\ConexteoSmsClient;
use App\Modules\Notifications\Services\Sms\ConexteoSmsException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    /** @var array<int, int> */
    public array $backoff = [15, 90, 300];

    public function __construct(
        public readonly string $recipient,
        public readonly string $content,
        public readonly string $dedupeKey,
        public readonly string $type,
        public readonly ?string $referenceType = null,
        public readonly ?int $referenceId = null,
    ) {}

    public function handle(ConexteoSmsClient $client, NotificationLogRecorder $recorder): void
    {
        if (! $client->isConfigured() || $this->alreadySent()) {
            return;
        }

        try {
            $result = $client->send($this->recipient, $this->content, hash('sha256', $this->dedupeKey));
            $recorder->recordSms($this->recipient, $this->type, 'sent', $this->referenceType, $this->referenceId, [
                'message_id' => $result['message_id'],
                'credits_used' => $result['credits_used'],
                'duplicate' => $result['duplicate'],
            ]);
        } catch (ConexteoSmsException $exception) {
            $recorder->recordSms($this->recipient, $this->type, 'failed', $this->referenceType, $this->referenceId, [
                'error' => $exception->getMessage(),
            ]);

            if ($exception->shouldRetry()) {
                throw $exception;
            }
        }
    }

    private function alreadySent(): bool
    {
        if ($this->referenceType === null || $this->referenceId === null) {
            return false;
        }

        return NotificationLog::query()
            ->where('channel', NotificationLogRecorder::SMS_CHANNEL)
            ->where('template', $this->type)
            ->where('recipient', $this->recipient)
            ->where('reference_type', $this->referenceType)
            ->where('reference_id', $this->referenceId)
            ->where('status', 'sent')
            ->exists();
    }
}
