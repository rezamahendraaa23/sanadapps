<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Role dan izin Tahap 1 dari matriks PRD Sistem bagian 10. Izin modul lain ditambah di tahapnya.
 */
class RoleSeeder extends Seeder
{
    /** @var array<string, list<string>> */
    public const ROLES = [
        'admin' => ['panel.access', 'students.view-all', 'students.manage', 'guardians.manage', 'masters.manage', 'announcements.manage', 'imports.manage', 'users.manage', 'settings.manage', 'audit.view'],
        'bendahara' => ['panel.access', 'students.view-all'],
        'admin_akademik' => ['panel.access', 'students.view-all', 'students.manage', 'guardians.manage', 'masters.manage', 'announcements.manage', 'imports.manage'],
        'ustadz' => ['panel.access', 'students.view-own-class'],
        'musyrif' => ['panel.access', 'students.view-own-dormitory'],
        'petugas_uks' => ['panel.access', 'students.view-all'],
        'kasir' => ['panel.access'],
        'pengelola_merchant' => ['panel.access'],
        'penjaga_gerbang' => ['panel.access'],
        'panitia_ppdb' => ['panel.access'],
        'wali' => [],
        'santri' => [],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (array_unique(array_merge(...array_values(self::ROLES))) as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (self::ROLES as $role => $permissions) {
            Role::findOrCreate($role, 'web')->syncPermissions($permissions);
        }
    }
}
