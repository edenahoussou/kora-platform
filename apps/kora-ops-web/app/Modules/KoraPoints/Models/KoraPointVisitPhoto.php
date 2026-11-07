<?php

namespace App\Modules\KoraPoints\Models;

use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $visit_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 */
class KoraPointVisitPhoto extends KoraModel
{
    protected $table = 'kora_point_visit_photos';

    protected $fillable = ['visit_id', 'disk', 'path', 'original_name', 'mime_type', 'size'];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    /** @return BelongsTo<KoraPointVisit, $this> */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(KoraPointVisit::class, 'visit_id');
    }
}
