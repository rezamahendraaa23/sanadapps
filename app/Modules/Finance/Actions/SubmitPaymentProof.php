<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\Payment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SubmitPaymentProof
{
    public function __construct(private CompressProofImage $compressor) {}

    /**
     * Unggah pertama dan unggah ulang setelah ditolak memakai pembayaran yang sama (PRD Sistem 8.6).
     */
    public function handle(Payment $payment, UploadedFile $proof): void
    {
        DB::transaction(function () use ($payment, $proof): void {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->method !== 'manual_transfer' || ! in_array($locked->status, ['pending', 'review', 'failed'], true)) {
                throw new FinanceException('Pembayaran ini tidak menerima bukti lagi.');
            }

            if ($locked->status === 'failed') {
                // Tagihan bisa saja sudah dibayar lewat cara lain setelah penolakan.
                foreach ($locked->invoices()->lockForUpdate()->get() as $invoice) {
                    if (! $invoice->isOpen()) {
                        throw new FinanceException("Tagihan {$invoice->invoice_no} sudah tidak perlu dibayar.");
                    }
                }
            }

            $old = $locked->proof_path;
            $path = $this->compressor->store($proof, 'payment-proofs');

            $locked->update(['proof_path' => $path, 'status' => 'review', 'reject_reason' => null]);

            if ($old) {
                Storage::disk('local')->delete($old);
            }
        });

        $payment->refresh();
    }
}
