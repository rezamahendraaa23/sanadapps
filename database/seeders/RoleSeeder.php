<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Role dan izin dari matriks PRD Sistem bagian 10. Izin modul lain ditambah di tahapnya.
 */
class RoleSeeder extends Seeder
{
    /** @var array<string, list<string>> */
    public const ROLES = [
        'admin' => ['panel.access', 'students.view-all', 'students.manage', 'guardians.manage', 'masters.manage', 'announcements.manage', 'imports.manage', 'users.manage', 'settings.manage', 'audit.view', 'finance.view', 'finance.manage', 'wallet.view', 'wallet.manage', 'cards.view', 'cards.manage', 'devices.manage', 'pos.view', 'pos.operate', 'pos.void', 'merchants.manage'],
        'bendahara' => ['panel.access', 'students.view-all', 'finance.view', 'finance.manage', 'finance.verify', 'wallet.view', 'wallet.manage', 'cards.view', 'cards.manage', 'pos.view'],
        'admin_akademik' => ['panel.access', 'students.view-all', 'students.manage', 'guardians.manage', 'masters.manage', 'announcements.manage', 'imports.manage', 'finance.view', 'wallet.view', 'cards.view'],
        'ustadz' => ['panel.access', 'students.view-own-class'],
        'musyrif' => ['panel.access', 'students.view-own-dormitory', 'wallet.view', 'cards.view'],
        'petugas_uks' => ['panel.access', 'students.view-all'],
        'kasir' => ['panel.access', 'pos.operate'],
        'pengelola_merchant' => ['panel.access', 'pos.operate', 'pos.void'],
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
