<?php

namespace App\Modules\Letters\Actions;

use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\User;
use App\Modules\Letters\Models\Letter;
use App\Modules\Letters\Models\Letterhead;
use App\Modules\Letters\Models\LetterTemplate;
use App\Modules\Letters\Support\LetterFields;
use App\Modules\Letters\Support\LetterSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Menerbitkan surat: nomor urut per tahun diambil dalam transaksi yang sama dengan arsip, jadi
 * tidak ada nomor ganda maupun nomor yang hilang. Surat yang dibatalkan tetap memegang nomornya.
 */
class IssueLetter
{
    /**
     * @param  array<string, string>  $extra
     */
    public function handle(LetterTemplate $template, ?Student $student, ?string $recipient, array $extra, string $issuedOn, string $signerName, string $signerTitle, ?Letterhead $letterhead, User $user, ?string $subject = null, ?string $body = null): Letter
    {
        if ($template->needs_student && $student === null) {
            throw new RuntimeException('Pilih santri untuk surat ini.');
        }

        if (trim($signerName) === '' || trim($signerTitle) === '') {
            throw new RuntimeException('Isi nama dan jabatan penandatangan.');
        }

        $settings = LetterSettings::all();
        $date = CarbonImmutable::parse($issuedOn, config('sanad.display_timezone'));
        $subjectText = trim((string) ($subject ?: $template->subject));
        $bodyText = trim((string) ($body ?: $template->body));

        // Cek isian kosong dengan nomor contoh sebelum mengambil nomor sungguhan.
        $preview = LetterFields::values($student, $date, 'X', $recipient, $signerName, $signerTitle, $settings['city'], $extra);
        $missing = LetterFields::missing($subjectText.' '.$bodyText, $preview);
        if ($missing !== []) {
            $labels = collect($template->fields)->pluck('label', 'key')->all() + LetterFields::BUILT_IN;
            throw new RuntimeException('Isian belum diisi: '.implode(', ', array_map(fn (string $key) => $labels[$key] ?? str_replace('_', ' ', $key), $missing)).'.');
        }

        return DB::transaction(function () use ($template, $student, $recipient, $extra, $date, $signerName, $signerTitle, $letterhead, $user, $settings, $subjectText, $bodyText): Letter {
            $sequence = (int) DB::selectOne(
                'INSERT INTO document_counters (key, value) VALUES (?, 1) ON CONFLICT (key) DO UPDATE SET value = document_counters.value + 1 RETURNING value',
                ['SURAT-'.$date->year],
            )->value;
            $number = LetterSettings::formatNumber($settings['number_format'], $sequence, (int) $settings['number_digits'], $template->code, $date->month, $date->year);
            $values = LetterFields::values($student, $date, $number, $recipient, $signerName, $signerTitle, $settings['city'], $extra);

            return Letter::create([
                'letter_no' => $number,
                'year' => $date->year,
                'sequence' => $sequence,
                'template_id' => $template->id,
                'letterhead_id' => ($letterhead ?? Letterhead::default())?->id,
                'student_id' => $student?->id,
                'recipient' => filled($recipient) ? trim((string) $recipient) : null,
                'subject' => LetterFields::fill($subjectText, $values),
                'body' => LetterFields::fill($bodyText, $values),
                'data' => $extra,
                'issued_on' => $date->toDateString(),
                'signer_name' => trim($signerName),
                'signer_title' => trim($signerTitle),
                'created_by' => $user->id,
            ]);
        });
    }

    public function void(Letter $letter, string $reason): void
    {
        if (trim($reason) === '') {
            throw new RuntimeException('Alasan pembatalan wajib diisi.');
        }

        $letter->update(['status' => 'void', 'void_reason' => trim($reason)]);
    }

    /**
     * Nomor berikutnya untuk pratinjau; tidak mengambil nomor.
     */
    public static function nextPreview(string $code, ?string $date = null): string
    {
        $settings = LetterSettings::all();
        $day = CarbonImmutable::parse($date ?? now(config('sanad.display_timezone'))->toDateString(), config('sanad.display_timezone'));
        $current = (int) DB::table('document_counters')->where('key', 'SURAT-'.$day->year)->value('value');

        return LetterSettings::formatNumber($settings['number_format'], $current + 1, (int) $settings['number_digits'], $code, $day->month, $day->year);
    }
}
