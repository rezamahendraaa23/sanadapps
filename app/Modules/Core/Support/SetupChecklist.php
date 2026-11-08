<?php

namespace App\Modules\Core\Support;

use App\Modules\Core\Models\AcademicYear;
use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Models\Dormitory;
use App\Modules\Core\Models\Integration;
use App\Modules\Core\Models\Module;
use App\Modules\Core\Models\SchoolProfile;
use App\Modules\Core\Models\Student;
use App\Modules\Finance\Models\FeeRate;

/**
 * Status setup dihitung dari data nyata (PRD UI/UX 5.6), kecuali langkah yang boleh dilewati:
 * modul dan integrasi bisa ditandai selesai karena nilai bawaannya sudah sah.
 */
class SetupChecklist
{
    /**
     * @return list<array{key: string, label: string, hint: string, route: string, done: bool, can_mark: bool}>
     */
    public function steps(): array
    {
        $profile = SchoolProfile::current();
        $marked = (array) (Module::query()->where('module_key', 'core')->value('settings')['setup_marked'] ?? []);

        return [
            ['key' => 'profile', 'label' => 'Isi profil dan logo pesantren', 'hint' => 'Nama, alamat, logo, dan warna.', 'route' => 'staff.settings.profile', 'done' => filled($profile->name), 'can_mark' => false],
            ['key' => 'modules', 'label' => 'Pilih modul yang dipakai', 'hint' => 'Bisa diubah kapan saja.', 'route' => 'staff.settings.modules', 'done' => in_array('modules', $marked, true), 'can_mark' => true],
            ['key' => 'integrations', 'label' => 'Atur integrasi', 'hint' => 'Midtrans dan Fonnte, boleh dilewati dulu.', 'route' => 'staff.settings.integrations', 'done' => in_array('integrations', $marked, true) || Integration::query()->exists(), 'can_mark' => true],
            ['key' => 'year', 'label' => 'Buat tahun ajaran aktif', 'hint' => 'Tahun ajaran dan periode (semester) berjalan.', 'route' => 'staff.classrooms.index', 'done' => AcademicYear::query()->where('is_active', true)->exists(), 'can_mark' => false],
            ['key' => 'classes', 'label' => 'Buat kelas dan asrama', 'hint' => 'Dibutuhkan sebelum impor santri.', 'route' => 'staff.classrooms.index', 'done' => Classroom::query()->exists() && Dormitory::query()->exists(), 'can_mark' => false],
            ['key' => 'students', 'label' => 'Impor santri dan wali', 'hint' => 'Unduh template, isi, lalu unggah.', 'route' => 'staff.students.import', 'done' => Student::query()->exists(), 'can_mark' => false],
            ['key' => 'fees', 'label' => 'Atur jenis tagihan dan tarif', 'hint' => 'SPP, uang makan, dan lainnya.', 'route' => 'staff.settings.fee-types', 'done' => FeeRate::query()->exists(), 'can_mark' => false],
        ];
    }

    public function mark(string $key): void
    {
        abort_unless(in_array($key, ['modules', 'integrations'], true), 404);

        $core = Module::query()->firstOrCreate(['module_key' => 'core'], ['is_enabled' => true, 'settings' => []]);
        $settings = $core->settings ?? [];
        $settings['setup_marked'] = array_values(array_unique([...($settings['setup_marked'] ?? []), $key]));
        $core->update(['settings' => $settings]);
    }
}
