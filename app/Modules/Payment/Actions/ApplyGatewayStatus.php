<?php

namespace App\Modules\Payment\Actions;

use App\Modules\Finance\Actions\ApplySettlement;
use App\Modules\Finance\Actions\FinanceException;
use App\Modules\Finance\Actions\RefundPayment;
use App\Modules\Finance\Events\PaymentClosed;
use App\Modules\Finance\Models\Payment;
use Illuminate\Support\Facades\DB;

class ApplyGatewayStatus
{
    public function __construct(private ApplySettlement $settlement, private RefundPayment $refund) {}

    /**
     * Peta status PRD Sistem 8.5, dipakai bersama webhook dan sinkronisasi. Status lunas tidak pernah
     * mundur oleh notifikasi yang datang terlambat atau tidak berurutan.
     *
     * @param  array<string, mixed>  $data  Isi notifikasi atau jawaban Get Status.
     */
    public function handle(Payment $payment, array $data): void
    {
        DB::transaction(function () use ($payment, $data): void {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            $locked->update([
                'gateway_transaction_id' => $data['transaction_id'] ?? $locked->gateway_transaction_id,
                'channel' => $data['payment_type'] ?? $locked->channel,
                'gateway_payload' => [...($locked->gateway_payload ?? []), 'last_status' => $data['transaction_status'] ?? null],
            ]);

            match ($this->mapStatus($data)) {
                'settlement' => $this->settle($locked, $data),
                'expired' => $this->close($locked, 'expired'),
                'failed' => $this->close($locked, 'failed'),
                'refunded' => $this->markRefunded($locked),
                default => null,
            };
        });

        $payment->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function mapStatus(array $data): ?string
    {
        return match ($data['transaction_status'] ?? null) {
            'settlement' => 'settlement',
            // Kartu: capture baru dianggap lunas bila lolos pemeriksaan fraud.
            'capture' => ($data['fraud_status'] ?? 'accept') === 'accept' ? 'settlement' : 'pending',
            'pending' => 'pending',
            'expire' => 'expired',
            'deny', 'cancel', 'failure' => 'failed',
            'refund', 'partial_refund' => 'refunded',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function settle(Payment $payment, array $data): void
    {
        if ($payment->status === 'settlement') {
            return;
        }

        $gross = (int) round((float) ($data['gross_amount'] ?? 0));

        if ($gross !== $payment->gross_amount) {
            $payment->update(['needs_review' => true, 'review_note' => "Nominal dari Midtrans Rp {$gross} tidak sama dengan tagihan Rp {$payment->gross_amount}. Pembayaran tidak ditandai lunas."]);

            return;
        }

        try {
            // Uang sudah masuk walau pembayaran terlanjur ditutup, jadi tetap dilunasi bila tagihannya masih terbuka.
            $this->settlement->handle($payment, null, allowClosed: true);
        } catch (FinanceException $exception) {
            $payment->update(['needs_review' => true, 'review_note' => 'Uang masuk di Midtrans tetapi tidak bisa dilunaskan: '.$exception->getMessage()]);
        }
    }

    private function close(Payment $payment, string $status): void
    {
        if ($payment->status !== 'pending') {
            return;
        }

        $payment->update(['status' => $status, 'reject_reason' => $status === 'expired' ? 'Batas waktu pembayaran habis.' : 'Pembayaran gagal di penyedia layanan.']);

        PaymentClosed::dispatch($payment);
    }

    private function markRefunded(Payment $payment): void
    {
        if ($payment->status !== 'settlement') {
            return;
        }

        // Kas dicatat manual oleh bendahara sesuai PRD Sistem 8.5; di sini hanya tagihan yang dibuka kembali.
        $this->refund->handle($payment, 'Refund dari Midtrans.', null, recordCash: false);
        $payment->update(['needs_review' => true, 'review_note' => 'Refund di Midtrans. Catat pengeluarannya di Laporan Kas.']);
    }
}
