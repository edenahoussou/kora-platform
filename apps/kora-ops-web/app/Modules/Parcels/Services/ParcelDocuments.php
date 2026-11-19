<?php

namespace App\Modules\Parcels\Services;

use App\Models\User;
use App\Modules\Fiscalization\Data\FiscalInvoiceDocumentKind;
use App\Modules\Fiscalization\Data\FiscalInvoiceStatus;
use App\Modules\Fiscalization\Models\FiscalInvoice;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\Parcels\Models\Parcel;
use Illuminate\Support\Facades\DB;

class ParcelDocuments
{
    /** @return array<string, mixed> */
    public function render(Parcel $parcel, string $kind, ?User $actor = null, ?TerminalP501 $terminal = null): array
    {
        abort_if($parcel->reference === null, 409, 'Le colis n’a pas de référence logistique.');
        $parcel->loadMissing(['corridor.originHub.stop', 'corridor.destinationHub.stop']);
        $route = $parcel->corridor?->originHub?->stop?->city.' > '.$parcel->corridor?->destinationHub?->stop?->city;
        $result = ['kind' => $kind, 'width_mm' => 58, 'reference' => $parcel->reference];
        if ($kind === 'label') {
            $trackingUrl = route('api.v1.parcels.tracking', ['reference' => $parcel->reference]);
            $result['qr_payload'] = $trackingUrl;
            $result['esc_pos_base64'] = $this->printable(['KORA COLIS', $parcel->reference, $route], $trackingUrl);
        } else {
            $charge = $parcel->charges()->where('return_leg', $kind === 'return_receipt')->whereNotNull('settled_payment_id')->firstOrFail();
            $invoice = FiscalInvoice::query()->where('parcel_charge_id', $charge->id)->where('document_kind', FiscalInvoiceDocumentKind::Sale)->with('emecfInvoice')->firstOrFail();
            if ($invoice->status !== FiscalInvoiceStatus::Certified) {
                return $result + ['fiscal_status' => $invoice->status->value];
            }

            $qr = $invoice->emecfInvoice?->qr_code;
            $code = $invoice->emecfInvoice?->code_mec_ef_dgi;
            abort_if(blank($qr) || blank($code), 409, 'Données fiscales de certification indisponibles.');
            $result += [
                'fiscal_status' => $invoice->status->value,
                'fiscal_code' => $code,
                'fiscal_qr_payload' => $qr,
                'esc_pos_base64' => $this->printable([
                    'KORA COLIS', $invoice->kora_reference, $route,
                    'Exp. '.$parcel->sender_name, 'Dest. '.$parcel->recipient_name,
                    $parcel->weight_grams.' g | '.$invoice->created_at?->toDateString(),
                    'HT '.$charge->net.' XOF', 'TVA '.$charge->tax.' XOF',
                    'TTC '.$charge->total.' XOF', 'MECeF '.$code,
                ], (string) $qr),
            ];
        }

        if ($actor !== null) {
            $result['print_job_uuid'] = $parcel->record('print_requested', $actor, $parcel->custodyHub,
                terminal: $terminal, details: ['kind' => $kind])->uuid;
        }

        return $result;
    }

    public function recordResult(Parcel $parcel, TerminalP501 $terminal, User $actor, string $job, string $outcome): void
    {
        DB::transaction(function () use ($parcel, $terminal, $actor, $job, $outcome): void {
            $parcel = Parcel::query()->lockForUpdate()->findOrFail($parcel->id);
            $print = $parcel->events()->where('uuid', $job)->where('kind', 'print_requested')
                ->where('terminal_id', $terminal->id)->where('actor_id', $actor->id)->firstOrFail();
            $result = $parcel->events()->where('kind', 'print_result')->where('details->job_uuid', $job)->first();
            if ($result !== null) {
                abort_unless(($result->details['outcome'] ?? null) === $outcome, 409);

                return;
            }
            $parcel->record('print_result', $actor, $parcel->custodyHub, terminal: $terminal, details: [
                'job_uuid' => $print->uuid,
                'outcome' => $outcome,
            ]);
        });
    }

    /** @param list<string> $lines */
    private function printable(array $lines, string $qr): string
    {
        $text = array_map(static function (string $line): string {
            $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $line);

            return (string) preg_replace('/[^\x20-\x7E]/', ' ', $ascii ?: '');
        }, $lines);
        $storeLength = pack('v', strlen($qr) + 3);
        $commands = "\x1B\x40".implode("\n", $text)."\n"
            ."\x1D\x28\x6B\x04\x00\x31\x41\x32\x00"
            ."\x1D\x28\x6B\x03\x00\x31\x43\x06"
            ."\x1D\x28\x6B".$storeLength."\x31\x50\x30".$qr
            ."\x1D\x28\x6B\x03\x00\x31\x51\x30\n\n";

        return base64_encode($commands);
    }
}
