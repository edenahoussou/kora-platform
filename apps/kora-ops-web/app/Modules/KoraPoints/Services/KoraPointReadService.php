<?php

namespace App\Modules\KoraPoints\Services;

use App\Models\User;
use App\Modules\KoraPoints\Models\KoraPartnerDocument;
use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\KoraPoints\Models\KoraPointEntry;
use App\Modules\KoraPoints\Models\KoraPointRefund;
use App\Modules\KoraPoints\Models\KoraPointSale;
use Illuminate\Database\Eloquent\Builder;

class KoraPointReadService
{
    public function __construct(private readonly KoraPointAccess $access, private readonly KoraPointTermsService $terms) {}

    /** @return array<string, mixed> */
    public function index(User $actor): array
    {
        $this->access->authorize($actor, 'kora-points.view');

        return ['points' => $this->query($actor)->latest('id')->paginate(30)->through(fn (KoraPoint $point): array => $this->point($point, $actor)), 'defaults' => $actor->can('kora-points.terms') ? ($this->terms->defaults() + ['grace_days' => $this->terms->graceDays()]) : null];
    }

    /** @return array<string, mixed> */
    public function show(User $actor, KoraPoint $point): array
    {
        $this->access->requirePoint($actor, $point);

        $point = $this->query($actor)->whereKey($point->getKey())->firstOrFail();

        return [
            'point' => $this->point($point, $actor),
            'sales' => $this->access->sales($actor)->where('point_id', $point->getKey())->with(['reservation', 'point'])->latest('id')->paginate(30)->through(fn (KoraPointSale $sale): array => $this->sale($sale)),
            'entries' => KoraPointEntry::query()->whereIn('sale_id', $this->access->sales($actor)->where('point_id', $point->getKey())->select('id'))->latest('id')->paginate(30, ['*'], 'entries_page')->through(fn (KoraPointEntry $entry): array => ['uuid' => $entry->uuid, 'type' => $entry->type, 'amount' => $entry->amount, 'commission_delta' => $entry->commission_delta, 'recorded_at' => $entry->created_at?->toIso8601String()]),
            'refunds' => KoraPointRefund::query()->whereIn('sale_id', $this->access->sales($actor)->where('point_id', $point->getKey())->select('id'))->latest('id')->paginate(30, ['*'], 'refunds_page')->through(fn ($refund): array => ['uuid' => $refund->uuid, 'status' => $refund->status, 'amount' => $refund->amount, 'executor' => $refund->executor]),
            'documents' => $this->documents($actor, $point)->latest('id')->get()->map(fn (KoraPartnerDocument $document): array => $this->document($document))->all(),
        ];
    }

    /** @return Builder<KoraPoint> */
    public function query(User $actor): Builder
    {
        $saleIds = $this->access->sales($actor)->select('id');
        $sales = fn ($query) => $query->whereIn('id', $saleIds);
        $entries = fn ($query) => $query->whereIn('sale_id', $saleIds);
        $due = now()->subDays($this->terms->graceDays());

        return $this->access->points($actor)->with(['agency', 'organization'])
            ->withCount(['sales' => $sales])
            ->withSum(['sales as gross_total' => $sales], 'gross_amount')
            ->withSum(['sales as refunded_total' => $sales], 'refunded_amount')
            ->withSum(['entries as debt_total' => $entries], 'amount')
            ->withSum(['entries as commission_total' => $entries], 'commission_delta')
            ->withExists(['sales as overdue' => fn ($query) => $query->whereIn('id', $saleIds)->where('outstanding_amount', '>', 0)->where('due_at', '<=', $due)]);
    }

    /** @return array<string, mixed> */
    public function point(KoraPoint $point, User $actor): array
    {

        return [
            'uuid' => $point->uuid, 'code' => $point->agency->code, 'name' => $point->agency->name,
            'city' => $point->agency->city, 'country' => $point->agency->country, 'status' => $point->status,
            'offer' => 'voyage', 'organization_uuid' => $point->organization?->uuid,
            'responsible_name' => $point->responsible_name, 'phone' => $point->phone, 'address' => $point->address,
            'latitude' => $point->latitude, 'longitude' => $point->longitude,
            'terms' => $this->terms->resolve($point), 'outstanding_amount' => (int) $point->getAttribute('debt_total'),
            'earned_commission_amount' => (int) $point->getAttribute('commission_total'),
            'gross_sales_amount' => (int) $point->getAttribute('gross_total'),
            'refunded_amount' => (int) $point->getAttribute('refunded_total'),
            'sales_count' => (int) $point->getAttribute('sales_count'),
            'overdue' => (bool) $point->getAttribute('overdue'),
        ];
    }

    /** @return array<string, mixed> */
    public function sale(KoraPointSale $sale): array
    {
        return ['uuid' => $sale->uuid, 'point_uuid' => $sale->point->uuid, 'reservation_uuid' => $sale->reservation->uuid, 'status' => $sale->status, 'gross_amount' => $sale->gross_amount,
            'commission_amount' => $sale->commission_amount, 'commission_reversed' => $sale->commission_reversed,
            'net_amount' => $sale->net_amount, 'outstanding_amount' => $sale->outstanding_amount,
            'refunded_amount' => $sale->refunded_amount, 'terms' => $sale->terms_snapshot,
            'paid_at' => $sale->paid_at->toIso8601String(), 'due_at' => $sale->due_at->toIso8601String()];
    }

    /** @return Builder<KoraPartnerDocument> */
    private function documents(User $actor, KoraPoint $point): Builder
    {
        $saleIds = $this->access->sales($actor)->select('id');

        return KoraPartnerDocument::query()->where(function ($query) use ($point): void {
            $query->where('point_id', $point->getKey());
            if ($point->organization_id !== null) {
                $query->orWhere(fn ($shared) => $shared->where('organization_id', $point->organization_id)->whereNull('point_id')->whereNull('remittance_id')->whereNull('refund_id'));
            }
        })->where(function ($query) use ($saleIds): void {
            $query->where(fn ($ordinary) => $ordinary->whereNull('refund_id')->whereNull('remittance_id'))
                ->orWhereHas('refund', fn ($refund) => $refund->whereIn('sale_id', $saleIds))
                ->orWhereHas('remittance.lines', fn ($line) => $line->whereIn('sale_id', $saleIds));
        });
    }

    /** @return array<string, mixed> */
    public function document(KoraPartnerDocument $document): array
    {
        return ['uuid' => $document->uuid, 'kind' => $document->kind, 'title' => $document->title,
            'reference' => $document->reference, 'signed_at' => $document->signed_at?->toDateString(),
            'expires_at' => $document->expires_at?->toDateString(), 'download_url' => route('kora-points.documents.download', $document->uuid)];
    }
}
