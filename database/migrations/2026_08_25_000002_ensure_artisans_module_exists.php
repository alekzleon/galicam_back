<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('modules') || ! DB::getSchemaBuilder()->hasTable('roles') || ! DB::getSchemaBuilder()->hasTable('role_module')) {
            return;
        }

        $now = Carbon::now();

        DB::table('modules')->updateOrInsert(
            ['name' => 'artesanos'],
            [
                'display_name' => 'Artesanos',
                'description' => 'Administración de artesanos, su región, historia, contacto y productos asignados',
                'group_key' => 'catalogo',
                'sort_order' => 36,
                'route_name' => 'admin.artisans.index',
                'icon' => 'fa-solid fa-hands',
                'is_active' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        $moduleId = DB::table('modules')->where('name', 'artesanos')->value('id');

        if (! $moduleId) {
            return;
        }

        $roleNames = ['super_admin', 'admin', 'sistemas'];
        $roleIds = DB::table('roles')->whereIn('name', $roleNames)->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('role_module')->updateOrInsert(
                ['role_id' => $roleId, 'module_id' => $moduleId],
                ['created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('modules') || ! DB::getSchemaBuilder()->hasTable('role_module')) {
            return;
        }

        $moduleId = DB::table('modules')->where('name', 'artesanos')->value('id');

        if ($moduleId) {
            DB::table('role_module')->where('module_id', $moduleId)->delete();
            DB::table('modules')->where('id', $moduleId)->delete();
        }
    }
};
