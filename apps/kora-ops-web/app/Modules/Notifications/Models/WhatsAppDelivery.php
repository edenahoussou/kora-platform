<?php

namespace App\Modules\Notifications\Models;

use App\Modules\Booking\Models\Reservation;
use App\Modules\Notifications\Data\WhatsAppDeliveryStatus;
use App\Modules\Support\Models\KoraModel;
use App\Modules\Ticketing\Models\Ticket;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppDelivery extends KoraModel
{
    protected $table = 'whatsapp_deliveries';

    protected $fillable = [
        'uuid',
        'reservation_id',
        'ticket_id',
        'provider',
        'recipient_phone',
        'status',
        'attempts',
        'provider_message_id',
        'last_error',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => WhatsAppDeliveryStatus::class,
            'attempts' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Reservation, $this> */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
