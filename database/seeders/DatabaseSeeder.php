<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(SystemRoleSeeder::class);

        $tent = Tenant::withoutEvents(function () {
            return Tenant::firstOrCreate(
                ['email' => 'example@onivaco.com'],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => 'Example Tenant',
                    'domain' => 'demo',
                ],
            );
        });

        $admin = User::firstOrCreate(
            ['email' => 'admin@onivaco.com'],
            [
                'name' => 'Admin User',
                'tenant_id' => $tent->id,
                'password' => Hash::make('password'),
            ],
        );

        $cashier = User::firstOrCreate(
            ['email' => 'user@onivaco.com'],
            [
                'name' => 'Demo User',
                'tenant_id' => $tent->id,
                'password' => Hash::make('password'),
            ],
        );
        
        (new TenantRoleSeeder())->run();
        $this->call(UomSeeder::class);
        $admin->syncRoles(['admin']);
        $cashier->syncRoles(['cashier']);
    }
}
