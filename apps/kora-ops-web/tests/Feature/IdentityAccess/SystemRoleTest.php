<?php

use App\Modules\IdentityAccess\Data\SystemRole;
use App\Modules\IdentityAccess\Data\TerminalP501Status;

describe('SystemRole enum', function (): void {
    it('has all eight system roles', function (): void {
        expect(SystemRole::cases())->toHaveCount(8)
            ->and(array_column(SystemRole::cases(), 'value'))->toBe([
                'passenger',
                'bus_driver',
                'conductor',
                'agency_agent',
                'operations_director',
                'commercial_director',
                'general_management',
                'system_admin',
            ]);
    });

    it('returns permissions for each role', function (): void {
        expect(SystemRole::Passenger->permissions())->toBe(['bookings.create', 'bookings.view-own', 'tickets.view'])
            ->and(SystemRole::BusDriver->permissions())->toBe(['departures.view'])
            ->and(SystemRole::Conductor->permissions())->toContain('boarding.scan', 'seat-releases.create')
            ->and(SystemRole::SystemAdmin->permissions())->toBe([]);
    });

    it('differentiates bus_driver from conductor', function (): void {
        $driverPerms = SystemRole::BusDriver->permissions();
        $conductorPerms = SystemRole::Conductor->permissions();

        expect($driverPerms)->toBe(['departures.view'])
            ->and($conductorPerms)->not->toBe($driverPerms)
            ->and(count($conductorPerms))->toBeGreaterThan(count($driverPerms));
    });

    it('lists all permissions', function (): void {
        $all = SystemRole::allPermissions();

        expect($all)->toContain('bookings.create', 'boarding.scan', 'tickets.cancel', 'fiscalization.view')
            ->and($all)->toHaveCount(27)
            ->and(count(array_unique($all)))->toBe(27);
    });

    it('separates internal, field and management roles', function (): void {
        expect(SystemRole::internalRoles())
            ->toHaveCount(7)
            ->not->toContain(SystemRole::Passenger)
            ->and(SystemRole::fieldRoles())
            ->toBe([SystemRole::BusDriver, SystemRole::Conductor, SystemRole::AgencyAgent])
            ->and(SystemRole::managementRoles())
            ->toBe([
                SystemRole::OperationsDirector,
                SystemRole::CommercialDirector,
                SystemRole::GeneralManagement,
            ]);
    });

    it('generates correct translation keys', function (): void {
        expect(SystemRole::Passenger->translationKey())->toBe('system_role.passenger')
            ->and(SystemRole::BusDriver->translationKey())->toBe('system_role.bus_driver')
            ->and(SystemRole::SystemAdmin->translationKey())->toBe('system_role.system_admin');
    });

    it('returns translated labels in French', function (): void {
        app()->setLocale('fr');

        expect(SystemRole::Passenger->translatedLabel())->toBe('Passager')
            ->and(SystemRole::BusDriver->translatedLabel())->toBe('Chauffeur de bus')
            ->and(SystemRole::Conductor->translatedLabel())->toBe('Convoyeur')
            ->and(SystemRole::AgencyAgent->translatedLabel())->toBe("Agent d'agence")
            ->and(SystemRole::OperationsDirector->translatedLabel())->toBe('Directeur des opérations')
            ->and(SystemRole::SystemAdmin->translatedLabel())->toBe('Administrateur système');
    });

    it('returns translated labels in English', function (): void {
        expect(SystemRole::Passenger->translatedLabel('en'))->toBe('Passenger')
            ->and(SystemRole::BusDriver->translatedLabel('en'))->toBe('Bus Driver')
            ->and(SystemRole::Conductor->translatedLabel('en'))->toBe('Conductor')
            ->and(SystemRole::SystemAdmin->translatedLabel('en'))->toBe('System Administrator');
    });

    it('produces translated options array', function (): void {
        app()->setLocale('fr');

        $options = SystemRole::translatedOptions();

        expect($options)->toHaveKey('passenger')
            ->and($options['passenger'])->toBe('Passager')
            ->and($options['bus_driver'])->toBe('Chauffeur de bus')
            ->and($options)->toHaveCount(8);
    });

    it('falls back to headline label when translation key is missing', function (): void {
        $status = TerminalP501Status::Lost;

        expect($status->label())->toBe('Lost')
            ->and($status->translatedLabel('fr'))->toBe('Perdu');
    });
});
