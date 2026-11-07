<?php

namespace App\Modules\Notification\Listeners;

use App\Modules\Academic\Events\AttendanceClosed;
use App\Modules\Academic\Events\ReportCardPublished;
use App\Modules\Academic\Models\AttendanceRecord;
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
use App\Modules\Pos\Events\PayoutUpdated;
use App\Modules\Pos\Events\StockLow;
use App\Modules\Pos\Models\Merchant;
use App\Modules\Pos\Models\MerchantPayout;
use App\Modules\Wallet\Events\CardBlocked;
use App\Modules\Wallet\Events\WalletSpent;
use App\Modules\Wallet\Support\WalletSettings;
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

        if ($payment->isTopup()) {
            $this->topupSettled($payment);

            return;
        }

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

    private function topupSettled(Payment $payment): void
    {
        $wallet = $payment->topup->wallet;
        $student = $wallet->student;
        $link = route('wali.wallet', ['anak' => $student->uuid]);

        $this->notifications->toGuardians('topup_success', [$student], fn (Guardian $guardian) => [
            'nama_wali' => $guardian->name,
            'nama_santri' => $student->name,
            'nominal' => Rupiah::format($payment->amount),
            'saldo' => Rupiah::format($wallet->refresh()->balance),
            'link' => $link,
        ], 'Top-up '.$student->name.' berhasil', $link, 'topup:'.$payment->id);
    }

    public function walletSpent(WalletSpent $event): void
    {
        $order = $event->order->load('student', 'merchant');
        $student = $order->student;
        $link = route('wali.wallet', ['anak' => $student->uuid]);

        $this->notifications->toGuardians('wallet_spend', [$student], fn (Guardian $guardian) => [
            'nama_wali' => $guardian->name,
            'nama_santri' => $student->name,
            'nominal' => Rupiah::format($order->total),
            'merchant' => $order->merchant->name,
            'saldo' => Rupiah::format($event->balance),
            'link' => $link,
        ], $student->name.' belanja di '.$order->merchant->name, $link, 'spend:'.$order->id);

        $threshold = WalletSettings::get('low_balance');

        // Hanya saat baru melewati ambang, agar tidak mengabari di setiap belanja berikutnya.
        if ($event->balance < $threshold && $event->balance + $order->total >= $threshold) {
            $this->notifications->toGuardians('wallet_low', [$student], fn (Guardian $guardian) => [
                'nama_wali' => $guardian->name,
                'nama_santri' => $student->name,
                'saldo' => Rupiah::format($event->balance),
                'link' => route('wali.wallet.topup', ['anak' => $student->uuid]),
            ], 'Saldo '.$student->name.' menipis', route('wali.wallet.topup', ['anak' => $student->uuid]), 'low:'.$order->id);
        }
    }

    public function cardBlocked(CardBlocked $event): void
    {
        $card = $event->card->load('student');
        $student = $card->student;
        $link = route('wali.wallet', ['anak' => $student->uuid]);
        $reason = (string) $card->blocked_reason;

        $this->notifications->toGuardians('card_blocked', [$student], fn (Guardian $guardian) => [
            'nama_wali' => $guardian->name,
            'nama_santri' => $student->name,
            'alasan' => $reason,
            'link' => $link,
        ], 'Kartu '.$student->name.' diblokir', $link, 'card-blocked:'.$card->id);

        foreach (User::permission('settings.manage')->where('is_active', true)->get() as $admin) {
            $this->notifications->toStaff('card_blocked', $admin, [
                'nama_wali' => $admin->name,
                'nama_santri' => $student->name,
                'alasan' => $reason,
                'link' => route('staff.cards.index'),
            ], 'Kartu '.$student->name.' diblokir', route('staff.cards.index'));
        }
    }

    public function attendanceClosed(AttendanceClosed $event): void
    {
        $session = $event->session;
        $date = $session->session_date->translatedFormat('j F Y');

        foreach ($session->records()->with('student')->whereIn('status', ['alpa', 'terlambat'])->get() as $record) {
            $this->notifications->toGuardians('attendance_alert', [$record->student], fn (Guardian $guardian) => [
                'nama_wali' => $guardian->name,
                'nama_santri' => $record->student->name,
                'status' => AttendanceRecord::STATUSES[$record->status],
                'sesi' => $session->title,
                'tanggal' => $date,
            ], $record->student->name.' '.AttendanceRecord::STATUSES[$record->status], route('wali.progress', ['anak' => $record->student->uuid, 'segment' => 'absensi']), 'attendance:'.$record->id.':'.$record->status);
        }
    }

    public function reportCardPublished(ReportCardPublished $event): void
    {
        $card = $event->card->load('student', 'term');
        $link = route('wali.progress', ['anak' => $card->student->uuid, 'segment' => 'nilai']);

        $this->notifications->toGuardians('report_published', [$card->student], fn (Guardian $guardian) => [
            'nama_wali' => $guardian->name,
            'nama_santri' => $card->student->name,
            'term' => $card->term->name,
            'link' => $link,
        ], 'Rapor '.$card->student->name.' terbit', $link, 'report:'.$card->id);
    }

    public function stockLow(StockLow $event): void
    {
        $merchant = $event->product->merchant;
        $link = route('staff.merchants.stock', $merchant);

        foreach ($this->merchantManagers($merchant) as $user) {
            $this->notifications->toStaff('stock_low', $user, [
                'merchant' => $merchant->name,
                'produk' => $event->product->name,
                'sisa' => $event->stock,
                'link' => $link,
            ], 'Stok '.$event->product->name.' menipis', $link);
        }
    }

    public function payoutUpdated(PayoutUpdated $event): void
    {
        $payout = $event->payout->load('merchant');
        $link = route('staff.merchants.report', $payout->merchant);

        foreach ($this->merchantManagers($payout->merchant) as $user) {
            $this->notifications->toStaff('payout_status', $user, [
                'merchant' => $payout->merchant->name,
                'nominal' => Rupiah::format($payout->amount),
                'status' => MerchantPayout::LABELS[$payout->status],
                'link' => $link,
            ], 'Pencairan: '.MerchantPayout::LABELS[$payout->status], $link);
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function merchantManagers(Merchant $merchant): Collection
    {
        return collect([$merchant->pic])->merge($merchant->cashiers()->permission('merchants.own')->get())->filter()->unique('id')->values();
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
        return $payment->load('invoices.student', 'topup.wallet.student')->relatedStudents();
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
            WalletSpent::class => 'walletSpent',
            CardBlocked::class => 'cardBlocked',
            AttendanceClosed::class => 'attendanceClosed',
            ReportCardPublished::class => 'reportCardPublished',
            StockLow::class => 'stockLow',
            PayoutUpdated::class => 'payoutUpdated',
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
