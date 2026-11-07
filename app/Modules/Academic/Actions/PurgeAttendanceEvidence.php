<?php

namespace App\Modules\Academic\Actions;

use App\Modules\Academic\Models\AttendanceRecord;
use App\Modules\Academic\Support\AcademicSettings;
use Illuminate\Support\Facades\Storage;

class PurgeAttendanceEvidence
{
    /**
     * Foto bukti terhapus setelah masa retensi; catatan kehadirannya tetap (PRD Sistem 8.9b).
     */
    public function handle(): int
    {
        $count = 0;

        AttendanceRecord::query()->whereNotNull('evidence_path')
            ->where('scanned_at', '<', now()->subDays(AcademicSettings::evidenceRetentionDays()))
            ->each(function (AttendanceRecord $record) use (&$count): void {
                Storage::disk('local')->delete($record->evidence_path);
                $record->update(['evidence_path' => null]);
                $count++;
            });

        return $count;
    }
}
