<?php

use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Models\Dormitory;
use App\Modules\Core\Models\Student;
use App\Modules\Finance\Actions\ApplyLateFees;
use App\Modules\Finance\Actions\CreateManualInvoice;
use App\Modules\Finance\Actions\FinanceException;
use App\Modules\Finance\Actions\GenerateInvoices;
use App\Modules\Finance\Actions\ResolveFeeRate;
use App\Modules\Finance\Actions\VoidInvoice;
use App\Modules\Finance\Models\FeeRate;
use App\Modules\Finance\Models\FeeType;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Models\InvoiceItem;
use App\Modules\Finance\Models\StudentDiscount;
use App\Modules\Finance\Models\StudentFeeAssignment;
use App\Modules\Finance\Support\ArrearsQuery;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->period = CarbonImmutable::parse('2026-11-01');
    $this->travelTo('2026-11-02 09:00:00');

    $this->class8 = Classroom::create(['name' => 'Kelas 8', 'level' => '8']);
    $this->dorm = Dormitory::create(['name' => 'Asrama Putra', 'gender' => 'L']);
    $this->ahmad = Student::create(['nis' => 'A1', 'name' => 'Ahmad', 'gender' => 'L', 'classroom_id' => $this->class8->id, 'dormitory_id' => $this->dorm->id]);

    $this->spp = FeeType::create(['code' => 'SPP', 'name' => 'SPP', 'category' => 'monthly', 'due_day' => 10]);
    $this->makan = FeeType::create(['code' => 'MAKAN', 'name' => 'Uang Makan', 'category' => 'monthly', 'due_day' => 10, 'late_fee_type' => 'fixed', 'late_fee_value' => 10000, 'late_fee_after_days' => 7]);
    FeeRate::create(['fee_type_id' => $this->spp->id, 'scope' => 'classroom', 'scope_id' => $this->class8->id, 'amount' => 350000, 'valid_from' => '2026-01-01']);
    FeeRate::create(['fee_type_id' => $this->makan->id, 'scope' => 'default', 'amount' => 450000, 'valid_from' => '2026-01-01']);
    StudentDiscount::create(['student_id' => $this->ahmad->id, 'fee_type_id' => $this->spp->id, 'type' => 'percent', 'value' => 50, 'reason' => 'Yatim', 'valid_from' => '2026-01-01']);
});

it('menghasilkan tagihan persis seperti contoh PRD Sistem 8.2', function () {
    app(GenerateInvoices::class)->run($this->period);

    $invoice = Invoice::with('items')->first();

    expect($invoice->total)->toBe(625000)
        ->and($invoice->subtotal)->toBe(800000)
        ->and($invoice->discount_total)->toBe(175000)
        ->and($invoice->status)->toBe('unpaid')
        ->and($invoice->due_date->toDateString())->toBe('2026-11-10')
        ->and($invoice->items->firstWhere('fee_type_id', $this->spp->id)->amount)->toBe(175000)
        ->and($invoice->items->firstWhere('fee_type_id', $this->spp->id)->period_key)->toBe('2026-11')
        ->and($invoice->items->firstWhere('fee_type_id', $this->makan->id)->amount)->toBe(450000);
});

it('menambahkan denda tetap sekali setelah lewat 7 hari tanpa diskon, sesuai contoh PRD', function () {
    app(GenerateInvoices::class)->run($this->period);

    $this->travelTo('2026-11-17 01:00:00');
    expect(app(ApplyLateFees::class)->handle())->toBe(['overdue' => 1, 'late_fees' => 0]);
    expect(Invoice::first()->total)->toBe(625000)->and(Invoice::first()->status)->toBe('overdue');

    $this->travelTo('2026-11-18 01:00:00');
    expect(app(ApplyLateFees::class)->handle()['late_fees'])->toBe(1);
    expect(Invoice::first()->total)->toBe(635000)->and(Invoice::first()->late_fee_total)->toBe(10000);

    $this->travelTo('2026-11-25 01:00:00');
    app(ApplyLateFees::class)->handle();
    expect(Invoice::first()->total)->toBe(635000);
});

it('memakai tanggal pesantren, bukan UTC, untuk batas denda dan lama tunggakan', function () {
    $invoice = app(CreateManualInvoice::class)->handle($this->ahmad, [['description' => 'X', 'amount' => 1000, 'fee_type_id' => $this->makan->id]], '2026-11-10');

    // 17 November 23.30 WIB masih hari ketujuh; 18 November 00.30 WIB sudah lewat.
    $this->travelTo('2026-11-17 16:30:00');
    app(ApplyLateFees::class)->handle();
    expect($invoice->fresh()->late_fee_total)->toBe(0);

    $this->travelTo('2026-11-17 17:30:00');
    app(ApplyLateFees::class)->handle();
    expect($invoice->fresh()->late_fee_total)->toBe(10000)
        ->and(ArrearsQuery::rows()->first()['days'])->toBe(8);
});

it('menghitung denda persen dari nominal item', function () {
    $this->makan->update(['late_fee_type' => 'percent', 'late_fee_value' => 2, 'late_fee_after_days' => 0]);
    app(GenerateInvoices::class)->run($this->period);

    $this->travelTo('2026-11-11 01:00:00');
    app(ApplyLateFees::class)->handle();

    expect(Invoice::first()->late_fee_total)->toBe(9000);
});

it('idempoten: menjalankan generate dua kali tidak menduplikasi', function () {
    $first = app(GenerateInvoices::class)->run($this->period);
    $second = app(GenerateInvoices::class)->run($this->period);

    expect($first['invoices'])->toBe(1)
        ->and($second['invoices'])->toBe(0)
        ->and(collect($second['skipped'])->pluck('reason')->unique()->all())->toBe(['Sudah ditagih'])
        ->and(Invoice::count())->toBe(1)
        ->and(InvoiceItem::count())->toBe(2);
});

it('database menolak item ganda walau logika aplikasi terlewati', function () {
    app(GenerateInvoices::class)->run($this->period);
    $item = InvoiceItem::first();

    expect(fn () => DB::transaction(fn () => InvoiceItem::create([...$item->only(['invoice_id', 'student_id', 'fee_type_id', 'period_key', 'description', 'unit_price', 'amount'])])))
        ->toThrow(QueryException::class, 'invoice_items_student_id_fee_type_id_period_key_unique');
});

it('pratinjau sama dengan hasil eksekusi dan tidak menyimpan apa pun', function () {
    $preview = app(GenerateInvoices::class)->preview($this->period);

    expect(Invoice::count())->toBe(0)->and(DB::table('document_counters')->count())->toBe(0);

    $result = app(GenerateInvoices::class)->run($this->period);

    expect($result)->toBe($preview)->and($preview['total'])->toBe(625000);
});

it('melaporkan santri tanpa tarif dan tidak membuat tagihan nol', function () {
    FeeRate::query()->delete();

    $result = app(GenerateInvoices::class)->run($this->period);

    expect($result['invoices'])->toBe(0)
        ->and(collect($result['skipped'])->pluck('reason')->unique()->all())->toBe(['Tarif belum diatur'])
        ->and(Invoice::count())->toBe(0);
});

it('mengikuti urutan prioritas tarif PRD 8.1', function () {
    $resolve = fn () => app(ResolveFeeRate::class)->handle($this->ahmad->fresh('classroom'), $this->spp, $this->period);
    FeeRate::query()->where('fee_type_id', $this->spp->id)->delete();

    FeeRate::create(['fee_type_id' => $this->spp->id, 'scope' => 'default', 'amount' => 100, 'valid_from' => '2026-01-01']);
    expect($resolve()['amount'])->toBe(100);

    FeeRate::create(['fee_type_id' => $this->spp->id, 'scope' => 'level', 'scope_level' => '8', 'amount' => 200, 'valid_from' => '2026-01-01']);
    expect($resolve()['amount'])->toBe(200);

    FeeRate::create(['fee_type_id' => $this->spp->id, 'scope' => 'dormitory', 'scope_id' => $this->dorm->id, 'amount' => 300, 'valid_from' => '2026-01-01']);
    expect($resolve()['amount'])->toBe(300);

    FeeRate::create(['fee_type_id' => $this->spp->id, 'scope' => 'classroom', 'scope_id' => $this->class8->id, 'amount' => 400, 'valid_from' => '2026-01-01']);
    expect($resolve()['amount'])->toBe(400);

    FeeRate::create(['fee_type_id' => $this->spp->id, 'scope' => 'student', 'scope_id' => $this->ahmad->id, 'amount' => 500, 'valid_from' => '2026-01-01']);
    expect($resolve()['amount'])->toBe(500);

    StudentFeeAssignment::create(['student_id' => $this->ahmad->id, 'fee_type_id' => $this->spp->id, 'custom_amount' => 600, 'start_month' => '2026-01-01']);
    expect($resolve()['amount'])->toBe(600);
});

it('mengabaikan tarif di luar masa berlaku dan memakai yang berlaku', function () {
    FeeRate::query()->where('fee_type_id', $this->spp->id)->delete();
    FeeRate::create(['fee_type_id' => $this->spp->id, 'scope' => 'default', 'amount' => 100, 'valid_from' => '2025-01-01', 'valid_to' => '2026-10-31']);
    FeeRate::create(['fee_type_id' => $this->spp->id, 'scope' => 'default', 'amount' => 200, 'valid_from' => '2026-11-01']);

    expect(app(ResolveFeeRate::class)->handle($this->ahmad, $this->spp, $this->period)['amount'])->toBe(200)
        ->and(app(ResolveFeeRate::class)->handle($this->ahmad, $this->spp, CarbonImmutable::parse('2026-10-01'))['amount'])->toBe(100);
});

it('mengeluarkan santri dari jenis wajib lewat penugasan nonaktif', function () {
    StudentFeeAssignment::create(['student_id' => $this->ahmad->id, 'fee_type_id' => $this->makan->id, 'start_month' => '2026-01-01', 'is_active' => false]);

    app(GenerateInvoices::class)->run($this->period);

    expect(Invoice::first()->total)->toBe(175000);
});

it('hanya menagih jenis opsional kepada santri yang punya penugasan', function () {
    $opsional = FeeType::create(['code' => 'LES', 'name' => 'Les', 'category' => 'monthly', 'is_mandatory' => false]);
    FeeRate::create(['fee_type_id' => $opsional->id, 'scope' => 'default', 'amount' => 50000, 'valid_from' => '2026-01-01']);
    $lain = Student::create(['nis' => 'B1', 'name' => 'Budi', 'gender' => 'L']);
    StudentFeeAssignment::create(['student_id' => $lain->id, 'fee_type_id' => $opsional->id, 'start_month' => '2026-01-01']);

    app(GenerateInvoices::class)->run($this->period);

    expect(InvoiceItem::where('fee_type_id', $opsional->id)->pluck('student_id')->all())->toBe([$lain->id]);
});

it('tidak menagih santri yang tidak aktif dan bisa dibatasi per kelas', function () {
    Student::create(['nis' => 'C1', 'name' => 'Lulus', 'gender' => 'L', 'status' => 'graduated']);
    $lain = Classroom::create(['name' => 'Kelas 9']);
    Student::create(['nis' => 'D1', 'name' => 'Kelas Lain', 'gender' => 'L', 'classroom_id' => $lain->id]);

    app(GenerateInvoices::class)->run($this->period, null, $this->class8->id);

    expect(Invoice::pluck('student_id')->all())->toBe([$this->ahmad->id]);
});

it('membatasi diskon agar tidak melebihi tarif dan melunasi otomatis bila total nol', function () {
    StudentDiscount::create(['student_id' => $this->ahmad->id, 'fee_type_id' => null, 'type' => 'fixed', 'value' => 900000, 'reason' => 'Beasiswa', 'valid_from' => '2026-01-01']);

    app(GenerateInvoices::class)->run($this->period);

    expect(Invoice::first()->total)->toBe(0)->and(Invoice::first()->status)->toBe('paid');
});

it('jenis baru pada periode yang sama masuk ke invoice terbuka, bukan invoice baru', function () {
    app(GenerateInvoices::class)->run($this->period);
    $baru = FeeType::create(['code' => 'ASRAMA', 'name' => 'Asrama', 'category' => 'monthly']);
    FeeRate::create(['fee_type_id' => $baru->id, 'scope' => 'default', 'amount' => 100000, 'valid_from' => '2026-01-01']);

    app(GenerateInvoices::class)->run($this->period);

    expect(Invoice::count())->toBe(1)->and(Invoice::first()->total)->toBe(725000);
});

it('tagihan tahunan memakai kunci tahun dan hanya bila dipilih', function () {
    $tahunan = FeeType::create(['code' => 'TAHUNAN', 'name' => 'Uang Tahunan', 'category' => 'yearly', 'due_day' => 15]);
    FeeRate::create(['fee_type_id' => $tahunan->id, 'scope' => 'default', 'amount' => 1000000, 'valid_from' => '2026-01-01']);

    app(GenerateInvoices::class)->run($this->period);
    expect(InvoiceItem::where('fee_type_id', $tahunan->id)->exists())->toBeFalse();

    app(GenerateInvoices::class)->run($this->period, [$tahunan->id]);
    app(GenerateInvoices::class)->run(CarbonImmutable::parse('2026-12-01'), [$tahunan->id]);

    expect(InvoiceItem::where('fee_type_id', $tahunan->id)->pluck('period_key')->all())->toBe(['2026']);
});

it('nomor invoice unik dan berurutan', function () {
    $student = Student::create(['nis' => 'E1', 'name' => 'Eko', 'gender' => 'L']);

    $first = app(CreateManualInvoice::class)->handle($student, [['description' => 'Seragam', 'amount' => 200000]], '2026-11-20');
    $second = app(CreateManualInvoice::class)->handle($student, [['description' => 'Buku', 'amount' => 50000]], '2026-11-20');

    expect($first->invoice_no)->toBe('INV-202611-00001')->and($second->invoice_no)->toBe('INV-202611-00002')->and($first->source)->toBe('manual');
});

it('void wajib alasan, menolak yang sudah dibayar, dan bisa dibuat ulang saat generate', function () {
    app(GenerateInvoices::class)->run($this->period);
    $invoice = Invoice::first();

    expect(fn () => app(VoidInvoice::class)->handle($invoice, ' '))->toThrow(FinanceException::class, 'Alasan');

    app(VoidInvoice::class)->handle($invoice, 'Salah generate');
    expect($invoice->status)->toBe('void')->and($invoice->void_reason)->toBe('Salah generate');

    app(GenerateInvoices::class)->run($this->period);
    expect(Invoice::where('status', 'unpaid')->first()->total)->toBe(625000);

    $paid = Invoice::where('status', 'unpaid')->first();
    $paid->update(['paid_total' => 1000]);
    expect(fn () => app(VoidInvoice::class)->handle($paid, 'x'))->toThrow(FinanceException::class, 'sudah dibayar');
});

it('database menolak nominal negatif', function () {
    expect(fn () => DB::transaction(fn () => FeeRate::create(['fee_type_id' => $this->spp->id, 'scope' => 'default', 'amount' => -1, 'valid_from' => '2026-01-01'])))->toThrow(QueryException::class, 'fee_rates_amount_positive');
    expect(fn () => DB::transaction(fn () => StudentDiscount::create(['student_id' => $this->ahmad->id, 'type' => 'percent', 'value' => 150, 'reason' => 'x', 'valid_from' => '2026-01-01'])))->toThrow(QueryException::class, 'student_discounts_value');
});
