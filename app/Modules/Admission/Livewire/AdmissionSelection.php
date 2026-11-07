<?php

namespace App\Modules\Admission\Livewire;

use App\Modules\Admission\Actions\AdmissionFlow;
use App\Modules\Admission\Models\AdmissionPeriod;
use App\Modules\Admission\Models\Applicant;
use App\Modules\Admission\Support\AdmissionException;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Peringkat, keputusan massal, dan pengumuman (PD-02, PPD-06).
 */
#[Layout('components.layouts.staff', ['active' => 'ppdb-selection'])]
#[Title('Seleksi PPDB')]
class AdmissionSelection extends Component
{
    #[Url(as: 'periode', except: '')]
    public string $period = '';

    public string $announce_at = '';

    private function current(): ?AdmissionPeriod
    {
        return $this->period !== '' ? AdmissionPeriod::query()->find((int) $this->period) : AdmissionPeriod::query()->latest('id')->first();
    }

    private function manage(): void
    {
        abort_unless(auth()->user()->can('ppdb.manage'), 403);
    }

    public function decide(string $uuid, string $decision, AdmissionFlow $flow): void
    {
        $this->manage();

        try {
            $flow->decide(Applicant::query()->where('uuid', $uuid)->firstOrFail(), $decision);
        } catch (AdmissionException $exception) {
            $this->dispatch('toast', message: $exception->getMessage(), tone: 'danger');
        }
    }

    public function fillQuota(AdmissionFlow $flow): void
    {
        $this->manage();
        $period = $this->current();
        abort_if($period === null, 404);
        $summary = $flow->fillQuota($period);
        $this->dispatch('toast', message: "{$summary['accepted']} diterima, {$summary['waitlist']} cadangan".($summary['skipped'] ? ", {$summary['skipped']} dilewati karena skor belum lengkap." : '.'), tone: 'success');
    }

    public function announceNow(AdmissionFlow $flow): void
    {
        $this->manage();
        $period = $this->current();
        abort_if($period === null, 404);
        $count = $flow->announce($period);
        $this->dispatch('toast', message: "Hasil diumumkan ke {$count} pendaftar.", tone: 'success');
    }

    public function schedule(AdmissionFlow $flow): void
    {
        $this->manage();
        $this->resetErrorBag();
        $period = $this->current();
        abort_if($period === null, 404);
        $this->validate(['announce_at' => 'required|date'], ['announce_at.required' => 'Isi waktu pengumuman.']);
        $at = CarbonImmutable::parse($this->announce_at, config('sanad.display_timezone'))->utc();

        if ($at->isPast()) {
            $this->addError('announce_at', 'Waktu pengumuman harus di masa depan.');

            return;
        }

        $flow->announce($period, $at);
        $this->dispatch('toast', message: 'Pengumuman dijadwalkan.', tone: 'success');
    }

    public function render(): View
    {
        $period = $this->current();
        $rows = $period?->applicants()->whereIn('status', ['in_selection', 'accepted', 'waitlist', 'rejected'])->orderByRaw('total_score DESC NULLS LAST')->orderBy('submitted_at')->get() ?? collect();

        return view('admission.selection', [
            'current' => $period,
            'rows' => $rows,
            'periods' => AdmissionPeriod::query()->latest('id')->pluck('name', 'id')->all(),
            'accepted' => $period?->acceptedCount() ?? 0,
            'recipients' => $rows->whereIn('status', ['accepted', 'waitlist', 'rejected'])->whereNull('announced_at')->count(),
            'statuses' => Applicant::STATUSES,
            'canManage' => auth()->user()->can('ppdb.manage'),
        ]);
    }
}
