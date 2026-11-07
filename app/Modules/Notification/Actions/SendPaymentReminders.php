<?php

namespace App\Modules\Notification\Actions;

use App\Modules\Core\Models\Guardian;
use App\Modules\Core\Models\Module;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Support\Rupiah;
use App\Support\SchoolDate;

class SendPaymentReminders
{
    /** Selisih hari terhadap jatuh tempo: H-3, H0, H+3, H+7 (FIN-12). */
    public const DEFAULT_OFFSETS = [-3, 0, 3, 7];

    public function __construct(private QueueNotification $notifications) {}

    /**
     * Job harian. Kunci dedupe menjaga satu pengingat per tagihan per tahap, walau job berjalan ulang.
     */
    public function handle(): int
    {
        $today = SchoolDate::today();
        $offsets = Module::query()->where('module_key', 'finance')->value('settings')['reminder_offsets'] ?? self::DEFAULT_OFFSETS;
        $count = 0;

        foreach ($offsets as $offset) {
            $due = $today->subDays((int) $offset);

            Invoice::query()->open()->whereNotNull('student_id')->with('student')->whereDate('due_date', $due->toDateString())
                ->each(function (Invoice $invoice) use ($offset, &$count): void {
                    $count += $this->remind($invoice, 'H'.$offset);
                });
        }

        return $count;
    }

    /**
     * @param  string  $stage  Penanda tahap untuk kunci dedupe.
     */
    public function remind(Invoice $invoice, string $stage): int
    {
        return $this->notifications->toGuardians(
            'payment_reminder',
            [$invoice->student],
            fn (Guardian $guardian) => [
                'nama_wali' => $guardian->name,
                'nama_santri' => $invoice->student->name,
                'periode' => $invoice->period?->translatedFormat('F Y') ?? $invoice->invoice_no,
                'sisa' => Rupiah::format($invoice->outstanding()),
                'jatuh_tempo' => $invoice->due_date->translatedFormat('j F Y'),
                'link' => route('wali.invoices.show', $invoice),
            ],
            'Pengingat tagihan '.$invoice->student->name,
            route('wali.invoices.show', $invoice),
            'reminder:'.$invoice->id.':'.$stage,
        );
    }
}
