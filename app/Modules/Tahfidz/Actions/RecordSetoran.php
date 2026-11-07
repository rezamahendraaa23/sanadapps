<?php

namespace App\Modules\Tahfidz\Actions;

use App\Modules\Core\Models\Student;
use App\Modules\Tahfidz\Models\QuranSurah;
use App\Modules\Tahfidz\Models\TahfidzRecord;
use App\Modules\Tahfidz\Support\TahfidzException;

class RecordSetoran
{
    /**
     * Surat dan ayat divalidasi dari tabel referensi (THF-01). Rentang boleh melintasi surat, tetapi harus maju.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Student $student, array $data, int $teacherId): TahfidzRecord
    {
        if (! isset(TahfidzRecord::TYPES[$data['type'] ?? '']) || ! isset(TahfidzRecord::GRADES[$data['grade'] ?? ''])) {
            throw new TahfidzException('Pilih jenis setoran dan predikat.');
        }

        $from = QuranSurah::query()->find((int) ($data['surah_from'] ?? 0));
        $to = QuranSurah::query()->find((int) ($data['surah_to'] ?? 0));

        if ($from === null || $to === null) {
            throw new TahfidzException('Pilih surat.');
        }

        $ayahFrom = (int) ($data['ayah_from'] ?? 0);
        $ayahTo = (int) ($data['ayah_to'] ?? 0);

        if ($ayahFrom < 1 || $ayahFrom > $from->ayah_count) {
            throw new TahfidzException("Ayat awal harus 1 sampai {$from->ayah_count} untuk surat {$from->name_latin}.");
        }

        if ($ayahTo < 1 || $ayahTo > $to->ayah_count) {
            throw new TahfidzException("Ayat akhir harus 1 sampai {$to->ayah_count} untuk surat {$to->name_latin}.");
        }

        if ($to->number < $from->number || ($to->number === $from->number && $ayahTo < $ayahFrom)) {
            throw new TahfidzException('Akhir setoran tidak boleh sebelum awalnya.');
        }

        $pages = (float) ($data['pages'] ?? 0);

        if ($pages < 0 || $pages > 604) {
            throw new TahfidzException('Jumlah halaman tidak masuk akal.');
        }

        return TahfidzRecord::create([
            'student_id' => $student->id,
            'teacher_id' => $teacherId,
            'record_date' => $data['record_date'],
            'type' => $data['type'],
            'surah_from' => $from->number,
            'ayah_from' => $ayahFrom,
            'surah_to' => $to->number,
            'ayah_to' => $ayahTo,
            'pages' => $pages,
            'grade' => $data['grade'],
            'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
        ]);
    }
}
