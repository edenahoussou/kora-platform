<?php

namespace App\Modules\AssistedBooking\Http\Resources;

use App\Modules\AssistedBooking\Models\AssistedBooking;
use App\Modules\AssistedBooking\Services\AssistedBookingSupportChannels;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AssistedBooking */
class AssistedBookingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $supportChannels = app(AssistedBookingSupportChannels::class)->forBooking($this->resource);

        return [
            'uuid' => $this->uuid,
            'status' => $this->status->value,
            'channel' => $this->handoff_channel->value,
            'reservation' => [
                'uuid' => $this->reservation->uuid,
                'reference' => $this->reservation->reference,
                'status' => $this->reservation->status->value,
            ],
            'handling_agency' => $this->handlingAgency ? [
                'uuid' => $this->handlingAgency->uuid,
                'code' => $this->handlingAgency->code,
                'name' => $this->handlingAgency->name,
                'city' => $this->handlingAgency->city,
            ] : null,
            'assigned_agent' => $this->assignedUser ? [
                'uuid' => $this->assignedUser->uuid,
                'name' => $this->assignedUser->name,
            ] : null,
            'contact' => [
                'name' => $this->contact_name,
                'phone' => $this->contact_phone,
            ],
            'channels' => [
                'call_phone' => $supportChannels['call_phone'],
                'whatsapp_phone' => $supportChannels['whatsapp_phone'],
                'call_url' => $supportChannels['call_url'],
                'whatsapp_url' => $supportChannels['whatsapp_url'],
            ],
            'whatsapp_message' => $supportChannels['whatsapp_message'],
            'snapshot' => $this->snapshot,
            'amount' => [
                'original' => $this->original_total_amount,
                'current' => $this->current_total_amount,
                'currency' => $this->currency,
            ],
            'valid_until' => $this->expires_at->toIso8601String(),
            'actions' => [
                'resume_online' => $this->status->value === 'pending'
                    && $this->handlingAgency === null
                    && $this->expires_at->isFuture(),
            ],
        ];
    }
}
