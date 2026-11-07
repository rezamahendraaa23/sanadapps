<?php

/**
 * Proses pekerja untuk uji konkurensi: setiap salinan berjalan sebagai proses PHP sendiri
 * dengan koneksi database sendiri, lalu bersaing pada satu titik waktu.
 */

use App\Modules\Core\Actions\SaveIntegration;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\User;
use App\Modules\Finance\Actions\ApplySettlement;
use App\Modules\Finance\Actions\CreateManualInvoice;
use App\Modules\Finance\Actions\CreateManualPayment;
use App\Modules\Finance\Actions\GenerateInvoices;
use App\Modules\Finance\Actions\RecordCashPayment;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Models\CashTransaction;
use App\Modules\Finance\Models\FeeRate;
use App\Modules\Finance\Models\FeeType;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Models\InvoiceItem;
use App\Modules\Finance\Models\Payment;
use App\Modules\Payment\Actions\ProcessMidtransWebhook;
use Carbon\CarbonImmutable;
use Database\Seeders\FinanceSeeder;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $mode, $startAt, $argument] = array_pad($argv, 4, null);

$waitUntil = fn () => usleep(max(0, (int) (((float) $startAt - microtime(true)) * 1_000_000)));

switch ($mode) {
    case 'seed':
        (new ModuleSeeder)->run();
        (new RoleSeeder)->run();
        (new FinanceSeeder)->run();
        app(SaveIntegration::class)->handle('midtrans', 'sandbox', ['server_key' => 'kunci-uji', 'client_key' => 'c'], [], true);

        User::create(['name' => 'Bendahara', 'email' => 'k@uji.test', 'type' => 'staff']);
        $student = Student::create(['nis' => 'K1', 'name' => 'Santri Konkurensi', 'gender' => 'L']);
        $invoice = app(CreateManualInvoice::class)->handle($student, [['description' => 'SPP', 'amount' => 100000]], '2030-01-10');
        $account = BankAccount::create(['bank_name' => 'B', 'account_no' => '1', 'account_holder' => 'P']);
        $payment = app(CreateManualPayment::class)->handle(collect([$invoice]), $account, null, null);
        $payment->update(['status' => 'review', 'gateway_order_id' => $payment->id.'-uji123', 'method' => 'midtrans']);

        $cashInvoice = app(CreateManualInvoice::class)->handle($student, [['description' => 'Buku', 'amount' => 50000]], '2030-01-10');

        $type = FeeType::create(['code' => 'SPPK', 'name' => 'SPP', 'category' => 'monthly', 'due_day' => 10]);
        FeeRate::create(['fee_type_id' => $type->id, 'scope' => 'default', 'amount' => 75000, 'valid_from' => '2020-01-01']);

        echo json_encode(['payment' => $payment->id, 'cash_invoice' => $cashInvoice->id]);
        break;

    case 'settle':
        $waitUntil();
        echo app(ApplySettlement::class)->handle(Payment::findOrFail((int) $argument), null) ? 'applied' : 'skipped';
        break;

    case 'webhook':
        $payment = Payment::findOrFail((int) $argument);
        $data = ['order_id' => $payment->gateway_order_id, 'status_code' => '200', 'gross_amount' => $payment->gross_amount.'.00', 'transaction_status' => 'settlement'];
        $data['signature_key'] = hash('sha512', $data['order_id'].$data['status_code'].$data['gross_amount'].'kunci-uji');
        $waitUntil();
        echo app(ProcessMidtransWebhook::class)->handle($data, json_encode($data));
        break;

    case 'cash':
        $waitUntil();
        try {
            app(RecordCashPayment::class)->handle([(int) $argument], 50000, 1);
            echo 'paid';
        } catch (Throwable $exception) {
            echo 'rejected';
        }
        break;

    case 'generate':
        $waitUntil();
        echo app(GenerateInvoices::class)->run(CarbonImmutable::parse('2030-02-01'))['invoices'];
        break;

    case 'report':
        echo json_encode([
            'paid_total' => Invoice::query()->whereHas('payments', fn ($query) => $query->whereKey((int) $argument))->sum('paid_total'),
            'cash_count' => CashTransaction::query()->where('source_id', (int) $argument)->count(),
            'status' => Payment::find((int) $argument)?->status,
            'cash_payments' => Payment::query()->where('method', 'cash')->count(),
            'cash_invoice_paid' => Invoice::query()->where('total', 50000)->value('paid_total'),
            'generated_items' => InvoiceItem::query()->where('period_key', '2030-02')->count(),
            'generated_invoices' => Invoice::query()->whereDate('period', '2030-02-01')->count(),
            'invoice_numbers_unique' => Invoice::query()->count() === Invoice::query()->distinct()->count('invoice_no'),
        ]);
        break;
}
