<?php

namespace App\Modules\AssistedBooking\Http\Resources;

use App\Modules\AssistedBooking\Models\AssistedBooking;
use App\Modules\AssistedBooking\Services\AssistedBookingPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AssistedBooking */
class AssistedBookingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $presenter = app(AssistedBookingPresenter::class);

        return [
            'uuid' => $this->uuid,
            'status' => $this->status->value,
            'channel' => $this->handoff_channel->value,
            'reservation' => [
                'uuid' => $this->reservation->uuid,
                'reference' => $this->reservation->reference,
                'status' => $this->reservation->status->value,
            ],
            'agency' => [
                'uuid' => $this->preferredAgency->uuid,
                'code' => $this->preferredAgency->code,
                'name' => $this->preferredAgency->name,
                'city' => $this->preferredAgency->city,
            ],
            'contact' => [
                'name' => $this->contact_name,
                'phone' => $this->contact_phone,
            ],
            'channels' => [
                'call_phone' => $this->preferredAgency->call_phone,
                'whatsapp_phone' => $this->preferredAgency->whatsapp_phone,
                'call_url' => $presenter->callUrl($this->resource),
                'whatsapp_url' => $presenter->whatsappUrl($this->resource),
            ],
            'whatsapp_message' => $presenter->whatsappMessage($this->resource),
            'snapshot' => $this->snapshot,
            'amount' => [
                'original' => $this->original_total_amount,
                'current' => $this->current_total_amount,
                'currency' => $this->currency,
            ],
            'valid_until' => $this->expires_at->toIso8601String(),
            'actions' => [
                'resume_online' => $this->status->value === 'pending' && $this->expires_at->isFuture(),
            ],
        ];
    }
}
