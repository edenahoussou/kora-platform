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
    case KoraPointAgent = 'kora_point_agent';
    case KoraPointSupervisor = 'kora_point_supervisor';
    case KoraPointTreasurer = 'kora_point_treasurer';

    public function category(): RoleCategory
    {
        return match ($this) {
            self::BusDriver, self::Mechanic, self::Conductor => RoleCategory::Terrain,
            self::KoraPointAgent, self::AgencyAgent, self::AgencyManager => RoleCategory::Agency,
            default => RoleCategory::Central,
        };
    }

    /**
     * Permissions associées au rôle.
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::KoraPointAgent => ['departures.view', 'kora-points.view', 'kora-points.remit', 'kora-points.refund-request', 'kora-points.visits.actions.update', 'counter-sales.create', 'cash.open', 'cash.close', 'cash.view', 'cash.media.view', 'tickets.view', 'tickets.issue', 'notifications.view'],
            self::KoraPointSupervisor => ['kora-points.view', 'kora-points.manage', 'kora-points.remit', 'kora-points.documents.upload', 'notifications.view'],
            self::KoraPointTreasurer => ['kora-points.view', 'kora-points.remit', 'kora-points.documents.upload', 'notifications.view'],
            self::Passenger => [
                'bookings.create',
                'bookings.view-own',
                'bookings.cancel',
                'tickets.view',
                'company-accounts.view',
                'company-portal.access',
            ],
            self::BusDriver => [
                'departures.progress',
                'incidents.report',
                'departures.view',
                'notifications.view',
            ],
            self::Mechanic => [
                'incidents.report',
                'departures.view',
                'notifications.view',
            ],
            self::Conductor => [
                'departures.progress',
                'incidents.report',
                'notifications.view',
                'departures.view',
                'boarding.scan',
                'seat-releases.create',
                'seat-releases.view',
                'tickets.view',
                'tracking.report',
            ],
            self::AgencyAgent => [
                'incidents.report',
                'notifications.view',
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
                'incidents.report',
                'notifications.view',
                'agencies.view',
                'fleet.view',
                'tracking.view-agency',
                'network.view',
                'bookings.view',
                'company-accounts.view',
                'cash.view',
                'cash.view-agency',
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
                'notifications.view',
                'tickets.view-network',
                'agencies.view',
                'fleet.view',
                'tracking.view-network',
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
                'cash.view-network',
                'departures.view',
                'departures.manage',
                'departures.cancel',
                'expenses.create',
                'seat-releases.view',
                'terminals.view',
                'terminals.manage',
                'tickets.view',
                'assisted-bookings.view-network',
                'assisted-bookings.reassign-network',
                'assisted-bookings.refuse',
                'passengers.erase',
                'sensitive-data.export',
                'profitability.view',
                'profitability.export',
            ],
            self::CommercialDirector => [
                'kora-points.view', 'kora-points.manage', 'kora-points.terms', 'kora-points.documents.upload', 'kora-points.refund-request',
                'notifications.view',
                'tickets.view-network',
                'agencies.view',
                'fleet.view',
                'tracking.view-network',
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
                'profitability.view',
                'profitability.export',
            ],
            self::Daf => [
                'kora-points.view', 'kora-points.finance', 'kora-points.refund-request',
                'notifications.view',
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
                'fiscalization.credit',
                'fiscalization.payload.view',
                'expenses.view-network',
                'expenses.create',
                'expenses.approve-network',
                'expenses.reverse',
                'expenses.export',
                'revenue.view',
                'revenue.export',
                'profitability.view',
                'profitability.export',
            ],
            self::GeneralManagement => [
                'kora-points.view',
                'notifications.view',
                'tickets.view-network',
                'agencies.view',
                'fleet.view',
                'tracking.view-network',
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
                'cash.view-network',
                'departures.view',
                'fiscalization.view',
                'expenses.view-network',
                'expenses.create',
                'expenses.export',
                'revenue.view',
                'revenue.export',
                'profitability.view',
                'profitability.export',
                'pricing.view',
                'seat-releases.view',
                'tickets.view',
                'assisted-bookings.view-network',
                'passengers.erase',
                'sensitive-data.export',
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
            'kora-points.view', 'kora-points.manage', 'kora-points.terms', 'kora-points.documents.upload', 'kora-points.remit', 'kora-points.refund-request', 'kora-points.finance', 'kora-points.visits.actions.update',
            'departures.progress',
            'incidents.report',
            'notifications.view',
            'agencies.view',
            'agencies.create',
            'agencies.update',
            'agencies.deactivate',
            'fleet.view',
            'tracking.view-agency',
            'tracking.view-network',
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
            'tracking.report',
            'cash.view',
            'cash.view-agency',
            'cash.view-network',
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
            'company-portal.access',
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
            'revenue.view',
            'revenue.export',
            'profitability.view',
            'profitability.export',
            'users.view',
            'users.manage',
            'passengers.erase',
            'roles.view',
            'roles.manage',
            'settings.general.view',
            'settings.general.manage',
            'settings.operations.view',
            'settings.operations.manage',
            'settings.integrations.view',
            'settings.integrations.manage',
            'monitoring.view',
            'app-releases.view',
            'app-releases.manage',
        ];
    }

    /**
     * Rôles internes (non passager) — pour les écrans de gestion.
     *
     * @return list<self>
     */
    public static function internalRoles(): array
    {
        return array_values(array_filter(self::cases(), fn (self $role): bool => ! in_array($role, [self::Passenger, self::KoraPointAgent, self::KoraPointSupervisor, self::KoraPointTreasurer], true)));
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
