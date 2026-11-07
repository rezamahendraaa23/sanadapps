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
        'admin' => ['panel.access', 'students.view-all', 'students.manage', 'guardians.manage', 'masters.manage', 'announcements.manage', 'imports.manage', 'users.manage', 'settings.manage', 'audit.view', 'finance.view', 'finance.manage', 'wallet.view', 'wallet.manage', 'cards.view', 'cards.manage', 'devices.manage', 'pos.view', 'pos.operate', 'pos.void', 'merchants.manage', 'payouts.approve', 'academic.manage', 'attendance.take', 'grades.manage', 'reports.publish', 'leave.manage', 'leave.decide', 'gate.operate', 'gate.override', 'tahfidz.input', 'tahfidz.manage', 'exams.manage'],
        'bendahara' => ['panel.access', 'students.view-all', 'finance.view', 'finance.manage', 'finance.verify', 'wallet.view', 'wallet.manage', 'cards.view', 'cards.manage', 'pos.view', 'payouts.approve'],
        'admin_akademik' => ['panel.access', 'students.view-all', 'students.manage', 'guardians.manage', 'masters.manage', 'announcements.manage', 'imports.manage', 'finance.view', 'wallet.view', 'cards.view', 'academic.manage', 'attendance.take', 'grades.manage', 'reports.publish', 'tahfidz.manage', 'exams.manage'],
        'ustadz' => ['panel.access', 'students.view-own-class', 'attendance.take', 'grades.input', 'tahfidz.input', 'exams.own'],
        'musyrif' => ['panel.access', 'students.view-own-dormitory', 'wallet.view', 'cards.view', 'attendance.take', 'leave.decide', 'gate.operate', 'gate.override', 'tahfidz.input'],
        'petugas_uks' => ['panel.access', 'students.view-all'],
        'kasir' => ['panel.access', 'pos.operate'],
        'pengelola_merchant' => ['panel.access', 'pos.operate', 'pos.void', 'merchants.own'],
        'penjaga_gerbang' => ['panel.access', 'gate.operate'],
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
