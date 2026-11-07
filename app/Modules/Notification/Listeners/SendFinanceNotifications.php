<?php

namespace App\Modules\Notification\Listeners;

use App\Modules\Core\Models\Guardian;
use App\Modules\Core\Models\User;
use App\Modules\Finance\Events\InvoiceIssued;
use App\Modules\Finance\Events\PaymentClosed;
use App\Modules\Finance\Events\PaymentSettled;
use App\Modules\Finance\Events\ProofRejected;
use App\Modules\Finance\Events\ProofSubmitted;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Support\Rupiah;
use App\Modules\Notification\Actions\QueueNotification;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Collection;
use Throwable;

class SendFinanceNotifications
{
    public function __construct(private QueueNotification $notifications) {}

    public function invoiceIssued(InvoiceIssued $event): void
    {
        $invoice = $event->invoice->load('student');

        if ($invoice->student === null || $invoice->status === 'paid') {
            return;
        }

        $link = route('wali.invoices.show', $invoice);

        $this->notifications->toGuardians('invoice_issued', [$invoice->student], fn (Guardian $guardian) => [
            'nama_wali' => $guardian->name,
            'nama_santri' => $invoice->student->name,
            'periode' => $invoice->period?->translatedFormat('F Y') ?? $invoice->invoice_no,
            'total' => Rupiah::format($invoice->total),
            'jatuh_tempo' => $invoice->due_date->translatedFormat('j F Y'),
            'link' => $link,
        ], 'Tagihan '.$invoice->student->name.' terbit', $link, 'issued:'.$invoice->id.':'.$invoice->total);
    }

    public function paymentSettled(PaymentSettled $event): void
    {
        $payment = $event->payment;

        $link = route('wali.payments.show', $payment);
        $names = $this->studentNames($payment);

        $this->notifications->toGuardians('payment_success', $this->students($payment), fn (Guardian $guardian) => [
            'nama_wali' => $guardian->name,
            'nama_santri' => $names,
            'nominal' => Rupiah::format($payment->amount),
            'metode' => ['cash' => 'tunai', 'manual_transfer' => 'transfer', 'midtrans' => 'pembayaran online', 'wallet' => 'dompet'][$payment->method],
            'link' => $link,
        ], 'Pembayaran diterima', $link, 'paid:'.$payment->id);
    }

    public function paymentClosed(PaymentClosed $event): void
    {
        $payment = $event->payment;
        $link = route('wali.payments.show', $payment);
        $names = $this->studentNames($payment);

        $this->notifications->toGuardians('payment_failed', $this->students($payment), fn (Guardian $guardian) => [
            'nama_wali' => $guardian->name,
            'nama_santri' => $names,
            'alasan' => (string) $payment->reject_reason,
            'link' => $link,
        ], 'Pembayaran belum berhasil', $link, 'closed:'.$payment->id.':'.$payment->status);
    }

    public function proofSubmitted(ProofSubmitted $event): void
    {
        $payment = $event->payment;
        $link = route('staff.transfers.index');
        $names = $this->studentNames($payment);

        foreach (User::permission('finance.verify')->where('is_active', true)->get() as $user) {
            $this->notifications->toStaff('proof_submitted', $user, [
                'nama_santri' => $names,
                'nominal' => Rupiah::format($payment->transferAmount()),
                'link' => $link,
            ], 'Bukti transfer baru', $link);
        }
    }

    public function proofRejected(ProofRejected $event): void
    {
        $payment = $event->payment;
        $link = route('wali.payments.show', $payment);
        $names = $this->studentNames($payment);

        $this->notifications->toGuardians('proof_rejected', $this->students($payment), fn (Guardian $guardian) => [
            'nama_wali' => $guardian->name,
            'nama_santri' => $names,
            'alasan' => (string) $payment->reject_reason,
            'link' => $link,
        ], 'Bukti transfer ditolak', $link);
    }

    /**
     * @return Collection<int, mixed>
     */
    private function students(Payment $payment): Collection
    {
        return $payment->invoices()->with('student')->get()->pluck('student')->filter()->unique('id')->values();
    }

    private function studentNames(Payment $payment): string
    {
        return $this->students($payment)->pluck('name')->join(', ');
    }

    public function subscribe(Dispatcher $events): void
    {
        foreach ([
            InvoiceIssued::class => 'invoiceIssued',
            PaymentSettled::class => 'paymentSettled',
            PaymentClosed::class => 'paymentClosed',
            ProofSubmitted::class => 'proofSubmitted',
            ProofRejected::class => 'proofRejected',
        ] as $event => $method) {
            // Berjalan setelah commit: kegagalan notifikasi tidak boleh menampilkan galat pada pembayaran yang sudah tercatat.
            $events->listen($event, function ($payload) use ($method): void {
                try {
                    app(self::class)->{$method}($payload);
                } catch (Throwable $exception) {
                    report($exception);
                }
            });
        }
    }
}
