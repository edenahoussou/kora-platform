<?php

namespace App\Modules\IdentityAccess\Models;

use App\Modules\IdentityAccess\Data\RoleCategory;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property bool $is_system
 * @property string|null $label
 * @property string|null $description
 * @property RoleCategory $category
 * @property bool $is_active
 */
class Role extends SpatieRole
{
    protected $fillable = [
        'name',
        'guard_name',
        'is_system',
        'label',
        'description',
        'category',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'category' => RoleCategory::class,
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'name';
    }
}
