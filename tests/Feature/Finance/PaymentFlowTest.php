<?php

use App\Modules\Core\Models\Module;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\User;
use App\Modules\Finance\Actions\ApplySettlement;
use App\Modules\Finance\Actions\BuildReceiptPdf;
use App\Modules\Finance\Actions\CreateManualInvoice;
use App\Modules\Finance\Actions\CreateManualPayment;
use App\Modules\Finance\Actions\FinanceException;
use App\Modules\Finance\Actions\RecordCashPayment;
use App\Modules\Finance\Actions\RefundPayment;
use App\Modules\Finance\Actions\RejectManualPayment;
use App\Modules\Finance\Actions\SubmitPaymentProof;
use App\Modules\Finance\Actions\VoidInvoice;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Models\CashTransaction;
use App\Modules\Finance\Models\FeeType;
use App\Modules\Finance\Models\InvoiceItem;
use App\Support\Pdf\PdfRenderer;
use Database\Seeders\ModuleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Route::get('/uji-kwitansi/{payment}', fn () => '')->name('receipts.verify');
    app('router')->getRoutes()->refreshNameLookups();
    (new ModuleSeeder)->run();
    Storage::fake('local');
    $this->travelTo('2026-11-02 09:00:00');
    $this->bendahara = User::create(['name' => 'B', 'email' => 'b@uji.test', 'type' => 'staff']);
    $this->student = Student::create(['nis' => 'A1', 'name' => 'Ahmad', 'gender' => 'L']);
    $this->account = BankAccount::create(['bank_name' => 'BSI', 'account_no' => '123', 'account_holder' => 'Pesantren']);
    $this->invoice = app(CreateManualInvoice::class)->handle($this->student, [['description' => 'SPP', 'amount' => 625000]], '2026-11-10');
});

function manualPayment($context)
{
    return app(CreateManualPayment::class)->handle(collect([$context->invoice]), $context->account, null, null);
}

it('alur transfer manual: bukti, verifikasi, invoice lunas, kas tercatat', function () {
    $payment = manualPayment($this);
    expect($payment->status)->toBe('pending')->and($payment->amount)->toBe(625000)->and($this->invoice->fresh()->paid_total)->toBe(0);

    app(SubmitPaymentProof::class)->handle($payment, UploadedFile::fake()->image('bukti.jpg'));
    expect($payment->status)->toBe('review')->and($payment->proof_path)->not->toBeNull();
    Storage::disk('local')->assertExists($payment->proof_path);

    expect(app(ApplySettlement::class)->handle($payment, $this->bendahara->id))->toBeTrue();

    $invoice = $this->invoice->fresh();
    expect($payment->status)->toBe('settlement')
        ->and($payment->verified_by)->toBe($this->bendahara->id)
        ->and($invoice->status)->toBe('paid')
        ->and($invoice->paid_total)->toBe(625000)
        ->and(CashTransaction::sum('amount'))->toEqual(625000);
});

it('verifikasi ganda tidak melunasi dan tidak mencatat kas dua kali', function () {
    $payment = manualPayment($this);
    app(SubmitPaymentProof::class)->handle($payment, UploadedFile::fake()->image('b.jpg'));

    expect(app(ApplySettlement::class)->handle($payment, $this->bendahara->id))->toBeTrue()
        ->and(app(ApplySettlement::class)->handle($payment, $this->bendahara->id))->toBeFalse()
        ->and($this->invoice->fresh()->paid_total)->toBe(625000)
        ->and(CashTransaction::count())->toBe(1);
});

it('kode unik menambah nominal transfer tetapi tidak melunasi, selisihnya masuk kas sendiri', function () {
    Module::where('module_key', 'payment_manual')->update(['settings' => ['unique_code' => true]]);

    $payment = manualPayment($this);
    expect($payment->unique_code)->toBeBetween(1, 999)
        ->and($payment->amount)->toBe(625000)
        ->and($payment->transferAmount())->toBe(625000 + $payment->unique_code);

    app(SubmitPaymentProof::class)->handle($payment, UploadedFile::fake()->image('b.jpg'));
    app(ApplySettlement::class)->handle($payment, $this->bendahara->id);

    expect($this->invoice->fresh()->paid_total)->toBe(625000)
        ->and(CashTransaction::where('description', 'like', 'Kode unik%')->value('amount'))->toBe($payment->unique_code)
        ->and(CashTransaction::where('description', 'like', 'Pembayaran%')->value('amount'))->toBe(625000);
});

it('penolakan wajib alasan, wali bisa unggah ulang pada pembayaran yang sama', function () {
    $payment = manualPayment($this);
    app(SubmitPaymentProof::class)->handle($payment, UploadedFile::fake()->image('a.jpg'));
    $firstProof = $payment->proof_path;

    expect(fn () => app(RejectManualPayment::class)->handle($payment, '', $this->bendahara->id))->toThrow(FinanceException::class);

    app(RejectManualPayment::class)->handle($payment, 'Nominal tidak sesuai', $this->bendahara->id);
    expect($payment->status)->toBe('failed')->and($payment->reject_reason)->toBe('Nominal tidak sesuai')->and($this->invoice->fresh()->paid_total)->toBe(0);

    app(SubmitPaymentProof::class)->handle($payment, UploadedFile::fake()->image('b.jpg'));
    expect($payment->status)->toBe('review')->and($payment->reject_reason)->toBeNull();
    Storage::disk('local')->assertMissing($firstProof);
});

it('tidak bisa membuat dua pembayaran berjalan untuk tagihan yang sama', function () {
    manualPayment($this);

    expect(fn () => manualPayment($this))->toThrow(FinanceException::class, 'pembayaran yang berjalan')
        ->and(fn () => app(RecordCashPayment::class)->handle([$this->invoice->id], 625000, $this->bendahara->id))->toThrow(FinanceException::class, 'pembayaran yang berjalan');
});

it('menolak melunasi bila tagihan sudah dibatalkan atau terbayar lewat cara lain', function () {
    $payment = manualPayment($this);
    $this->invoice->update(['status' => 'void']);

    expect(fn () => app(ApplySettlement::class)->handle($payment, $this->bendahara->id))->toThrow(FinanceException::class, 'dibatalkan');
    expect($payment->fresh()->status)->toBe('pending');
});

it('tidak bisa void selama ada pembayaran berjalan', function () {
    manualPayment($this);

    expect(fn () => app(VoidInvoice::class)->handle($this->invoice, 'x'))->toThrow(FinanceException::class, 'berjalan');
});

it('tunai penuh langsung lunas dan bernomor kwitansi', function () {
    $payment = app(RecordCashPayment::class)->handle([$this->invoice->id], 625000, $this->bendahara->id);

    expect($payment->status)->toBe('settlement')
        ->and($payment->payment_no)->toBe('KW-202611-00001')
        ->and($payment->method)->toBe('cash')
        ->and($this->invoice->fresh()->status)->toBe('paid');
});

it('menolak tunai sebagian bila jenisnya tidak mengizinkan, dan melebihi tagihan', function () {
    expect(fn () => app(RecordCashPayment::class)->handle([$this->invoice->id], 100000, $this->bendahara->id))->toThrow(FinanceException::class, 'harus dibayar penuh');
    expect(fn () => app(RecordCashPayment::class)->handle([$this->invoice->id], 700000, $this->bendahara->id))->toThrow(FinanceException::class, 'melebihi');
    expect($this->invoice->fresh()->paid_total)->toBe(0);
});

it('tunai sebagian dialokasikan ke tagihan tertua dulu', function () {
    $fee = FeeType::create(['code' => 'CICIL', 'name' => 'Cicilan', 'category' => 'custom', 'allow_partial' => true]);
    $older = app(CreateManualInvoice::class)->handle($this->student, [['description' => 'Lama', 'amount' => 100000, 'fee_type_id' => $fee->id]], '2026-10-01');
    $newer = app(CreateManualInvoice::class)->handle($this->student, [['description' => 'Baru', 'amount' => 100000, 'fee_type_id' => $fee->id]], '2026-12-01');

    app(RecordCashPayment::class)->handle([$newer->id, $older->id], 150000, $this->bendahara->id);

    expect($older->fresh()->status)->toBe('paid')
        ->and($newer->fresh()->paid_total)->toBe(50000)
        ->and($newer->fresh()->status)->toBe('partial')
        ->and($newer->fresh()->outstanding())->toBe(50000);
});

it('pengembalian dana mengurangi terbayar dan mencatat kas keluar, tidak bisa dua kali', function () {
    $payment = app(RecordCashPayment::class)->handle([$this->invoice->id], 625000, $this->bendahara->id);

    expect(fn () => app(RefundPayment::class)->handle($payment, '', $this->bendahara->id))->toThrow(FinanceException::class);

    app(RefundPayment::class)->handle($payment, 'Salah catat', $this->bendahara->id);

    expect($payment->status)->toBe('refunded')
        ->and($this->invoice->fresh()->paid_total)->toBe(0)
        ->and($this->invoice->fresh()->status)->toBe('unpaid')
        ->and(CashTransaction::where('type', 'out')->value('amount'))->toBe(625000);

    expect(fn () => app(RefundPayment::class)->handle($payment, 'lagi', $this->bendahara->id))->toThrow(FinanceException::class);
});

it('kwitansi hanya untuk pembayaran lunas dan memuat data kunci', function () {
    $captured = new class implements PdfRenderer
    {
        public string $html = '';

        public function render(string $html, string $format = 'A4'): string
        {
            $this->html = $html;

            return '%PDF-uji';
        }
    };
    $this->app->instance(PdfRenderer::class, $captured);

    expect(fn () => app(BuildReceiptPdf::class)->handle(manualPayment($this)))->toThrow(FinanceException::class, 'setelah pembayaran lunas');

    $invoice = app(CreateManualInvoice::class)->handle($this->student, [['description' => 'Seragam', 'amount' => 200000]], '2026-11-10');
    $payment = app(RecordCashPayment::class)->handle([$invoice->id], 200000, $this->bendahara->id);

    expect(app(BuildReceiptPdf::class)->handle($payment))->toBe('%PDF-uji')
        ->and($captured->html)->toContain($payment->payment_no)
        ->and($captured->html)->toContain('Ahmad')
        ->and($captured->html)->toContain('Rp 200.000')
        ->and($captured->html)->toContain('Lunas')
        ->and($captured->html)->toContain('<svg');
});
