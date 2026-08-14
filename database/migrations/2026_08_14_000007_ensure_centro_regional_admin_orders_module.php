<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')
            ->where('name', 'centro_regional_admin')
            ->value('id');

        $moduleId = DB::table('modules')
            ->where('name', 'pedidos')
            ->value('id');

        if (! $roleId || ! $moduleId) {
            return;
        }

        DB::table('role_module')->updateOrInsert(
            [
                'role_id' => $roleId,
                'module_id' => $moduleId,
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        $roleId = DB::table('roles')
            ->where('name', 'centro_regional_admin')
            ->value('id');

        $moduleId = DB::table('modules')
            ->where('name', 'pedidos')
            ->value('id');

        if (! $roleId || ! $moduleId) {
            return;
        }

        DB::table('role_module')
            ->where('role_id', $roleId)
            ->where('module_id', $moduleId)
            ->delete();
    }
};
