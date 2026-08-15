<?php

namespace App\Modules\IdentityAccess\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum SystemRole: string
{
    use HasEnumUtilities;

    case Passenger = 'passenger';
    case BusDriver = 'bus_driver';
    case Conductor = 'conductor';
    case AgencyAgent = 'agency_agent';
    case OperationsDirector = 'operations_director';
    case CommercialDirector = 'commercial_director';
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
                'tickets.view',
            ],
            self::BusDriver => [
                'departures.view',
            ],
            self::Conductor => [
                'departures.view',
                'boarding.scan',
                'seat-releases.create',
                'seat-releases.view',
                'tickets.view',
            ],
            self::AgencyAgent => [
                'bookings.view',
                'counter-sales.create',
                'cash.open',
                'cash.close',
                'departures.view',
                'tickets.issue',
                'tickets.view',
            ],
            self::OperationsDirector => [
                'bookings.view',
                'boarding.reverse',
                'cash.view',
                'departures.view',
                'departures.manage',
                'departures.cancel',
                'seat-releases.view',
                'tickets.view',
            ],
            self::CommercialDirector => [
                'bookings.view',
                'pricing.view',
                'pricing.manage',
                'seat-releases.view',
                'tickets.view',
            ],
            self::GeneralManagement => [
                'bookings.view',
                'cash.view',
                'departures.view',
                'fiscalization.view',
                'pricing.view',
                'seat-releases.view',
                'tickets.view',
            ],
            self::SystemAdmin => [],
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
            self::Conductor,
            self::AgencyAgent,
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
            self::GeneralManagement,
        ];
    }
}
