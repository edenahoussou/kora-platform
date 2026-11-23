<?php

namespace App\Modules\Parcels\Services;

use App\Modules\Parcels\Models\Parcel;
use App\Modules\Settings\Settings\GeneralSettings;
use App\Modules\Ticketing\Actions\GenerateTicketQrCodeAction;

/**
 * Payload du bordereau d’envoi A4 paysage.
 *
 * Le volet collé reste volontairement pauvre : référence, QR, trajet, hub de
 * destination, destinataire et poids. Les données commerciales et le contenu
 * déclaré restent sur la souche agence.
 */
class ParcelLabelDocument
{
    /**
     * Statuts Phase 1 autorisant le bordereau d’envoi.
     *
     * `preregistered` est exclu : le colis n’est pas encore accepté. Les statuts
     * de sortie du flux aller (`withdrawn`, `return_*`, `returned`), les refus,
     * annulations, expirations et annulations en attente sont aussi exclus.
     *
     * @var list<string>
     */
    public const PRINTABLE_STATUSES = [
        'weighed',
        'registered',
        'ready',
        'assigned',
        'loaded',
        'in_transit',
        'arrived',
        'available',
    ];

    public function __construct(private readonly GenerateTicketQrCodeAction $qrCode) {}

    public static function statusAllowsPrinting(?string $status): bool
    {
        return is_string($status) && in_array($status, self::PRINTABLE_STATUSES, true);
    }

    /** @return array<string, mixed> */
    public function build(Parcel $parcel): array
    {
        abort_if($parcel->reference === null || $parcel->reference === '', 409, 'The parcel has no logistics reference.');
        abort_unless(self::statusAllowsPrinting($parcel->status), 409, 'The parcel status does not allow a shipping label.');
        $parcel->loadMissing([
            'corridor.originHub.stop',
            'corridor.destinationHub.stop',
            'depositHub.stop',
            'custodyHub.stop',
        ]);

        $originHub = $parcel->corridor?->originHub;
        $destinationHub = $parcel->corridor?->destinationHub;
        $trackingUrl = route('api.v1.parcels.tracking', ['reference' => $parcel->reference]);
        $brand = trim((string) app(GeneralSettings::class)->displayName);
        if ($brand === '') {
            $brand = (string) config('app.name', 'KORA');
        }

        return [
            'brand' => $brand,
            'document_title' => 'Bordereau d’envoi',
            'reference' => $parcel->reference,
            'tracking_url' => $trackingUrl,
            'qr_svg' => $this->qrCode->execute($trackingUrl, 360),
            'route' => [
                'origin' => $originHub?->stop?->city,
                'destination' => $destinationHub?->stop?->city,
            ],
            'origin_hub' => [
                'name' => $originHub?->stop?->name,
                'city' => $originHub?->stop?->city,
            ],
            'destination_hub' => [
                'name' => $destinationHub?->stop?->name,
                'city' => $destinationHub?->stop?->city,
            ],
            'sender_name' => $parcel->sender_name,
            'recipient_name' => $parcel->recipient_name,
            'contents' => $parcel->contents,
            'weight_grams' => $parcel->weight_grams ?? $parcel->declared_grams,
            'generated_at' => now()->toIso8601String(),
            'generated_at_label' => now()->format('d/m/Y H:i'),
        ];
    }
}
