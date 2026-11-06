<?php

namespace App\Modules\Core\Support;

use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Membaca dan memeriksa satu baris template import. Baris valid menghasilkan data siap simpan.
 */
final class StudentImportRow
{
    public const HEADINGS = ['nis', 'nama', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'alamat', 'kelas', 'asrama', 'tanggal_masuk', 'nama_wali', 'no_wa_wali', 'hubungan_wali'];

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $classrooms  nama kelas huruf kecil ke id
     * @param  array<string, int>  $dormitories  nama asrama huruf kecil ke id
     * @param  array<string, true>  $existingNis
     * @return array{student: array<string, mixed>, guardian: array<string, string>|null, errors: list<string>}
     */
    public static function parse(array $row, array $classrooms, array $dormitories, array $existingNis): array
    {
        $errors = [];
        $text = fn (string $key): ?string => filled($row[$key] ?? null) ? trim((string) $row[$key]) : null;

        $name = $text('nama');
        if ($name === null) {
            $errors[] = 'Nama wajib diisi.';
        }

        $gender = match (mb_strtoupper((string) $text('jenis_kelamin'))) {
            'L', 'LAKI-LAKI', 'LAKI LAKI', 'PUTRA' => 'L',
            'P', 'PEREMPUAN', 'PUTRI' => 'P',
            default => null,
        };
        if ($gender === null) {
            $errors[] = 'Jenis kelamin harus L atau P.';
        }

        $nis = $text('nis');
        if ($nis !== null && isset($existingNis[$nis])) {
            $errors[] = "NIS {$nis} sudah dipakai.";
        }

        $classroomId = null;
        if (($classroom = $text('kelas')) !== null) {
            $classroomId = $classrooms[mb_strtolower($classroom)] ?? null;
            if ($classroomId === null) {
                $errors[] = "Kelas \"{$classroom}\" belum ada. Buat dulu di Kelas & Asrama.";
            }
        }

        $dormitoryId = null;
        if (($dormitory = $text('asrama')) !== null) {
            $dormitoryId = $dormitories[mb_strtolower($dormitory)] ?? null;
            if ($dormitoryId === null) {
                $errors[] = "Asrama \"{$dormitory}\" belum ada. Buat dulu di Kelas & Asrama.";
            }
        }

        $birthDate = self::date($row['tanggal_lahir'] ?? null, 'Tanggal lahir', $errors);
        $entryDate = self::date($row['tanggal_masuk'] ?? null, 'Tanggal masuk', $errors);

        $guardian = null;
        if (($phoneRaw = $text('no_wa_wali')) !== null) {
            $phone = PhoneNumber::normalize($phoneRaw);
            if ($phone === null) {
                $errors[] = "Nomor WA wali \"{$phoneRaw}\" belum benar.";
            }

            $guardianName = $text('nama_wali');
            if ($guardianName === null) {
                $errors[] = 'Nama wali wajib diisi bila nomor WA diisi.';
            }

            $relation = mb_strtolower((string) ($text('hubungan_wali') ?? 'wali'));
            if (! in_array($relation, ['ayah', 'ibu', 'wali'], true)) {
                $errors[] = 'Hubungan wali harus ayah, ibu, atau wali.';
            }

            if ($phone !== null && $guardianName !== null) {
                $guardian = ['phone_wa' => $phone, 'name' => $guardianName, 'relation' => $relation];
            }
        }

        return [
            'student' => [
                'nis' => $nis,
                'name' => $name,
                'gender' => $gender,
                'birth_place' => $text('tempat_lahir'),
                'birth_date' => $birthDate,
                'address' => $text('alamat'),
                'classroom_id' => $classroomId,
                'dormitory_id' => $dormitoryId,
                'entry_date' => $entryDate,
            ],
            'guardian' => $guardian,
            'errors' => $errors,
        ];
    }

    /**
     * Excel bisa mengirim angka seri tanggal atau teks; keduanya diterima.
     *
     * @param  list<string>  $errors
     */
    private static function date(mixed $value, string $label, array &$errors): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return CarbonImmutable::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString();
            }

            $text = trim((string) $value);

            return preg_match('#^\d{1,2}/\d{1,2}/\d{4}$#', $text) === 1
                ? CarbonImmutable::createFromFormat('!d/m/Y', $text)->toDateString()
                : CarbonImmutable::parse($text)->toDateString();
        } catch (Throwable) {
            $errors[] = "{$label} \"{$value}\" belum benar. Gunakan format 31/12/2010.";

            return null;
        }
    }
}
