<?php

namespace App\Modules\Cash\Services;

use App\Models\User;
use App\Modules\Cash\Models\CashMovement;
use App\Modules\Cash\Models\CashSession;
use Illuminate\Database\Eloquent\Builder;

class CashJournalQuery
{
    public const CHANNELS = ['cash' => 'Espèces', 'mtn_momo' => 'MTN MoMo', 'moov_flooz' => 'Moov Money / Flooz', 'celtiis_cash' => 'Celtiis Cash'];

    public const TYPES = ['counter_sale' => 'Vente guichet', 'counter_sale_cash' => 'Vente guichet espèces', 'counter_sale_mobile_money' => 'Vente guichet Mobile Money', 'mobile_money_payment' => 'Paiement Mobile Money', 'cash_payment' => 'Paiement espèces', 'reversement' => 'Reversement', 'adjustment' => 'Ajustement'];

    public const STATUSES = ['posted' => 'Publié', 'pending' => 'En attente', 'approved' => 'Approuvé', 'rejected' => 'Rejeté', 'reversed' => 'Contrepassé'];

    public function session(User $user): ?CashSession
    {
        return CashSession::query()
            ->where('agent_id', $user->getKey())
            ->where('agency_id', $user->agency_id)
            ->whereDate('business_date', now()->toDateString())
            ->with(['agent', 'agency', 'ledgers' => fn ($query) => $query
                ->with(['agencyPaymentAccount', 'media'])
                ->withCount('movements')
                ->withSum(['movements as inflows' => fn ($movements) => $movements->whereIn('status', ['posted', 'approved'])->where('direction', 'in')], 'amount')
                ->withSum(['movements as outflows' => fn ($movements) => $movements->whereIn('status', ['posted', 'approved'])->where('direction', 'out')], 'amount')])
            ->latest('id')->first();
    }

    /**
     * @param  array{q: string, channel: string, status: string, direction: string}  $filters
     * @return Builder<CashMovement>
     */
    public function movements(?CashSession $session, array $filters): Builder
    {
        $query = CashMovement::query()->where('cash_session_id', $session?->getKey() ?? 0);
        if ($filters['q'] !== '') {
            $term = '%'.mb_strtolower($filters['q']).'%';
            $types = array_keys(array_filter(self::TYPES, fn (string $label): bool => str_contains(mb_strtolower($label), mb_strtolower($filters['q']))));
            $channels = array_keys(array_filter(self::CHANNELS, fn (string $label): bool => str_contains(mb_strtolower($label), mb_strtolower($filters['q']))));
            $query->where(function (Builder $query) use ($term, $types, $channels): void {
                $query->whereRaw('LOWER(reference) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(destination_label) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(type) LIKE ?', [$term])
                    ->orWhereIn('type', $types)
                    ->orWhereHas('cashLedger', function (Builder $ledger) use ($channels): void {
                        $ledger->whereHas('agencyPaymentAccount', fn (Builder $account) => $account->whereIn('network', $channels));
                        if (in_array('cash', $channels, true)) {
                            $ledger->orWhere('kind', 'cash');
                        }
                    });
            });
        }
        if ($filters['channel'] !== 'all') {
            $query->whereHas('cashLedger', function (Builder $ledger) use ($filters): void {
                if ($filters['channel'] === 'cash') {
                    $ledger->where('kind', 'cash');
                } else {
                    $ledger->whereHas('agencyPaymentAccount', fn (Builder $account) => $account->where('network', $filters['channel']));
                }
            });
        }
        foreach (['status', 'direction'] as $field) {
            if ($filters[$field] !== 'all') {
                $query->where($field, $filters[$field]);
            }
        }

        return $query;
    }
}
