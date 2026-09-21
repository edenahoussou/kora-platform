<?php

namespace App\Modules\IdentityAccess\Http\Requests;

use App\Modules\IdentityAccess\Data\PermissionCatalog;
use App\Modules\IdentityAccess\Data\RoleCategory;
use App\Modules\IdentityAccess\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Role::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category' => ['required', Rule::enum(RoleCategory::class)],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::in(PermissionCatalog::codes())],
        ];
    }

    /**
     * @return array{label: string, description: string|null, category: string, permissions: list<string>}
     */
    public function payload(): array
    {
        return [
            'label' => $this->string('label')->trim()->value(),
            'description' => $this->string('description')->trim()->value() ?: null,
            'category' => $this->string('category')->trim()->value(),
            'permissions' => array_values(array_unique(array_map('strval', (array) $this->input('permissions', [])))),
        ];
    }
}
