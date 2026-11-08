<?php

namespace App\Modules\Finance\Livewire\Reports;

use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Support\ModuleRegistry;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Support\ArrearsQuery;
use App\Modules\Finance\Support\Rupiah;
use App\Modules\Notification\Actions\QueueNotification;
use App\Modules\Notification\Actions\SendPaymentReminders;
use App\Support\SchoolDate;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.staff', ['active' => 'arrears'])]
#[Title('Tunggakan')]
class ArrearsReport extends Component
{
    #[Url(as: 'kelas', except: '')]
    public string $classroom = '';

    /** @var array{count: int, message: string}|null */
    #[Locked]
    public ?array $reminderPreview = null;

    /**
     * Menyiapkan pratinjau dan jumlah penerima sebelum mengirim (PRD UI/UX F-06).
     */
    public function prepareReminder(QueueNotification $notifications): void
    {
        abort_unless(auth()->user()->can('finance.manage'), 403);

        $invoices = $this->overdueInvoices();
        $first = $invoices->first();

        $this->reminderPreview = $first === null ? null : [
            'count' => $invoices->count(),
            'message' => (string) $notifications->renderBody('payment_reminder', [
                'nama_wali' => 'Bapak/Ibu',
                'nama_santri' => $first->student->name,
                'periode' => $first->period?->translatedFormat('F Y') ?? $first->invoice_no,
                'sisa' => Rupiah::format($first->outstanding()),
                'jatuh_tempo' => $first->due_date->translatedFormat('j F Y'),
                'link' => route('wali.invoices.show', $first),
            ]),
        ];

        $this->dispatch('open-modal', 'kirim-pengingat');
    }

    public function sendReminder(SendPaymentReminders $reminders): void
    {
        abort_unless(auth()->user()->can('finance.manage'), 403);

        $stage = 'manual:'.now(config('sanad.display_timezone'))->toDateString();
        $sent = $this->overdueInvoices()->sum(fn (Invoice $invoice) => $reminders->remind($invoice, $stage));

        $this->dispatch('close-modal', 'kirim-pengingat');
        $this->dispatch('toast', message: "{$sent} pengingat masuk antrean. Yang sudah dikirim hari ini tidak diulang.", tone: 'success');
    }

    /**
     * @return Collection<int, Invoice>
     */
    private function overdueInvoices(): Collection
    {
        return Invoice::query()
            ->with('student')
            ->open()
            ->whereNotNull('student_id')
            ->whereDate('due_date', '<', SchoolDate::today()->toDateString())
            ->when($this->classroom !== '', fn ($query) => $query->whereHas('student', fn ($student) => $student->where('classroom_id', (int) $this->classroom)))
            ->get();
    }

    public function render(): View
    {
        $rows = ArrearsQuery::rows($this->classroom === '' ? null : (int) $this->classroom);

        return view('finance.reports.arrears', [
            'rows' => $rows,
            'grandTotal' => $rows->sum('outstanding'),
            'waEnabled' => app(ModuleRegistry::class)->enabled('notification_wa'),
            'classrooms' => Classroom::query()->ordered()->pluck('name', 'id')->all(),
        ]);
    }
}
