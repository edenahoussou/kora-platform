<?php

namespace App\Modules\KoraPoints\Services;

use App\Models\User;
use App\Modules\KoraPoints\Models\KoraPartnerDocument;
use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\KoraPoints\Models\KoraPointRemittance;
use App\Modules\KoraPoints\Models\KoraPointRemittanceLine;
use App\Modules\KoraPoints\Models\KoraPointSale;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KoraPointRemittanceService
{
    public function __construct(private readonly KoraPointAccess $access, private readonly KoraPointLedgerService $ledger) {}

    /** @param list<string> $saleUuids */
    /**
     * Groups eligible outstanding sales into a draft remittance slip with claimed lines.
     *
     * @param  list<string>  $saleUuids
     */
    public function create(User $actor, array $saleUuids, string $method, string $reference): KoraPointRemittance
    {
        $this->access->authorize($actor, 'kora-points.remit');

        return DB::transaction(function () use ($actor, $saleUuids, $method, $reference): KoraPointRemittance {
            $ids = KoraPointSale::query()->whereIn('uuid', $saleUuids)->orderBy('point_id')->get();
            KoraPoint::query()->whereIn('id', $ids->pluck('point_id'))->orderBy('id')->lockForUpdate()->get();
            $sales = KoraPointSale::query()->whereIn('uuid', $saleUuids)->orderBy('id')->lockForUpdate()->get();
            if ($sales->isEmpty() || $sales->count() !== count(array_unique($saleUuids))) {
                throw ValidationException::withMessages(['sale_uuids' => 'Sélection de ventes invalide.']);
            }
            $organizationIds = $sales->pluck('organization_id')->unique();
            if ($organizationIds->count() !== 1 || ($organizationIds->first() === null && $sales->pluck('point_id')->unique()->count() !== 1)) {
                throw ValidationException::withMessages(['sale_uuids' => 'Un bordereau concerne une organisation ou un seul Point autonome.']);
            }
            foreach ($sales as $sale) {
                $this->access->declare($actor, $sale);
                if ($sale->outstanding_amount <= 0 || $sale->refunds()->whereNotIn('status', ['confirmed', 'rejected'])->exists() || KoraPointRemittanceLine::query()->where('claimed_sale_id', $sale->getKey())->exists()) {
                    throw ValidationException::withMessages(['sale_uuids' => 'Une vente est déjà affectée, remboursée ou sans encours à régler.']);
                }
            }
            $remittance = KoraPointRemittance::query()->create([
                'organization_id' => $organizationIds->first(), 'declared_by' => $actor->getKey(), 'status' => 'draft',
                'amount' => $sales->sum('outstanding_amount'), 'method' => $method, 'reference' => $reference,
            ]);
            foreach ($sales as $sale) {
                $remittance->lines()->create(['sale_id' => $sale->getKey(), 'claimed_sale_id' => $sale->getKey(), 'amount' => $sale->outstanding_amount]);
            }
            $this->audit($actor, $remittance, 'remittance.created');

            return $remittance;
        }, 3);
    }

    /**
     * Submits a draft remittance for finance control once its proof document is attached.
     */
    public function submit(User $actor, KoraPointRemittance $remittance): void
    {
        DB::transaction(function () use ($actor, $remittance): void {
            $locked = KoraPointRemittance::query()->lockForUpdate()->whereKey($remittance->getKey())->firstOrFail();
            $this->access->authorize($actor, 'kora-points.remit');
            $this->requireView($actor, $locked);
            if ($locked->declared_by !== $actor->getKey()) {
                throw new AuthorizationException;
            }
            if ($locked->status === 'submitted') {
                return;
            }
            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages(['remittance' => 'Ce bordereau ne peut plus être soumis.']);
            }
            if (! KoraPartnerDocument::query()->where('remittance_id', $locked->getKey())->where('kind', 'remittance_proof')->whereHas('media')->exists()) {
                throw ValidationException::withMessages(['proof' => 'Joignez une preuve de reversement avant soumission.']);
            }
            $locked->update(['status' => 'submitted', 'submitted_at' => now()]);
            $this->audit($actor, $locked, 'remittance.submitted');
        });
    }

    /**
     * Finance reception: validates exact amounts, flags discrepancies for reconciliation, or rejects.
     */
    public function receive(User $actor, KoraPointRemittance $remittance, int $received, bool $reject = false, ?string $note = null): void
    {
        $this->access->authorize($actor, 'kora-points.finance');
        DB::transaction(function () use ($actor, $remittance, $received, $reject, $note): void {
            $locked = KoraPointRemittance::query()->lockForUpdate()->whereKey($remittance->getKey())->firstOrFail();
            if ($actor->getKey() === $locked->declared_by) {
                throw new AuthorizationException('Le déclarant ne peut pas valider son reversement.');
            }
            if ($locked->status === 'validated' && ! $reject && $locked->received_amount === $received) {
                return;
            }
            if (! in_array($locked->status, ['draft', 'submitted', 'reconciliation'], true) || ($locked->status === 'draft' && ! $reject)) {
                throw ValidationException::withMessages(['remittance' => 'État incompatible avec la réception.']);
            }
            $lines = $locked->lines()->orderBy('sale_id')->get();
            $pointIds = KoraPointSale::query()->whereIn('id', $lines->pluck('sale_id'))->pluck('point_id');
            KoraPoint::query()->whereIn('id', $pointIds)->orderBy('id')->lockForUpdate()->get();
            if ($reject) {
                $locked->update(['status' => 'rejected', 'note' => $note]);
                $locked->lines()->update(['claimed_sale_id' => null]);
            } elseif ($received !== $locked->amount) {
                $locked->update(['status' => 'reconciliation', 'received_amount' => $received, 'note' => $note]);
            } else {
                foreach ($lines as $line) {
                    $sale = KoraPointSale::query()->lockForUpdate()->whereKey($line->sale_id)->firstOrFail();
                    if ($sale->outstanding_amount !== $line->amount) {
                        throw ValidationException::withMessages(['remittance' => 'Le solde de la vente a changé ; rapprochez le bordereau.']);
                    }
                    $this->ledger->post($sale, $actor, 'remittance:'.$line->uuid, 'remittance', -$line->amount);
                    $sale->update(['settled_at' => now(), 'status' => 'settled']);
                }
                $locked->lines()->update(['claimed_sale_id' => null]);
                $locked->update(['status' => 'validated', 'received_amount' => $received, 'validated_by' => $actor->getKey(), 'validated_at' => now(), 'note' => $note]);
            }
            $this->audit($actor, $locked, 'remittance.'.$locked->status);
        }, 3);
    }

    /**
     * Throws an authorization error when any covered sale is outside the actor visibility scope.
     */
    public function requireView(User $actor, KoraPointRemittance $remittance): void
    {
        $lines = $remittance->lines()->with('sale.point')->get();
        if ($lines->isEmpty()) {
            throw new AuthorizationException;
        }
        foreach ($lines as $line) {
            $this->access->requireSale($actor, $line->sale);
        }
    }

    /**
     * Writes an audit activity entry for a remittance lifecycle event.
     */
    private function audit(User $actor, KoraPointRemittance $remittance, string $event): void
    {
        activity('kora-points')->performedOn($remittance)->causedBy($actor)->withProperties(['amount' => $remittance->amount, 'received_amount' => $remittance->received_amount])->log($event);
    }
}
