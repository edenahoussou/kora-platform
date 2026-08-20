<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\IdentityAccess\Data\SystemRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Crée des utilisateurs de démonstration pour chaque rôle système.
 *
 * - Passagers (app passager) : PIN + OTP, pas de mot de passe web
 * - Rôles terrain (KORA OPS Mobile) : chauffeur, convoyeur, agent d'agence
 * - Rôles de direction (KORA OPS Web) : ops, commercial, direction générale
 * - Admin système
 *
 * Tous les mots de passe sont "password" et les PIN sont "1234".
 */
class KoraDemoUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedPassengers();
        $this->seedFieldStaff();
        $this->seedManagement();
        $this->seedSystemAdmin();
    }

    /**
     * Crée un user sans factory (compatible prod sans Faker).
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createUser(array $overrides): User
    {
        $defaults = [
            'uuid' => (string) Str::uuid(),
            'country_code' => '+229',
            'status' => 'active',
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];

        return User::create(array_merge($defaults, $overrides));
    }

    /**
     * Passagers — pour les tests de l'app passager (OTP + PIN).
     */
    private function seedPassengers(): void
    {
        $passengers = [
            ['name' => 'Koffi Adjovi', 'email' => 'passenger.koffi@kora.demo', 'phone' => '+22901000001'],
            ['name' => 'Amina Diallo', 'email' => 'passenger.amina@kora.demo', 'phone' => '+22901000002'],
            ['name' => 'Yao Mensah', 'email' => 'passenger.yao@kora.demo', 'phone' => '+22901000003'],
            ['name' => 'Fatou Bello', 'email' => 'passenger.fatou@kora.demo', 'phone' => '+22901000004'],
            ['name' => 'Kossi Agbodjan', 'email' => 'passenger.kossi@kora.demo', 'phone' => '+22901000005'],
        ];

        foreach ($passengers as $data) {
            $user = $this->createUser([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'pin_hash' => Hash::make('1234'),
                'password' => Hash::make('password'),
            ]);
            $user->assignRole(SystemRole::Passenger->value);
        }
    }

    /**
     * Rôles terrain — pour les tests de KORA OPS Mobile (P501).
     */
    private function seedFieldStaff(): void
    {
        $fieldStaff = [
            [
                'role' => SystemRole::BusDriver,
                'name' => 'Jean-Baptiste Houngbo',
                'email' => 'driver.jb@kora.demo',
                'phone' => '+22902000001',
            ],
            [
                'role' => SystemRole::BusDriver,
                'name' => 'Paulin Zinsou',
                'email' => 'driver.paulin@kora.demo',
                'phone' => '+22902000002',
            ],
            [
                'role' => SystemRole::Conductor,
                'name' => 'Marc Adjagba',
                'email' => 'conductor.marc@kora.demo',
                'phone' => '+22902000003',
            ],
            [
                'role' => SystemRole::Conductor,
                'name' => 'Luc Sossou',
                'email' => 'conductor.luc@kora.demo',
                'phone' => '+22902000004',
            ],
            [
                'role' => SystemRole::AgencyAgent,
                'name' => 'Sandrine Akpovi',
                'email' => 'agent.sandrine@kora.demo',
                'phone' => '+22902000005',
            ],
            [
                'role' => SystemRole::AgencyAgent,
                'name' => 'Brice Hounkpatin',
                'email' => 'agent.brice@kora.demo',
                'phone' => '+22902000006',
            ],
            [
                'role' => SystemRole::AgencyManager,
                'name' => 'Adjoua Kpékpassi',
                'email' => 'manager.adjoua@kora.demo',
                'phone' => '+22902000007',
            ],
        ];

        foreach ($fieldStaff as $data) {
            $user = $this->createUser([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'pin_hash' => Hash::make('1234'),
                'password' => Hash::make('password'),
            ]);
            $user->assignRole($data['role']->value);
        }
    }

    /**
     * Rôles de direction — pour les tests de KORA OPS Web.
     */
    private function seedManagement(): void
    {
        $management = [
            [
                'role' => SystemRole::OperationsDirector,
                'name' => 'Eden Ahoussou',
                'email' => 'ops.director@kora.demo',
                'phone' => '+22903000001',
            ],
            [
                'role' => SystemRole::CommercialDirector,
                'name' => 'Nadège Kpakpo',
                'email' => 'commercial.director@kora.demo',
                'phone' => '+22903000002',
            ],
            [
                'role' => SystemRole::GeneralManagement,
                'name' => 'Robert Gbénou',
                'email' => 'general.management@kora.demo',
                'phone' => '+22903000003',
            ],
        ];

        foreach ($management as $data) {
            $user = $this->createUser([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => Hash::make('password'),
            ]);
            $user->assignRole($data['role']->value);
        }
    }

    /**
     * Admin système — accès complet.
     */
    private function seedSystemAdmin(): void
    {
        $admin = $this->createUser([
            'name' => 'System Administrator',
            'email' => 'admin@kora.demo',
            'phone' => '+22909000001',
            'password' => Hash::make('password'),
        ]);
        $admin->assignRole(SystemRole::SystemAdmin->value);
    }
}
