<?php

namespace App\Modules\Admission\Actions;

use App\Modules\Admission\Models\AdmissionPeriod;
use App\Modules\Admission\Models\Applicant;
use App\Modules\Admission\Models\ApplicantDocument;
use App\Modules\Admission\Support\AdmissionException;
use App\Modules\Core\Actions\RecordAudit;
use App\Modules\Notification\Actions\QueueNotification;
use Illuminate\Support\Facades\DB;

class AdmissionFlow
{
    public function __construct(private QueueNotification $notifications, private RecordAudit $audit) {}

    public function notify(Applicant $applicant, string $nextStep): void
    {
        $this->notifications->toPhone('ppdb_status', $applicant->guardian_phone, [
            'nama_calon' => (string) $applicant->name,
            'no_daftar' => $applicant->reg_no,
            'status' => Applicant::STATUSES[$applicant->publicStatus()],
            'langkah' => $nextStep,
            'link' => route('ppdb.status'),
        ], 'ppdb:'.$applicant->id.':'.$applicant->status);
    }

    public function reviewDocument(ApplicantDocument $document, string $status, ?string $note): void
    {
        if (! in_array($status, ['approved', 'rejected'], true)) {
            throw new AdmissionException('Keputusan dokumen tidak dikenal.');
        }

        if ($status === 'rejected' && blank($note)) {
            throw new AdmissionException('Isi alasan penolakan dokumen.');
        }

        $document->update(['status' => $status, 'note' => $status === 'rejected' ? trim((string) $note) : null]);
    }

    public function verify(Applicant $applicant): void
    {
        $this->transition($applicant, ['paid'], 'verified', function (Applicant $locked): void {
            $approved = $locked->documents()->where('status', 'approved')->pluck('type')->all();

            foreach (Applicant::REQUIRED_DOCUMENTS as $type) {
                if (! in_array($type, $approved, true)) {
                    throw new AdmissionException(Applicant::DOCUMENTS[$type].' belum disetujui.');
                }
            }
        });
        $this->notify($applicant->refresh(), 'Dokumen lengkap. Tunggu jadwal seleksi.');
    }

    public function startSelection(Applicant $applicant): void
    {
        $this->transition($applicant, ['verified'], 'in_selection');
        $this->notify($applicant->refresh(), 'Anda masuk tahap seleksi. Cek jadwal di halaman status.');
    }

    /**
     * Kuota dijaga dengan mengunci periode: dua panitia yang menerima bersamaan tidak bisa melebihi kuota (PRD Sistem 8.13).
     */
    public function decide(Applicant $applicant, string $decision, ?string $note = null): void
    {
        if (! in_array($decision, ['accepted', 'rejected', 'waitlist'], true)) {
            throw new AdmissionException('Keputusan tidak dikenal.');
        }

        DB::transaction(function () use ($applicant, $decision, $note): void {
            $period = AdmissionPeriod::query()->whereKey($applicant->period_id)->lockForUpdate()->firstOrFail();
            $locked = Applicant::query()->whereKey($applicant->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, ['in_selection', 'accepted', 'rejected', 'waitlist'], true)) {
                throw new AdmissionException('Pendaftar ini belum atau sudah melewati tahap seleksi.');
            }

            if ($decision === 'accepted') {
                if ($locked->total_score === null) {
                    throw new AdmissionException('Skor akhir belum lengkap.');
                }

                if ($locked->status !== 'accepted' && $period->acceptedCount() >= $period->quota) {
                    throw new AdmissionException('Kuota sudah penuh. Masukkan ke cadangan.');
                }
            }

            $before = $locked->status;
            // Bila periode sudah diumumkan, perubahan keputusan langsung terlihat dan diberitahukan.
            $locked->update(['status' => $decision, 'decision_note' => $note, 'announced_at' => $period->announced_at !== null ? now() : null]);
            $this->audit->handle('admission.decided', 'applicant', $locked->id, ['status' => $before], ['status' => $decision]);
        });

        if ($applicant->refresh()->announced_at !== null) {
            $this->notify($applicant, $this->nextStepFor($decision));
        }
    }

    /**
     * Mengisi kuota berurut skor; sisanya menjadi cadangan. Yang skornya belum lengkap dilewati.
     *
     * @return array{accepted: int, waitlist: int, skipped: int}
     */
    public function fillQuota(AdmissionPeriod $period): array
    {
        return DB::transaction(function () use ($period): array {
            $locked = AdmissionPeriod::query()->whereKey($period->id)->lockForUpdate()->firstOrFail();
            $seats = max(0, $locked->quota - $locked->acceptedCount());
            $summary = ['accepted' => 0, 'waitlist' => 0, 'skipped' => 0];

            $candidates = $locked->applicants()->where('status', 'in_selection')->orderByRaw('total_score DESC NULLS LAST')->orderBy('submitted_at')->lockForUpdate()->get();

            foreach ($candidates as $applicant) {
                if ($applicant->total_score === null) {
                    $summary['skipped']++;

                    continue;
                }

                $status = $seats > 0 ? 'accepted' : 'waitlist';
                $seats -= $status === 'accepted' ? 1 : 0;
                $applicant->update(['status' => $status]);
                $summary[$status]++;
            }

            return $summary;
        });
    }

    /**
     * Mengumumkan sekarang, atau menjadwalkan bila waktu diberikan (PPD-06).
     */
    public function announce(AdmissionPeriod $period, ?\DateTimeInterface $at = null): int
    {
        if ($at !== null && $at > now()) {
            $period->update(['announce_at' => $at]);

            return 0;
        }

        return $this->sendAnnouncement($period);
    }

    /**
     * Job berkala: pengumuman yang jadwalnya tiba.
     */
    public function announceDue(): int
    {
        $count = 0;

        foreach (AdmissionPeriod::query()->whereNull('announced_at')->whereNotNull('announce_at')->where('announce_at', '<=', now())->get() as $period) {
            $count += $this->sendAnnouncement($period);
        }

        return $count;
    }

    private function sendAnnouncement(AdmissionPeriod $period): int
    {
        $applicants = DB::transaction(function () use ($period): array {
            $locked = AdmissionPeriod::query()->whereKey($period->id)->lockForUpdate()->firstOrFail();
            $list = $locked->applicants()->whereIn('status', ['accepted', 'rejected', 'waitlist'])->whereNull('announced_at')->lockForUpdate()->get()->all();

            foreach ($list as $applicant) {
                $applicant->update(['announced_at' => now()]);
            }

            $locked->update(['announced_at' => $locked->announced_at ?? now(), 'announce_at' => $locked->announce_at ?? now()]);

            return $list;
        });

        foreach ($applicants as $applicant) {
            $this->notify($applicant, $this->nextStepFor($applicant->status));
        }

        $period->refresh();

        return count($applicants);
    }

    public function closeExpired(): int
    {
        return AdmissionPeriod::query()->where('status', 'open')->where('close_at', '<=', now())->update(['status' => 'closed']);
    }

    private function nextStepFor(string $status): string
    {
        return match ($status) {
            'accepted' => 'Selamat. Silakan daftar ulang ke panitia.',
            'waitlist' => 'Anda di daftar cadangan. Panitia akan menghubungi bila ada tempat.',
            default => 'Terima kasih sudah mendaftar.',
        };
    }

    /**
     * @param  list<string>  $from
     */
    private function transition(Applicant $applicant, array $from, string $to, ?callable $guard = null): void
    {
        DB::transaction(function () use ($applicant, $from, $to, $guard): void {
            $locked = Applicant::query()->whereKey($applicant->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, $from, true)) {
                throw new AdmissionException($locked->name.' berstatus '.Applicant::STATUSES[$locked->status].'.');
            }

            if ($guard !== null) {
                $guard($locked);
            }

            $locked->update(['status' => $to]);
        });
    }
}
