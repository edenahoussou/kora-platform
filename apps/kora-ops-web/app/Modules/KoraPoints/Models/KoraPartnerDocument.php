<?php

namespace App\Modules\KoraPoints\Models;

use App\Modules\Support\Models\KoraModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int|null $point_id
 * @property int|null $organization_id
 * @property CarbonImmutable|null $signed_at
 * @property CarbonImmutable|null $expires_at
 */
class KoraPartnerDocument extends KoraModel implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'kora_partner_documents';

    protected $fillable = ['point_id', 'organization_id', 'uploaded_by', 'remittance_id', 'refund_id', 'kind', 'title', 'reference', 'signed_at', 'expires_at', 'uuid'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['signed_at' => 'immutable_date', 'expires_at' => 'immutable_date'];
    }

    /** @return BelongsTo<KoraPoint, $this> */
    public function point(): BelongsTo
    {
        return $this->belongsTo(KoraPoint::class, 'point_id');
    }

    /** @return BelongsTo<KoraPointOrganization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(KoraPointOrganization::class, 'organization_id');
    }

    /** @return BelongsTo<KoraPointRefund, $this> */
    public function refund(): BelongsTo
    {
        return $this->belongsTo(KoraPointRefund::class, 'refund_id');
    }

    /** @return BelongsTo<KoraPointRemittance, $this> */
    public function remittance(): BelongsTo
    {
        return $this->belongsTo(KoraPointRemittance::class, 'remittance_id');
    }

    /** Declares the single-file proof media collection. */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('proof')->useDisk('local')->singleFile();
    }

    /** Forbids deleting a partner document. */
    protected static function booted(): void
    {
        static::deleting(fn () => throw new \LogicException('Une pièce partenaire ne peut pas être supprimée.'));
    }
}
