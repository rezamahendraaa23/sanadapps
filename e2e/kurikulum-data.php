<?php

/**
 * Data untuk e2e/kurikulum.spec.js, ditulis langsung ke database E2E.
 *   php e2e/kurikulum-data.php siapkan   -> kurikulum Ta'aruf, jalur Kitab dan Tahfidz, tiga kelas, tiga santri baru
 *   php e2e/kurikulum-data.php lulus     -> hasil fase Ta'aruf semua santri ditetapkan lulus
 */

use App\Modules\Academic\Actions\ManagePhases;
use App\Modules\Academic\Models\Curriculum;
use App\Modules\Academic\Models\PhaseResult;
use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Models\Module;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Support\ModuleRegistry;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$phases = app(ManagePhases::class);

if (($argv[1] ?? '') === 'siapkan') {
    Module::query()->whereIn('module_key', ['academic', 'curriculum'])->update(['is_enabled' => true]);
    app(ModuleRegistry::class)->forget();

    foreach ([['Program Ta\'aruf', 'Tarbiyatut Ta\'aruf', 'TRF'], ['Jalur Kitab', 'Kitab Dasar', 'KTB'], ['Jalur Tahfidz', 'Tahfidz Dasar', 'THF']] as [$curriculum, $phase, $code]) {
        $phases->savePhase(Curriculum::create(['name' => $curriculum]), ['name' => $phase, 'code' => $code, 'duration_weeks' => 12, 'min_score' => 70, 'has_certificate' => false]);
        Classroom::create(['name' => $phase === 'Tarbiyatut Ta\'aruf' ? 'Kelas 7 Ta\'aruf' : $phase.' 1']);
    }
    $room = Classroom::query()->where('name', 'Kelas 7 Ta\'aruf')->firstOrFail();
    foreach (['Fajar Kitab', 'Hasan Tahfidz', 'Zaid Tetap'] as $i => $name) {
        Student::create(['nis' => 'TRF'.($i + 1), 'name' => $name, 'gender' => 'L', 'classroom_id' => $room->id]);
    }
}

if (($argv[1] ?? '') === 'lulus') {
    $room = Classroom::query()->where('name', 'Kelas 7 Ta\'aruf')->firstOrFail();
    foreach ($room->students as $student) {
        PhaseResult::create(['student_id' => $student->id, 'phase_id' => $room->phase_id, 'classroom_id' => $room->id, 'started_on' => $room->phase_started_on, 'ended_on' => $room->phase_ends_on, 'final_score' => 85, 'status' => 'passed', 'decided_at' => now()]);
    }
}
