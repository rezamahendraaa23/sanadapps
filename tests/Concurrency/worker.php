<?php

/**
 * Proses pekerja untuk uji konkurensi: setiap salinan berjalan sebagai proses PHP sendiri
 * dengan koneksi database sendiri, lalu bersaing pada satu titik waktu.
 */

use App\Modules\Core\Actions\SaveIntegration;
use App\Modules\Core\Models\Module;
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
use App\Modules\Pos\Actions\CheckoutOrder;
use App\Modules\Pos\Models\Merchant;
use App\Modules\Pos\Models\MerchantProduct;
use App\Modules\Pos\Models\PosOrder;
use App\Modules\Pos\Models\StockMovement;
use App\Modules\Wallet\Actions\IssueCard;
use App\Modules\Wallet\Actions\ReconcileWallets;
use App\Modules\Wallet\Actions\RecordCashTopup;
use App\Modules\Wallet\Models\Wallet;
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

        // Saldo cukup untuk tepat 3 kali belanja Rp 30.000.
        $spender = Student::create(['nis' => 'K2', 'name' => 'Santri Dompet', 'gender' => 'L', 'status' => 'inactive']);
        app(IssueCard::class)->handle($spender, 'KONKUREN1');
        app(RecordCashTopup::class)->handle($spender, 90000, User::first()->id);
        Module::query()->where('module_key', 'wallet')->update(['settings' => ['pin_threshold' => 1000000]]);
        $merchant = Merchant::create(['name' => 'Warung Konkuren', 'payment_modes' => ['card', 'qr']]);

        $sameKey = Student::create(['nis' => 'K3', 'name' => 'Santri Tap Ganda', 'gender' => 'L', 'status' => 'inactive']);
        app(IssueCard::class)->handle($sameKey, 'KONKUREN2');
        app(RecordCashTopup::class)->handle($sameKey, 90000, User::first()->id);

        // Stok 3, saldo berlebih: yang membatasi hanya stok.
        $buyer = Student::create(['nis' => 'K4', 'name' => 'Santri Stok', 'gender' => 'L', 'status' => 'inactive']);
        app(IssueCard::class)->handle($buyer, 'KONKUREN3');
        app(RecordCashTopup::class)->handle($buyer, 500000, User::first()->id);
        $product = MerchantProduct::create(['merchant_id' => $merchant->id, 'name' => 'Stok Terakhir', 'price' => 1000]);
        MerchantProduct::query()->whereKey($product->id)->update(['stock' => 3]);

        echo json_encode(['payment' => $payment->id, 'cash_invoice' => $cashInvoice->id, 'merchant' => $merchant->id, 'product' => $product->id]);
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

    case 'spend':
        $merchant = Merchant::findOrFail((int) $argument);
        $waitUntil();
        $result = app(CheckoutOrder::class)->handle($merchant, [], 30000, 'KONKUREN1', null, 'spend-'.getmypid(), null);
        echo $result->ok ? 'paid' : $result->code;
        break;

    case 'tap':
        $merchant = Merchant::findOrFail((int) $argument);
        $waitUntil();
        $result = app(CheckoutOrder::class)->handle($merchant, [], 10000, 'KONKUREN2', null, 'tap-ganda', null);
        echo $result->ok ? 'paid:'.$result->balance : $result->code;
        break;

    case 'stock':
        $product = MerchantProduct::findOrFail((int) $argument);
        $waitUntil();
        $result = app(CheckoutOrder::class)->handle($product->merchant, [$product->id => 1], null, 'KONKUREN3', null, 'stock-'.getmypid(), null);
        echo $result->ok ? 'paid' : $result->code;
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
            'cash_payments' => Payment::query()->where('method', 'cash')->where('purpose', 'invoice')->count(),
            'cash_invoice_paid' => Invoice::query()->where('total', 50000)->value('paid_total'),
            'generated_items' => InvoiceItem::query()->where('period_key', '2030-02')->count(),
            'generated_invoices' => Invoice::query()->whereDate('period', '2030-02-01')->count(),
            'invoice_numbers_unique' => Invoice::query()->count() === Invoice::query()->distinct()->count('invoice_no'),
            'spender_balance' => Wallet::query()->whereHas('student', fn ($query) => $query->where('nis', 'K2'))->value('balance'),
            'spender_orders' => PosOrder::query()->whereHas('student', fn ($query) => $query->where('nis', 'K2'))->count(),
            'tap_balance' => Wallet::query()->whereHas('student', fn ($query) => $query->where('nis', 'K3'))->value('balance'),
            'tap_orders' => PosOrder::query()->whereHas('student', fn ($query) => $query->where('nis', 'K3'))->count(),
            'merchant_balance' => Merchant::query()->where('name', 'Warung Konkuren')->first()?->balance(),
            'ledger_differences' => count(app(ReconcileWallets::class)->handle()),
            'product_stock' => MerchantProduct::query()->where('name', 'Stok Terakhir')->value('stock'),
            'stock_sales' => StockMovement::query()->where('type', 'sale')->count(),
        ]);
        break;
}
