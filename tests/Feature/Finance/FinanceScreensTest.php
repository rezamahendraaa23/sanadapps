<?php

use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Models\Guardian;
use App\Modules\Core\Models\Module;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\ModuleRegistry;
use App\Modules\Finance\Actions\CreateManualInvoice;
use App\Modules\Finance\Actions\CreateManualPayment;
use App\Modules\Finance\Actions\GenerateMonthlyInvoices;
use App\Modules\Finance\Actions\RecordCashPayment;
use App\Modules\Finance\Actions\SubmitPaymentProof;
use App\Modules\Finance\Livewire\Invoices\GenerateScreen;
use App\Modules\Finance\Livewire\Invoices\InvoiceCreate;
use App\Modules\Finance\Livewire\Invoices\InvoiceShow;
use App\Modules\Finance\Livewire\Payments\CashPayment;
use App\Modules\Finance\Livewire\Payments\TransferReview;
use App\Modules\Finance\Livewire\Portal\GuardianCheckout;
use App\Modules\Finance\Livewire\Portal\GuardianInvoices;
use App\Modules\Finance\Livewire\Portal\GuardianPayment;
use App\Modules\Finance\Livewire\Reports\ArrearsImport;
use App\Modules\Finance\Livewire\Reports\CashReport;
use App\Modules\Finance\Livewire\Settings\BankAccounts;
use App\Modules\Finance\Livewire\Settings\FeeTypes;
use App\Modules\Finance\Livewire\Students\StudentFinance;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Models\CashCategory;
use App\Modules\Finance\Models\CashTransaction;
use App\Modules\Finance\Models\FeeRate;
use App\Modules\Finance\Models\FeeType;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\StudentDiscount;
use App\Support\Pdf\PdfRenderer;
use Database\Seeders\FinanceSeeder;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function financeStaff(string $role): User
{
    return tap(User::create(['name' => ucfirst($role), 'email' => $role.'-'.uniqid().'@uji.test', 'type' => 'staff']))->assignRole($role);
}

function guardianOf(Student $student, string $phone): User
{
    $user = tap(User::create(['name' => 'Wali', 'phone' => $phone, 'type' => 'guardian']))->assignRole('wali');
    Guardian::create(['user_id' => $user->id, 'name' => 'Wali', 'phone_wa' => $phone])->students()->attach($student->id, ['relation' => 'ayah']);

    return $user;
}

beforeEach(function () {
    (new ModuleSeeder)->run();
    (new RoleSeeder)->run();
    (new FinanceSeeder)->run();
    Storage::fake('local');
    $this->travelTo('2026-11-02 09:00:00');
    $this->bendahara = financeStaff('bendahara');
    $this->actingAs($this->bendahara);
    $this->student = Student::create(['nis' => 'A1', 'name' => 'Ahmad', 'gender' => 'L']);
    $this->other = Student::create(['nis' => 'B1', 'name' => 'Budi', 'gender' => 'L']);
    $this->account = BankAccount::create(['bank_name' => 'BSI', 'account_no' => '7001', 'account_holder' => 'Pesantren']);
    $this->invoice = app(CreateManualInvoice::class)->handle($this->student, [['description' => 'SPP', 'amount' => 625000]], '2026-11-10');
    $this->otherInvoice = app(CreateManualInvoice::class)->handle($this->other, [['description' => 'SPP', 'amount' => 500000]], '2026-11-10');
    $this->wali = guardianOf($this->student, '6281200000001');
});

it('membatasi layar keuangan sesuai matriks PRD', function (string $role, string $route, int $status) {
    $this->actingAs(financeStaff($role))->get(route($route))->assertStatus($status);
})->with([
    ['bendahara', 'staff.invoices.index', 200],
    ['bendahara', 'staff.transfers.index', 200],
    ['bendahara', 'staff.settings.fee-types', 200],
    ['admin', 'staff.invoices.index', 200],
    ['admin', 'staff.settings.fee-types', 200],
    ['admin', 'staff.transfers.index', 403],
    ['admin_akademik', 'staff.invoices.index', 200],
    ['admin_akademik', 'staff.invoices.generate', 403],
    ['admin_akademik', 'staff.payments.cash', 403],
    ['kasir', 'staff.invoices.index', 403],
    ['ustadz', 'staff.cash.index', 403],
    ['musyrif', 'staff.arrears.index', 403],
    ['penjaga_gerbang', 'staff.payments.index', 403],
]);

it('menjawab MODULE_DISABLED bila modul keuangan mati dan menu hilang', function () {
    Module::whereIn('module_key', ['payment_manual', 'finance'])->update(['is_enabled' => false]);
    app(ModuleRegistry::class)->forget();

    $this->get(route('staff.invoices.index'))->assertForbidden()->assertSee('MODULE_DISABLED');
    $this->getJson(route('staff.invoices.index'))->assertJsonPath('error.code', 'MODULE_DISABLED');
    expect(Invoice::count())->toBe(2);
});

it('wali hanya melihat dan membayar tagihan anak sendiri, id ditebak selalu ditolak', function () {
    $this->actingAs($this->wali);

    $this->get(route('wali.invoices.show', $this->invoice))->assertOk();
    $this->get(route('wali.invoices.show', $this->otherInvoice))->assertForbidden();

    Livewire::test(GuardianInvoices::class)->assertSee('Ahmad')->assertDontSee('Budi');

    Livewire::withQueryParams(['tagihan' => $this->otherInvoice->uuid])->test(GuardianCheckout::class)
        ->assertSee('Tidak ada tagihan yang perlu dibayar')
        ->call('payByTransfer')
        ->assertHasErrors('bank_account');
    expect(Payment::count())->toBe(0);

    $payment = app(RecordCashPayment::class)->handle([$this->otherInvoice->id], 500000, $this->bendahara->id);
    $this->get(route('wali.payments.show', $payment))->assertForbidden();
    $this->get(route('payments.receipt', $payment))->assertForbidden();
    $this->get(route('staff.payments.proof', $payment))->assertRedirect(route('wali.home'));
});

it('wali membayar: pilih tagihan, transfer, kirim bukti, lalu terverifikasi', function () {
    $this->actingAs($this->wali);

    Livewire::test(GuardianInvoices::class)
        ->set('selected', [$this->invoice->uuid])
        ->assertSee('Rp 625.000')
        ->call('pay')
        ->assertRedirect(route('wali.checkout', ['tagihan' => $this->invoice->uuid]));

    $checkout = Livewire::withQueryParams(['tagihan' => $this->invoice->uuid])->test(GuardianCheckout::class)
        ->assertSee('Rp 625.000')
        ->assertSet('bank_account', $this->account->uuid)
        ->call('payByTransfer');

    $payment = Payment::first();
    $checkout->assertRedirect(route('wali.payments.show', $payment));
    expect($payment->payer_guardian_id)->toBe($this->wali->guardian->id);

    Livewire::test(GuardianPayment::class, ['payment' => $payment])
        ->assertSee('7001')
        ->call('submit')
        ->assertHasErrors('proof')
        ->set('proof', UploadedFile::fake()->create('virus.exe', 10))
        ->call('submit')
        ->assertHasErrors('proof')
        ->set('proof', UploadedFile::fake()->image('bukti.jpg')->size(6000))
        ->call('submit')
        ->assertSee('Maksimal 5 MB')
        ->set('proof', UploadedFile::fake()->image('bukti.jpg'))
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSee('sedang diperiksa bendahara');

    expect($payment->fresh()->status)->toBe('review');

    $this->actingAs($this->bendahara);
    Livewire::test(TransferReview::class)->assertSee('Ahmad')->assertSee('Rp 625.000')->call('verify')->assertDispatched('toast');
    expect($this->invoice->fresh()->status)->toBe('paid');

    $this->actingAs($this->wali);
    Livewire::test(GuardianPayment::class, ['payment' => $payment->fresh()])->assertSee('Unduh Kwitansi')->assertSee('wa.me');
});

it('penolakan memunculkan alasan ke wali dan bisa unggah ulang', function () {
    $payment = app(CreateManualPayment::class)->handle(collect([$this->invoice]), $this->account, null, null);
    app(SubmitPaymentProof::class)->handle($payment, UploadedFile::fake()->image('a.jpg'));

    Livewire::test(TransferReview::class)->call('reject')->assertHasErrors('reason');
    Livewire::test(TransferReview::class)->set('reason', 'Nominal kurang')->call('reject')->assertHasNoErrors();
    expect($payment->fresh()->status)->toBe('failed');

    $this->actingAs($this->wali);
    Livewire::test(GuardianPayment::class, ['payment' => $payment->fresh()])
        ->assertSee('Nominal kurang')
        ->set('proof', UploadedFile::fake()->image('b.jpg'))
        ->call('submit')
        ->assertHasNoErrors();
    expect($payment->fresh()->status)->toBe('review');
});

it('hanya bendahara yang bisa memverifikasi, termasuk lewat aksi langsung', function () {
    $payment = app(CreateManualPayment::class)->handle(collect([$this->invoice]), $this->account, null, null);
    app(SubmitPaymentProof::class)->handle($payment, UploadedFile::fake()->image('a.jpg'));

    $this->actingAs(financeStaff('admin'));
    Livewire::test(TransferReview::class)->call('verify')->assertForbidden();
    expect($this->invoice->fresh()->paid_total)->toBe(0);
});

it('antrean verifikasi urut terlama dan lanjut otomatis ke berikutnya', function () {
    $first = app(CreateManualPayment::class)->handle(collect([$this->invoice]), $this->account, null, null);
    app(SubmitPaymentProof::class)->handle($first, UploadedFile::fake()->image('a.jpg'));
    $this->travel(5)->minutes();
    $second = app(CreateManualPayment::class)->handle(collect([$this->otherInvoice]), $this->account, null, null);
    app(SubmitPaymentProof::class)->handle($second, UploadedFile::fake()->image('b.jpg'));

    Livewire::test(TransferReview::class)
        ->assertSet('current', $first->uuid)
        ->call('verify')
        ->assertSet('current', $second->uuid)
        ->assertSee('Budi');
});

it('menyediakan bukti transfer hanya untuk yang berhak dan kompres foto besar', function () {
    Storage::fake('local');
    $payment = app(CreateManualPayment::class)->handle(collect([$this->invoice]), $this->account, null, null);
    app(SubmitPaymentProof::class)->handle($payment, UploadedFile::fake()->image('besar.jpg', 4000, 3000));

    expect($payment->proof_path)->toEndWith('.jpg');
    [$width] = getimagesizefromstring(Storage::disk('local')->get($payment->proof_path));
    expect($width)->toBe(1600);

    $this->get(route('staff.payments.proof', $payment))->assertOk();
    $this->actingAs(financeStaff('kasir'))->get(route('staff.payments.proof', $payment))->assertForbidden();
});

it('mengunduh kwitansi PDF dan halaman periksa publik tidak membuka nama santri', function () {
    $this->app->instance(PdfRenderer::class, new class implements PdfRenderer
    {
        public function render(string $html, string $format = 'A4'): string
        {
            return '%PDF-uji';
        }
    });
    $payment = app(RecordCashPayment::class)->handle([$this->invoice->id], 625000, $this->bendahara->id);

    $this->get(route('payments.receipt', $payment))->assertOk()->assertHeader('content-type', 'application/pdf');

    $this->actingAs($this->wali)->get(route('payments.receipt', $payment))->assertOk();

    auth()->logout();
    $this->get(route('receipts.verify', $payment))->assertOk()->assertSee('sah')->assertSee('Rp 625.000')->assertDontSee('Ahmad');
});

it('layar jenis tagihan membuat tarif dan menolak masa berlaku bertabrakan', function () {
    $component = Livewire::test(FeeTypes::class)
        ->call('create')
        ->set('code', 'spp')->set('name', 'SPP')->set('category', 'monthly')
        ->call('save')
        ->assertHasNoErrors();

    $type = FeeType::first();
    expect($type->code)->toBe('SPP');

    $component->set('rate_amount', '350.000')->set('rate_from', '2026-01-01')->call('addRate')->assertHasNoErrors();
    expect(FeeRate::first()->amount)->toBe(350000);

    $component->set('rate_amount', '400000')->set('rate_from', '2026-06-01')->call('addRate')->assertHasErrors('rate_from');
    expect(FeeRate::count())->toBe(1);

    $component->set('previewNis', 'A1')->assertSee('Rp 350.000')->assertSee('Tarif umum');
    $component->set('previewNis', 'ZZZ')->assertSee('NIS tidak ditemukan');
});

it('tarif kelas berbeda boleh berlaku bersamaan', function () {
    $type = FeeType::create(['code' => 'SPP', 'name' => 'SPP', 'category' => 'monthly']);
    $a = Classroom::create(['name' => 'A']);
    $b = Classroom::create(['name' => 'B']);

    $component = Livewire::withQueryParams(['jenis' => $type->uuid])->test(FeeTypes::class);
    foreach ([$a, $b] as $classroom) {
        $component->set('rate_scope', 'classroom')->set('rate_target', (string) $classroom->id)->set('rate_amount', '100000')->set('rate_from', '2026-01-01')->call('addRate')->assertHasNoErrors();
    }

    expect(FeeRate::count())->toBe(2);
});

it('generate wajib pratinjau dulu dan pratinjau batal bila pilihan berubah', function () {
    $type = FeeType::create(['code' => 'SPP', 'name' => 'SPP', 'category' => 'monthly', 'due_day' => 10]);
    FeeRate::create(['fee_type_id' => $type->id, 'scope' => 'default', 'amount' => 100000, 'valid_from' => '2026-01-01']);

    Livewire::test(GenerateScreen::class)->call('generate')->assertStatus(422);

    Livewire::test(GenerateScreen::class)
        ->assertSet('period', '2026-11')
        ->call('runPreview')
        ->assertSee('Generate 2 Tagihan')
        ->set('period', '2026-12')
        ->assertSet('preview', null)
        ->call('runPreview')
        ->call('generate')
        ->assertSet('preview', null)
        ->assertSee('Hasil generate');

    expect(Invoice::where('source', 'auto')->count())->toBe(2);
});

it('tagihan manual dan void lewat layar', function () {
    Livewire::test(InvoiceCreate::class)->call('save')->assertHasErrors('search');

    Livewire::test(InvoiceCreate::class)
        ->call('pickStudent', $this->student->uuid)
        ->set('items.0.description', 'Seragam')
        ->set('items.0.amount', '200.000')
        ->call('addItem')
        ->set('items.1.description', 'Buku')
        ->set('items.1.amount', '50000')
        ->set('due_date', '2026-11-20')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $invoice = Invoice::where('source', 'manual')->latest('id')->first();
    expect($invoice->total)->toBe(250000);

    Livewire::test(InvoiceShow::class, ['invoice' => $invoice])->call('void')->assertHasErrors('reason');
    Livewire::test(InvoiceShow::class, ['invoice' => $invoice])->set('reason', 'Salah input')->call('void')->assertHasNoErrors();
    expect($invoice->fresh()->status)->toBe('void');
});

it('tunai lewat layar menghitung kembalian dan menolak uang kurang', function () {
    Livewire::test(CashPayment::class)
        ->call('pickStudent', $this->student->uuid)
        ->assertSet('amount', '625000')
        ->set('received', '700.000')
        ->assertSee('Rp 75.000')
        ->set('received', '100000')
        ->call('record')
        ->assertHasErrors('received')
        ->set('received', '700000')
        ->call('record')
        ->assertRedirect();

    expect($this->invoice->fresh()->status)->toBe('paid');
});

it('penugasan dan diskon lewat tab santri, hanya untuk yang berhak mengubah', function () {
    Livewire::test(StudentFinance::class, ['student' => $this->student])
        ->set('discount_type', 'percent')->set('discount_value', '150')->set('discount_reason', 'Yatim')->set('discount_from', '2026-01-01')
        ->call('saveDiscount')
        ->assertHasErrors('discount_value')
        ->set('discount_value', '50')
        ->call('saveDiscount')
        ->assertHasNoErrors()
        ->assertSee('50%');

    expect(StudentDiscount::first()->approved_by)->toBe($this->bendahara->id);

    $this->actingAs(financeStaff('admin_akademik'));
    Livewire::test(StudentFinance::class, ['student' => $this->student])->assertDontSee('Tambah Diskon')->call('saveDiscount')->assertForbidden();
});

it('laporan kas menjumlah per rentang dan mencatat pengeluaran', function () {
    app(RecordCashPayment::class)->handle([$this->invoice->id], 625000, $this->bendahara->id);
    $out = CashCategory::where('name', 'Operasional')->first();

    Livewire::test(CashReport::class)
        ->assertSee('Rp 625.000')
        ->call('openForm')
        ->set('form_category', (string) $out->id)
        ->set('form_amount', '100.000')
        ->set('form_description', 'Listrik')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Listrik')
        ->assertViewHas('totalOut', 100000)
        ->set('from', '2020-01-01')->set('to', '2020-01-31')
        ->assertViewHas('totalIn', 0);

    expect(CashTransaction::where('type', 'out')->value('amount'))->toBe(100000);
});

it('tunggakan hanya menghitung yang lewat jatuh tempo dan mengabaikan void', function () {
    $this->get(route('staff.arrears.index'))->assertOk()->assertSee('Tidak ada tunggakan');

    $this->travelTo('2026-11-20 09:00:00');
    $this->otherInvoice->update(['status' => 'void']);

    $this->get(route('staff.arrears.index'))->assertSee('Ahmad')->assertDontSee('Budi')->assertSee('Rp 625.000');
    $this->get(route('staff.arrears.export'))->assertOk()->assertDownload('tunggakan.xlsx');
});

it('mengekspor tagihan dan kas', function () {
    $this->get(route('staff.invoices.export'))->assertOk()->assertDownload('tagihan.xlsx');
    $this->get(route('staff.cash.export'))->assertOk()->assertDownload('kas.xlsx');
});

it('mengatur rekening dan kode unik', function () {
    Livewire::test(BankAccounts::class)
        ->call('create')
        ->set('bank_name', 'BCA')->set('account_no', 'abc')->set('account_holder', 'P')
        ->call('save')
        ->assertHasErrors('account_no')
        ->set('account_no', '12345')
        ->call('save')
        ->assertHasNoErrors()
        ->set('uniqueCode', true);

    expect(BankAccount::where('bank_name', 'BCA')->exists())->toBeTrue()
        ->and(Module::where('module_key', 'payment_manual')->value('settings')['unique_code'])->toBeTrue();
});

it('job harian menagih mulai H-7 sebelum jatuh tempo dan aman dijalankan ulang', function () {
    $type = FeeType::create(['code' => 'SPP', 'name' => 'SPP', 'category' => 'monthly', 'due_day' => 20]);
    FeeRate::create(['fee_type_id' => $type->id, 'scope' => 'default', 'amount' => 100000, 'valid_from' => '2026-01-01']);

    $this->travelTo('2026-11-05 01:00:00');
    expect(app(GenerateMonthlyInvoices::class)->handle())->toBeNull();

    $this->travelTo('2026-11-13 01:00:00');
    expect(app(GenerateMonthlyInvoices::class)->handle()['invoices'])->toBe(2);
    expect(app(GenerateMonthlyInvoices::class)->handle()['invoices'])->toBe(0);
});

it('impor tunggakan awal melaporkan baris salah lalu membuat tagihan', function () {
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray([
        ArrearsImport::HEADINGS,
        ['A1', 'Tunggakan SPP lama', 300000, '31/10/2026'],
        ['TIDAKADA', 'X', 100, '31/10/2026'],
        ['A1', '', 0, 'salah'],
    ], null, 'A1', true);
    $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    Livewire::test(ArrearsImport::class)
        ->set('file', File::createWithContent('t.xlsx', file_get_contents($path)))
        ->call('check')
        ->assertSee('NIS tidak ditemukan.')
        ->assertSee('Keterangan wajib diisi.')
        ->assertSee('1 baris siap')
        ->call('import')
        ->assertSee('1 tunggakan awal dibuat');

    expect(Invoice::where('total', 300000)->first()->status)->toBe('overdue');
});
