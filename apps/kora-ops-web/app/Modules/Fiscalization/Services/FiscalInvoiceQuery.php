<?php

namespace App\Modules\Fiscalization\Services;

use App\Modules\Fiscalization\Data\FiscalInvoiceStatus;
use App\Modules\Fiscalization\Models\FiscalInvoice;
use Illuminate\Database\Eloquent\Builder;

class FiscalInvoiceQuery
{
    /**
     * @param  array{q: string, status: string, from: string, to: string}  $filters
     * @return Builder<FiscalInvoice>
     */
    public function build(array $filters): Builder
    {
        return $this->applyFilters(FiscalInvoice::query(), $filters)
            ->with([
                'reservation:id,uuid,reference,total_amount,currency',
                'payment:id,uuid,provider_reference,payer_name,payer_phone',
                'emecfInvoice:id,uid,type,reference,client_name,client_contact,total,status,code_mec_ef_dgi,finalized_at',
            ])
            ->withCount(['syncAttempts', 'creditNotes'])
            ->latest('created_at');
    }

    public function forUuid(string $uuid): FiscalInvoice
    {
        return FiscalInvoice::query()
            ->where('uuid', $uuid)
            ->with([
                'reservation',
                'payment',
                'emecfInvoice.items',
                'emecfInvoice.payments',
                'syncAttempts' => fn ($query) => $query->oldest('attempt_number'),
                'originalFiscalInvoice.emecfInvoice',
                'creditNotes.emecfInvoice.items',
                'requestedBy',
            ])
            ->firstOrFail();
    }

    /**
     * @param  array{q: string, status: string, from: string, to: string}  $filters
     * @return array<string, int>
     */
    public function counters(array $filters): array
    {
        $counts = $this->applyFilters(FiscalInvoice::query(), $filters)->toBase()
            ->selectRaw("count(*) as total, count(case when status = 'pending' then 1 end) as pending, count(case when status = 'certified' then 1 end) as certified, count(case when status = 'failed' then 1 end) as failed, count(case when status = 'canceled' then 1 end) as canceled")
            ->first();

        return [
            'total' => (int) ($counts->total ?? 0),
            'pending' => (int) ($counts->pending ?? 0),
            'certified' => (int) ($counts->certified ?? 0),
            'failed' => (int) ($counts->failed ?? 0),
            'canceled' => (int) ($counts->canceled ?? 0),
        ];
    }

    /** @return array<string, string> */
    public function statuses(): array
    {
        return collect(FiscalInvoiceStatus::cases())
            ->mapWithKeys(fn (FiscalInvoiceStatus $status): array => [$status->value => $status->label()])
            ->all();
    }

    /**
     * @param  Builder<FiscalInvoice>  $query
     * @param  array{q: string, status: string, from: string, to: string}  $filters
     * @return Builder<FiscalInvoice>
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['q'] !== '', function (Builder $query) use ($filters): void {
                $term = '%'.mb_strtolower((string) $filters['q']).'%';
                $query->where(function (Builder $query) use ($term): void {
                    $query->whereRaw('LOWER(kora_reference) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(mecef_uid) LIKE ?', [$term])
                        ->orWhereHas('reservation', fn (Builder $reservation) => $reservation
                            ->whereRaw('LOWER(CAST(uuid AS TEXT)) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(reference) LIKE ?', [$term]))
                        ->orWhereHas('payment', fn (Builder $payment) => $payment->whereRaw('LOWER(provider_reference) LIKE ?', [$term]))
                        ->orWhereHas('emecfInvoice', fn (Builder $emecf) => $emecf
                            ->whereRaw('LOWER(uid) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(code_mec_ef_dgi) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(client_name) LIKE ?', [$term]));
                });
            })
            ->when($filters['status'] !== 'all', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['from'] !== '', fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['from']))
            ->when($filters['to'] !== '', fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['to']));
    }
}
