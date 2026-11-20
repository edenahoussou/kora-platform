<?php

namespace App\Modules\Parcels\Services;

use App\Modules\Fiscalization\Data\FiscalInvoiceDocumentKind;
use App\Modules\Fiscalization\Data\FiscalInvoiceStatus;
use App\Modules\Fiscalization\Models\FiscalInvoice;
use App\Modules\Parcels\Models\Parcel;
use App\Modules\Settings\Settings\GeneralSettings;

class ParcelReceipts
{
    /** @return array<string, mixed> */
    public function build(Parcel $parcel, string $kind = 'receipt'): array
    {
        abort_unless(in_array($kind, ['receipt', 'return_receipt'], true), 422);
        abort_if($parcel->reference === null, 409, 'The parcel has no logistics reference.');
        $parcel->loadMissing(['corridor.originHub.stop', 'corridor.destinationHub.stop']);
        $returnLeg = $kind === 'return_receipt';
        $charge = $parcel->charges()->with('tariff')->where('return_leg', $returnLeg)
            ->whereNotNull('settled_payment_id')->latest('id')->firstOrFail();
        $payment = $charge->settledPayment()->firstOrFail();
        $invoice = FiscalInvoice::query()->where('parcel_charge_id', $charge->id)
            ->where('document_kind', FiscalInvoiceDocumentKind::Sale)->with('emecfInvoice')->latest('id')->first();
        $certified = $invoice?->status === FiscalInvoiceStatus::Certified;
        $fiscalCode = $certified ? $invoice->emecfInvoice?->code_mec_ef_dgi : null;
        $fiscalQr = $certified ? $invoice->emecfInvoice?->qr_code : null;
        $certified = $certified && filled($fiscalCode) && filled($fiscalQr);
        $trackingUrl = route('api.v1.parcels.tracking', ['reference' => $parcel->reference]);

        return [
            'kind' => $kind,
            'receipt_status' => $certified ? 'certified' : 'provisional',
            'receipt_label' => $certified ? 'Reçu fiscal certifié' : 'Reçu provisoire · non certifié fiscalement',
            'reference' => $parcel->reference,
            'issued_at' => $payment->confirmed_at?->toIso8601String(),
            'currency' => app(GeneralSettings::class)->currency,
            'qr_payload' => $trackingUrl,
            'parties' => [
                'sender_name' => $parcel->sender_name,
                'sender_phone' => $parcel->sender_phone,
                'recipient_name' => $parcel->recipient_name,
                'recipient_phone' => $parcel->recipient_phone,
            ],
            'route' => [
                'origin' => $parcel->corridor?->originHub?->stop?->city,
                'destination' => $parcel->corridor?->destinationHub?->stop?->city,
                'corridor_uuid' => $parcel->corridor?->uuid,
            ],
            'parcel' => [
                'weight_grams' => $parcel->weight_grams,
                'declared_grams' => $parcel->declared_grams,
                'contents' => $parcel->contents,
            ],
            'charge' => [
                'net' => $charge->net,
                'tax' => $charge->tax,
                'total' => $charge->total,
                'tax_code' => $charge->tax_code,
                'tax_rate' => $charge->tax_rate,
                'fiscal_group' => $charge->fiscal_group,
                'tariff_uuid' => $charge->tariff?->uuid,
                'tariff_version' => $charge->tariff?->version,
                'deposit_kind' => $charge->tariff?->deposit_kind,
            ],
            'payment' => [
                'uuid' => $payment->uuid,
                'method' => $payment->method?->value,
                'provider' => $payment->provider,
                'confirmed_at' => $payment->confirmed_at?->toIso8601String(),
                'cash_received_amount' => $payment->cash_received_amount,
                'change_amount' => $payment->change_amount,
            ],
            'fiscal' => [
                'status' => $invoice?->status->value ?? 'not_submitted',
                'certified' => $certified,
                'code' => $fiscalCode,
                'qr_payload' => $fiscalQr,
                'invoice_uuid' => $invoice?->uuid,
            ],
        ];
    }
}
