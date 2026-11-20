<?php

namespace App\Modules\Parcels\Services;

use App\Models\User;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\Parcels\Models\Parcel;
use Illuminate\Support\Facades\DB;

class ParcelDocuments
{
    public function __construct(private ParcelReceipts $receipts) {}

    /** @return array<string, mixed> */
    public function render(Parcel $parcel, string $kind, ?User $actor = null, ?TerminalP501 $terminal = null): array
    {
        abort_if($parcel->reference === null, 409, 'The parcel has no logistics reference.');
        $parcel->loadMissing(['corridor.originHub.stop', 'corridor.destinationHub.stop']);
        $route = $parcel->corridor?->originHub?->stop?->city.' > '.$parcel->corridor?->destinationHub?->stop?->city;
        $result = ['kind' => $kind, 'width_mm' => 58, 'reference' => $parcel->reference];
        if ($kind === 'label') {
            $trackingUrl = route('api.v1.parcels.tracking', ['reference' => $parcel->reference]);
            $result['qr_payload'] = $trackingUrl;
            $result['esc_pos_base64'] = $this->printable(['KORA COLIS', $parcel->reference, $route], $trackingUrl);
        } else {
            $receipt = $this->receipts->build($parcel, $kind);
            $result = $receipt + $result;
            $result['fiscal_status'] = $receipt['fiscal']['status'];
            $currency = $receipt['currency'];
            $lines = [
                'KORA COLIS',
                $receipt['receipt_status'] === 'certified' ? 'RECU FISCAL CERTIFIE' : 'RECU PROVISOIRE - NON CERTIFIE FISCALEMENT',
                $receipt['reference'], $route,
                'Exp. '.$parcel->sender_name, 'Dest. '.$parcel->recipient_name,
                $parcel->weight_grams.' g',
                'HT '.$receipt['charge']['net'].' '.$currency,
                'TVA '.$receipt['charge']['tax'].' '.$currency,
                'TTC '.$receipt['charge']['total'].' '.$currency,
            ];
            if ($receipt['fiscal']['certified']) {
                $lines[] = 'MECeF '.$receipt['fiscal']['code'];
                $result['fiscal_code'] = $receipt['fiscal']['code'];
                $result['fiscal_qr_payload'] = $receipt['fiscal']['qr_payload'];
            }
            $result['esc_pos_base64'] = $this->printable(
                $lines,
                (string) ($receipt['fiscal']['qr_payload'] ?? $receipt['qr_payload']),
            );
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
