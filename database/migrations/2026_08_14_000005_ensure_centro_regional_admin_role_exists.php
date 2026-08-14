<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('roles')->updateOrInsert(
            ['name' => 'centro_regional_admin'],
            [
                'display_name' => 'Centro Regional Admin',
                'description' => 'Administración limitada a los productos y datos de su centro regional',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $roleId = DB::table('roles')
            ->where('name', 'centro_regional_admin')
            ->value('id');

        $moduleIds = DB::table('modules')
            ->whereIn('name', ['dashboard', 'productos', 'regiones', 'pedidos'])
            ->pluck('id');

        foreach ($moduleIds as $moduleId) {
            DB::table('role_module')->updateOrInsert(
                [
                    'role_id' => $roleId,
                    'module_id' => $moduleId,
                ],
                [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        $roleId = DB::table('roles')
            ->where('name', 'centro_regional_admin')
            ->value('id');

        if (! $roleId) {
            return;
        }

        DB::table('role_module')
            ->where('role_id', $roleId)
            ->delete();

        DB::table('roles')
            ->where('id', $roleId)
            ->whereNotExists(function ($query) use ($roleId) {
                $query->selectRaw('1')
                    ->from('users')
                    ->where('users.role_id', $roleId);
            })
            ->delete();
    }
};
