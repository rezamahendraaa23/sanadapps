<?php

namespace App\Modules\Admission\Listeners;

use App\Modules\Admission\Actions\AdmissionFlow;
use App\Modules\Admission\Models\Applicant;
use App\Modules\Finance\Events\PaymentSettled;
use Illuminate\Support\Facades\DB;

class MarkApplicantPaid
{
    public function __construct(private AdmissionFlow $flow) {}

    /**
     * Status pendaftar menjadi sudah bayar saat tagihannya lunas (PPD-04).
     */
    public function handle(PaymentSettled $event): void
    {
        $invoices = $event->payment->invoices()->whereNotNull('applicant_id')->get();

        foreach ($invoices as $invoice) {
            if ($invoice->fresh()->status !== 'paid') {
                continue;
            }

            $applicant = DB::transaction(function () use ($invoice): ?Applicant {
                $locked = Applicant::query()->whereKey($invoice->applicant_id)->lockForUpdate()->first();

                if ($locked === null || $locked->status !== 'submitted') {
                    return null;
                }

                $locked->update(['status' => 'paid']);

                return $locked;
            });

            if ($applicant !== null) {
                $this->flow->notify($applicant, 'Pembayaran diterima, dokumen sedang diperiksa.');
            }
        }
    }
}
