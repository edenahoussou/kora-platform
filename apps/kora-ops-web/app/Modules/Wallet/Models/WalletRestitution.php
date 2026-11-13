<?php

namespace App\Modules\Wallet\Models;

use App\Models\User;
use App\Modules\Support\Models\KoraModel;
use App\Modules\Wallet\Data\RestitutionStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Restitution exceptionnelle : demande → approbation → versement tracé → débit.
 *
 * @property int $amount
 * @property RestitutionStatus $status
 * @property Carbon|null $approved_at
 * @property Carbon|null $paid_at
 */
class WalletRestitution extends KoraModel
{
    protected $fillable = [
        'uuid',
        'wallet_id',
        'amount',
        'status',
        'requested_by_user_id',
        'approved_by_user_id',
        'approved_at',
        'rejected_at',
        'paid_at',
        'rejection_reason',
        'payout_reference',
        'payout_channel',
        'note',
    ];

    /**
     * Wallet concerné par la restitution.
     *
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Montant déjà engagé par les restitutions en attente d'approbation ou de versement.
     *
     * @param  Wallet  $wallet  Wallet contrôlé
     * @param  WalletRestitution|null  $except  Restitution exclue du calcul
     */
    public static function reservedAmount(Wallet $wallet, ?self $except = null): int
    {
        return (int) self::query()
            ->where('wallet_id', $wallet->getKey())
            ->whereIn('status', [RestitutionStatus::Requested->value, RestitutionStatus::Approved->value])
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->sum('amount');
    }

    /**
     * Auteur de la demande.
     *
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /**
     * Responsable qui a validé ou rejeté la demande.
     *
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /**
     * Casts du statut et des horodatages.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => RestitutionStatus::class,
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }
}
