<?php

namespace App\Modules\Notifications\Models;

use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Journal des envois externes (table `notification_logs`, créée avec les
 * tables KORA BUS). MEZ-98 y écrit chaque e-mail d'alerte opérationnelle :
 * un destinataire, un événement, un résultat.
 *
 * @property string $channel
 * @property string $recipient
 * @property string $template
 * @property string $status
 * @property Carbon|null $sent_at
 * @property array<string, mixed>|null $payload
 */
class NotificationLog extends KoraModel
{
    protected $fillable = [
        'uuid',
        'channel',
        'recipient',
        'template',
        'reference_type',
        'reference_id',
        'status',
        'sent_at',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
