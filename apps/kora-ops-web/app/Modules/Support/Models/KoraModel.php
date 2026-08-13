<?php

namespace App\Modules\Support\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

abstract class KoraModel extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id'];

    protected $hidden = ['id'];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Model $model): void {
            if (! $model->getAttribute('uuid')) {
                $model->setAttribute('uuid', (string) Str::uuid());
            }
        });
    }
}
