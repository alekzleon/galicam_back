<?php

namespace Tests\Feature;

use App\Models\Artisan;
use App\Models\Module;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    public function test_initial_seed_is_repeatable_and_contains_only_the_requested_data(): void
    {
        // Load the relevant real schema; older sales migrations require MySQL.
        $migrations = [
            '0001_01_01_000000_create_users_table.php',
            '2026_03_22_230515_add_username_to_users_table.php',
            '2026_03_27_040038_create_modules_table.php',
            '2026_03_27_040108_create_roles_table.php',
            '2026_03_27_040316_create_role_module_table.php',
            '2026_03_27_040342_add_role_id_to_users_table.php',
            '2026_08_05_000005_create_regions_tables.php',
            '2026_08_25_000001_create_artisans_tables.php',
            '2026_08_25_000003_add_translations_to_artisans_table.php',
        ];

        foreach ($migrations as $migration) {
            (require database_path('migrations/' . $migration))->up();
        }

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(5, Region::count());
        $this->assertSame(['brasil', 'colombia', 'espana', 'mexico', 'peru'], Region::orderBy('slug')->pluck('slug')->all());
        $this->assertSame(3, Artisan::count());
        $this->assertSame(5, User::count());
        $this->assertSame(7, Role::count());
        $this->assertFalse(User::whereHas('role', fn ($query) => $query->where('name', 'cliente'))->exists());

        $admin = User::where('email', 'admin@galicam.com')->firstOrFail();
        $this->assertSame('super_admin', $admin->role->name);
        $this->assertTrue(Hash::check('Password', $admin->password));

        foreach (['admin', 'super_admin'] as $roleName) {
            $this->assertEqualsCanonicalizing(
                Module::pluck('id')->all(),
                Role::where('name', $roleName)->firstOrFail()->modules()->pluck('modules.id')->all()
            );
        }

        // Catalog and sales tables are deliberately absent: seeding must not touch them.
        $this->assertSame(3, Artisan::whereHas('region')->count());
    }
}
