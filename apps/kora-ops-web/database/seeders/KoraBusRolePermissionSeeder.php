<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class KoraBusRolePermissionSeeder extends Seeder
{
    /**
     * @var array<int, string>
     */
    private array $permissions = [
        'bookings.create',
        'bookings.view',
        'bookings.view-own',
        'bookings.cancel',
        'boarding.scan',
        'boarding.reverse',
        'cash.view',
        'cash.open',
        'cash.close',
        'cash.approve-variance',
        'counter-sales.create',
        'departures.view',
        'departures.manage',
        'departures.cancel',
        'fiscalization.view',
        'fiscalization.retry',
        'fiscalization.credit',
        'pricing.view',
        'pricing.manage',
        'seat-releases.create',
        'seat-releases.view',
        'sensitive-data.export',
        'terminals.view',
        'terminals.manage',
        'tickets.issue',
        'tickets.view',
        'tickets.cancel',
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private array $rolePermissions = [
        'passenger' => [
            'bookings.create',
            'bookings.view-own',
            'tickets.view',
        ],
        'bus_driver' => [
            'departures.view',
        ],
        'conductor' => [
            'departures.view',
            'boarding.scan',
            'seat-releases.create',
            'seat-releases.view',
            'tickets.view',
        ],
        'agency_agent' => [
            'bookings.view',
            'counter-sales.create',
            'cash.open',
            'cash.close',
            'departures.view',
            'tickets.issue',
            'tickets.view',
        ],
        'operations_director' => [
            'bookings.view',
            'boarding.reverse',
            'cash.view',
            'departures.view',
            'departures.manage',
            'departures.cancel',
            'seat-releases.view',
            'tickets.view',
        ],
        'commercial_director' => [
            'bookings.view',
            'pricing.view',
            'pricing.manage',
            'seat-releases.view',
            'tickets.view',
        ],
        'general_management' => [
            'bookings.view',
            'cash.view',
            'departures.view',
            'fiscalization.view',
            'pricing.view',
            'seat-releases.view',
            'tickets.view',
        ],
        'system_admin' => [],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissions as $permission) {
            Permission::query()->firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        foreach ($this->rolePermissions as $roleName => $permissions) {
            $role = Role::query()->firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            $role->syncPermissions(
                Permission::query()
                    ->whereIn('name', $roleName === 'system_admin' ? $this->permissions : $permissions)
                    ->where('guard_name', 'web')
                    ->get(),
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
