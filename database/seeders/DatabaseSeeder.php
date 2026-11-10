<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            ModuleSeeder::class,
            RoleModuleSeeder::class,
            UserSeederRoles::class,
            AdminUserSeeder::class,
            AssignSuperAdminToExistingUserSeeder::class,
            RegionSeeder::class,
            ArtisanSeeder::class,
        ]);
    }
}
