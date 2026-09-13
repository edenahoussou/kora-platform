<?php

use App\Modules\IdentityAccess\Data\SystemRole;
use App\Modules\IdentityAccess\Data\TerminalP501Status;

describe('SystemRole enum', function (): void {
    it('has all eleven system roles', function (): void {
        expect(SystemRole::cases())->toHaveCount(11)
            ->and(array_column(SystemRole::cases(), 'value'))->toBe([
                'passenger',
                'bus_driver',
                'mechanic',
                'conductor',
                'agency_agent',
                'agency_manager',
                'operations_director',
                'commercial_director',
                'daf',
                'general_management',
                'system_admin',
            ]);
    });

    it('returns permissions for each role', function (): void {
        expect(SystemRole::Passenger->permissions())->toBe(['bookings.create', 'bookings.view-own', 'bookings.cancel', 'tickets.view', 'company-accounts.view'])
            ->and(SystemRole::BusDriver->permissions())->toBe(['departures.view'])
            ->and(SystemRole::Conductor->permissions())->toContain('boarding.scan', 'seat-releases.create')
            ->and(SystemRole::CommercialDirector->permissions())->toContain('agencies.view', 'agencies.create', 'agencies.update', 'agencies.deactivate')
            ->and(SystemRole::SystemAdmin->permissions())->toBe(SystemRole::allPermissions());
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

        expect($all)->toContain('agencies.view', 'agencies.create', 'agencies.update', 'agencies.deactivate')
            ->and($all)->toContain('bookings.create', 'boarding.scan', 'tickets.cancel', 'fiscalization.view')
            ->and($all)->toContain('network.view', 'network.create', 'network.update', 'network.deactivate')
            ->and($all)->toContain('cash.transfer.create', 'cash.transfer.approve', 'cash.media.view', 'cash.adjust')
            ->and($all)->toHaveCount(51)
            ->and(count(array_unique($all)))->toBe(51);
    });

    it('separates internal, field and management roles', function (): void {
        expect(SystemRole::internalRoles())
            ->toHaveCount(10)
            ->not->toContain(SystemRole::Passenger)
            ->and(SystemRole::fieldRoles())
            ->toBe([SystemRole::BusDriver, SystemRole::Mechanic, SystemRole::Conductor, SystemRole::AgencyAgent, SystemRole::AgencyManager])
            ->and(SystemRole::managementRoles())
            ->toBe([
                SystemRole::OperationsDirector,
                SystemRole::CommercialDirector,
                SystemRole::Daf,
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
            ->and(SystemRole::Mechanic->translatedLabel())->toBe('Mécanicien')
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
            ->and($options)->toHaveCount(11);
    });

    it('falls back to headline label when translation key is missing', function (): void {
        $status = TerminalP501Status::Lost;

        expect($status->label())->toBe('Lost')
            ->and($status->translatedLabel('fr'))->toBe('Perdu');
    });
});
