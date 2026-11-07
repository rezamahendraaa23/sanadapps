<?php

namespace App\Modules\Payment\Actions;

use App\Modules\Finance\Actions\FinanceException;
use App\Modules\Finance\Actions\LockPayableInvoices;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentAllocation;
use App\Modules\Finance\Support\DocumentNumber;
use App\Modules\Payment\Contracts\PaymentGateway;
use App\Modules\Payment\Support\GatewayException;
use App\Modules\Payment\Support\MidtransConfig;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateOnlinePayment
{
    private const EXPIRY_HOURS = 24;

    public function __construct(private PaymentGateway $gateway, private MidtransConfig $config) {}

    /**
     * Pembayaran dibuat dulu lalu Snap dipanggil di luar transaksi, supaya kunci invoice tidak
     * tertahan selama menunggu jaringan.
     *
     * @param  Collection<int, Invoice>  $invoices
     */
    public function handle(Collection $invoices, ?int $guardianId, ?int $userId, string $finishUrl): Payment
    {
        if (! $this->config->isReady()) {
            throw new FinanceException('Pembayaran online belum diatur pesantren. Gunakan transfer manual.');
        }

        $payment = DB::transaction(function () use ($invoices, $guardianId, $userId): Payment {
            $locked = LockPayableInvoices::handle($invoices->pluck('id')->all());
            $amount = $locked->sum(fn (Invoice $invoice) => $invoice->outstanding());
            $fee = $this->config->adminFee();

            $payment = Payment::create([
                'payment_no' => DocumentNumber::next('KW'),
                'payer_guardian_id' => $guardianId,
                'method' => 'midtrans',
                'amount' => $amount,
                'admin_fee' => $fee,
                'gross_amount' => $amount + $fee,
                'status' => 'pending',
                'expires_at' => now()->addHours(self::EXPIRY_HOURS),
                'created_by' => $userId,
            ]);

            $payment->update(['gateway_order_id' => $payment->id.'-'.Str::lower(Str::random(6))]);

            foreach ($locked as $invoice) {
                PaymentAllocation::create(['payment_id' => $payment->id, 'invoice_id' => $invoice->id, 'amount' => $invoice->outstanding()]);
            }

            return $payment;
        });

        $this->sendToGateway($payment, $finishUrl);

        return $payment;
    }

    /**
     * Snap dipanggil di luar transaksi; dipakai bersama pembayaran tagihan dan top-up.
     */
    public function sendToGateway(Payment $payment, string $finishUrl): void
    {
        try {
            $snap = $this->gateway->createTransaction($this->payload($payment, $finishUrl));
        } catch (GatewayException $exception) {
            report($exception);
            $payment->update(['status' => 'failed', 'reject_reason' => 'Layanan pembayaran online sedang bermasalah.']);

            throw new FinanceException('Pembayaran online sedang bermasalah. Coba lagi beberapa saat atau gunakan transfer manual.');
        }

        $payment->update(['gateway_payload' => ['snap_token' => $snap['token'], 'redirect_url' => $snap['redirect_url']]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Payment $payment, string $finishUrl): array
    {
        $payment->load('invoices.student:id,name', 'guardian:id,name,phone_wa,email');

        $items = $payment->isTopup()
            ? [['id' => $payment->payment_no, 'name' => 'Top-up dompet', 'price' => $payment->amount, 'quantity' => 1]]
            : $payment->invoices->map(fn (Invoice $invoice) => [
                'id' => $invoice->invoice_no,
                'name' => Str::limit('Tagihan '.$invoice->invoice_no, 45, ''),
                'price' => (int) $invoice->pivot->amount,
                'quantity' => 1,
            ])->all();

        if ($payment->admin_fee > 0) {
            $items[] = ['id' => 'BIAYA-ADMIN', 'name' => 'Biaya admin', 'price' => $payment->admin_fee, 'quantity' => 1];
        }

        return [
            'transaction_details' => ['order_id' => $payment->gateway_order_id, 'gross_amount' => $payment->gross_amount],
            'item_details' => $items,
            'customer_details' => array_filter([
                'first_name' => $payment->guardian?->name,
                'phone' => $payment->guardian?->phone_wa,
                'email' => $payment->guardian?->email,
            ]),
            'enabled_payments' => $this->config->enabledPayments(),
            'expiry' => ['duration' => self::EXPIRY_HOURS, 'unit' => 'hours'],
            'callbacks' => ['finish' => $finishUrl],
        ];
    }
}
