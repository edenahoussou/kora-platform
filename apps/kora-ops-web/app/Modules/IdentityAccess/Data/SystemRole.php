<?php

namespace App\Modules\IdentityAccess\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum SystemRole: string
{
    use HasEnumUtilities;

    case Passenger = 'passenger';
    case BusDriver = 'bus_driver';
    case Mechanic = 'mechanic';
    case Conductor = 'conductor';
    case AgencyAgent = 'agency_agent';
    case AgencyManager = 'agency_manager';
    case OperationsDirector = 'operations_director';
    case CommercialDirector = 'commercial_director';
    case Daf = 'daf';
    case GeneralManagement = 'general_management';
    case SystemAdmin = 'system_admin';

    /**
     * Permissions associées au rôle.
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Passenger => [
                'bookings.create',
                'bookings.view-own',
                'bookings.cancel',
                'tickets.view',
                'company-accounts.view',
            ],
            self::BusDriver => [
                'departures.view',
            ],
            self::Mechanic => ['departures.view'],
            self::Conductor => [
                'departures.view',
                'boarding.scan',
                'seat-releases.create',
                'seat-releases.view',
                'tickets.view',
            ],
            self::AgencyAgent => [
                'bookings.view',
                'company-accounts.view',
                'counter-sales.create',
                'cash.open',
                'cash.close',
                'cash.view',
                'cash.transfer.create',
                'cash.media.view',
                'departures.view',
                'tickets.issue',
                'tickets.view',
                'assisted-bookings.view-agency',
                'assisted-bookings.claim',
                'expenses.view-own',
                'expenses.create',
            ],
            self::AgencyManager => [
                'agencies.view',
                'fleet.view',
                'network.view',
                'bookings.view',
                'company-accounts.view',
                'cash.view',
                'cash.approve-variance',
                'cash.transfer.create',
                'cash.transfer.approve',
                'cash.media.view',
                'cash.adjust',
                'cash.open',
                'cash.close',
                'counter-sales.create',
                'departures.view',
                'terminals.view',
                'tickets.issue',
                'tickets.view',
                'assisted-bookings.view-agency',
                'assisted-bookings.claim',
                'assisted-bookings.manage-agency',
                'assisted-bookings.refuse',
                'expenses.view-agency',
                'expenses.approve-agency',
                'expenses.reverse',
                'expenses.export',
            ],
            self::OperationsDirector => [
                'tickets.view-network',
                'agencies.view',
                'fleet.view',
                'fleet.create',
                'fleet.update',
                'fleet.deactivate',
                'agencies.create',
                'agencies.update',
                'agencies.deactivate',
                'network.view',
                'network.create',
                'network.update',
                'network.deactivate',
                'bookings.view',
                'boarding.reverse',
                'cash.view',
                'departures.view',
                'departures.manage',
                'departures.cancel',
                'seat-releases.view',
                'terminals.view',
                'terminals.manage',
                'tickets.view',
                'assisted-bookings.view-network',
                'assisted-bookings.reassign-network',
                'assisted-bookings.refuse',
            ],
            self::CommercialDirector => [
                'tickets.view-network',
                'agencies.view',
                'fleet.view',
                'fleet.create',
                'fleet.update',
                'fleet.deactivate',
                'agencies.create',
                'agencies.update',
                'agencies.deactivate',
                'network.view',
                'network.create',
                'network.update',
                'network.deactivate',
                'bookings.view',
                'pricing.view',
                'pricing.manage',
                'group-discounts.view',
                'group-discounts.manage',
                'seat-releases.view',
                'tickets.view',
                'assisted-bookings.view-network',
                'assisted-bookings.reassign-network',
                'assisted-bookings.refuse',
            ],
            self::Daf => [
                'tickets.view-network',
                'bookings.view',
                'company-accounts.view',
                'company-accounts.manage',
                'company-credit.view',
                'company-credit.manage',
                'company-reports.export',
                'tickets.view',
                'fiscalization.view',
                'fiscalization.retry',
                'fiscalization.payload.view',
                'expenses.view-network',
                'expenses.approve-network',
                'expenses.reverse',
                'expenses.export',
            ],
            self::GeneralManagement => [
                'tickets.view-network',
                'agencies.view',
                'fleet.view',
                'fleet.create',
                'fleet.update',
                'fleet.deactivate',
                'agencies.create',
                'agencies.update',
                'agencies.deactivate',
                'network.view',
                'network.create',
                'network.update',
                'network.deactivate',
                'bookings.view',
                'cash.view',
                'departures.view',
                'fiscalization.view',
                'expenses.view-network',
                'expenses.export',
                'pricing.view',
                'seat-releases.view',
                'tickets.view',
                'assisted-bookings.view-network',
            ],
            self::SystemAdmin => self::allPermissions(),
        };
    }

    /**
     * Toutes les permissions du système.
     *
     * @return list<string>
     */
    public static function allPermissions(): array
    {
        return [
            'agencies.view',
            'agencies.create',
            'agencies.update',
            'agencies.deactivate',
            'fleet.view',
            'fleet.create',
            'fleet.update',
            'fleet.deactivate',
            'network.view',
            'network.create',
            'network.update',
            'network.deactivate',
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
            'cash.transfer.create',
            'cash.transfer.approve',
            'cash.media.view',
            'cash.adjust',
            'counter-sales.create',
            'departures.view',
            'departures.manage',
            'departures.cancel',
            'fiscalization.view',
            'fiscalization.retry',
            'fiscalization.credit',
            'fiscalization.payload.view',
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
            'tickets.view-network',
            'group-discounts.view',
            'group-discounts.manage',
            'company-accounts.view',
            'company-accounts.manage',
            'company-credit.view',
            'company-credit.manage',
            'company-reports.export',
            'assisted-bookings.view-agency',
            'assisted-bookings.claim',
            'assisted-bookings.manage-agency',
            'assisted-bookings.view-network',
            'assisted-bookings.reassign-network',
            'assisted-bookings.refuse',
            'expenses.view-own',
            'expenses.view-agency',
            'expenses.view-network',
            'expenses.create',
            'expenses.approve-agency',
            'expenses.approve-network',
            'expenses.reverse',
            'expenses.export',
        ];
    }

    /**
     * Rôles internes (non passager) — pour les écrans de gestion.
     *
     * @return list<self>
     */
    public static function internalRoles(): array
    {
        return array_values(array_filter(self::cases(), fn (self $role): bool => $role !== self::Passenger));
    }

    /**
     * Rôles terrain (chauffeur, convoyeur, agent d'agence) — pour KORA OPS Mobile.
     *
     * @return list<self>
     */
    public static function fieldRoles(): array
    {
        return [
            self::BusDriver,
            self::Mechanic,
            self::Conductor,
            self::AgencyAgent,
            self::AgencyManager,
        ];
    }

    /**
     * Rôles de direction — pour KORA OPS Web.
     *
     * @return list<self>
     */
    public static function managementRoles(): array
    {
        return [
            self::OperationsDirector,
            self::CommercialDirector,
            self::Daf,
            self::GeneralManagement,
        ];
    }
}
