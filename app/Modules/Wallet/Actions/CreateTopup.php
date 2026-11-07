<?php

namespace App\Modules\Wallet\Actions;

use App\Modules\Core\Models\Student;
use App\Modules\Finance\Actions\CreateManualPayment;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Support\DocumentNumber;
use App\Modules\Finance\Support\Rupiah;
use App\Modules\Payment\Actions\CreateOnlinePayment;
use App\Modules\Payment\Support\MidtransConfig;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\WalletTopup;
use App\Modules\Wallet\Support\WalletException;
use App\Modules\Wallet\Support\WalletSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateTopup
{
    private const EXPIRY_HOURS = 24;

    public function __construct(private CreateOnlinePayment $online, private CreateManualPayment $manual, private MidtransConfig $config) {}

    public function viaTransfer(Student $student, int $amount, BankAccount $account, ?int $guardianId, ?int $userId): Payment
    {
        $this->assertAmount($amount);

        return DB::transaction(function () use ($student, $amount, $account, $guardianId, $userId): Payment {
            $payment = Payment::create([
                'payment_no' => DocumentNumber::next('KW'),
                'purpose' => 'wallet_topup',
                'payer_guardian_id' => $guardianId,
                'method' => 'manual_transfer',
                'amount' => $amount,
                'unique_code' => $this->manual->uniqueCode(),
                'gross_amount' => $amount,
                'status' => 'pending',
                'bank_account_id' => $account->id,
                'created_by' => $userId,
            ]);

            $this->link($student, $payment);

            return $payment;
        });
    }

    public function viaOnline(Student $student, int $amount, ?int $guardianId, ?int $userId, string $finishUrl): Payment
    {
        $this->assertAmount($amount);

        if (! $this->config->isReady()) {
            throw new WalletException('Pembayaran online belum diatur pesantren. Gunakan transfer manual.');
        }

        $payment = DB::transaction(function () use ($student, $amount, $guardianId, $userId): Payment {
            $fee = $this->config->adminFee();

            $payment = Payment::create([
                'payment_no' => DocumentNumber::next('KW'),
                'purpose' => 'wallet_topup',
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
            $this->link($student, $payment);

            return $payment;
        });

        $this->online->sendToGateway($payment, $finishUrl);

        return $payment;
    }

    private function link(Student $student, Payment $payment): void
    {
        WalletTopup::create(['wallet_id' => Wallet::forStudent($student)->id, 'payment_id' => $payment->id, 'amount' => $payment->amount]);
    }

    private function assertAmount(int $amount): void
    {
        ['topup_min' => $min, 'topup_max' => $max] = WalletSettings::all();

        if ($amount < $min || $amount > $max) {
            throw new WalletException('Nominal top-up harus antara '.Rupiah::format($min).' dan '.Rupiah::format($max).'.');
        }
    }
}
