<?php

use App\Modules\Core\Actions\SaveIntegration;
use App\Modules\Core\Models\Module;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\WebhookEvent;
use App\Modules\Finance\Actions\ApplySettlement;
use App\Modules\Finance\Actions\CreateManualInvoice;
use App\Modules\Finance\Actions\FinanceException;
use App\Modules\Finance\Models\CashTransaction;
use App\Modules\Finance\Models\Payment;
use App\Modules\Payment\Actions\CancelOnlinePayment;
use App\Modules\Payment\Actions\CreateOnlinePayment;
use App\Modules\Payment\Actions\SyncPayment;
use App\Modules\Payment\Livewire\Reconciliation;
use App\Modules\Payment\Support\MidtransConfig;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;

const SERVER_KEY = 'SB-Mid-server-uji';

function notification(Payment $payment, array $overrides = []): array
{
    $data = array_merge([
        'order_id' => $payment->gateway_order_id,
        'status_code' => '200',
        'gross_amount' => $payment->gross_amount.'.00',
        'transaction_status' => 'settlement',
        'transaction_id' => 'trx-1',
        'payment_type' => 'bca_va',
    ], $overrides);
    $data['signature_key'] = hash('sha512', $data['order_id'].$data['status_code'].$data['gross_amount'].SERVER_KEY);

    return $data;
}

function midtransSettings(array $settings = []): void
{
    app(SaveIntegration::class)->handle('midtrans', 'sandbox', ['server_key' => SERVER_KEY, 'client_key' => 'SB-Mid-client-uji'], $settings, true);
    app(MidtransConfig::class)->forget();
}

beforeEach(function () {
    (new ModuleSeeder)->run();
    (new RoleSeeder)->run();
    Module::where('module_key', 'payment_midtrans')->update(['is_enabled' => true]);
    $this->travelTo('2026-11-02 09:00:00');
    midtransSettings();
    $this->student = Student::create(['nis' => 'A1', 'name' => 'Ahmad', 'gender' => 'L']);
    $this->invoice = app(CreateManualInvoice::class)->handle($this->student, [['description' => 'SPP', 'amount' => 625000]], '2026-11-10');
    Http::preventStrayRequests();
    // Http::fake menumpuk dan aturan pertama yang menang, jadi jawaban diatur lewat satu daftar yang bisa diganti.
    $this->stubs = [];
    Http::fake(function (HttpRequest $request) {
        foreach ($this->stubs as $pattern => $response) {
            if (Str::is($pattern, $request->url())) {
                return $response;
            }
        }

        return str_contains($request->url(), '/snap/')
            ? Http::response(['token' => 'tok-1', 'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/x'], 201)
            : Http::response(['status_code' => '200']);
    });
});

function onlinePayment($context): Payment
{
    return app(CreateOnlinePayment::class)->handle(collect([$context->invoice]), null, null, 'https://sanad.test/selesai');
}

it('membuat Snap dengan order_id, nominal, kanal terpilih, dan basic auth', function () {
    midtransSettings(['channels' => ['qris', 'store']]);

    $payment = onlinePayment($this);

    expect($payment->gateway_order_id)->toMatch('/^'.$payment->id.'-[a-z0-9]{6}$/')
        ->and($payment->gross_amount)->toBe(625000)
        ->and($payment->gateway_payload['snap_token'])->toBe('tok-1');

    Http::assertSent(function (HttpRequest $request) use ($payment) {
        return $request->url() === 'https://app.sandbox.midtrans.com/snap/v1/transactions'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode(SERVER_KEY.':'))
            && $request['transaction_details']['gross_amount'] === 625000
            && $request['transaction_details']['order_id'] === $payment->gateway_order_id
            && $request['enabled_payments'] === ['other_qris', 'indomaret', 'alfamart'];
    });
});

it('memakai alamat produksi pada mode production', function () {
    app(SaveIntegration::class)->handle('midtrans', 'production', ['server_key' => SERVER_KEY, 'client_key' => 'c'], [], true);
    app(MidtransConfig::class)->forget();

    onlinePayment($this);

    Http::assertSent(fn (HttpRequest $request) => str_starts_with($request->url(), 'https://app.midtrans.com/'));
});

it('biaya admin mode passed masuk gross tetapi invoice hanya bertambah sebesar tagihan', function () {
    midtransSettings(['fee_mode' => 'passed', 'admin_fee' => 4000]);

    $payment = onlinePayment($this);
    expect($payment->gross_amount)->toBe(629000)->and($payment->amount)->toBe(625000)->and($payment->admin_fee)->toBe(4000);

    $this->postJson('/webhooks/midtrans', notification($payment))->assertOk();

    expect($this->invoice->fresh()->paid_total)->toBe(625000)
        ->and($this->invoice->fresh()->status)->toBe('paid')
        ->and(CashTransaction::sum('amount'))->toEqual(625000);
});

it('biaya admin mode absorbed ditanggung pesantren', function () {
    midtransSettings(['fee_mode' => 'absorbed', 'admin_fee' => 4000]);

    expect(onlinePayment($this)->gross_amount)->toBe(625000);
});

it('kegagalan Snap menutup pembayaran dan tagihan bisa dibayar lagi', function () {
    $this->stubs['*'] = Http::response(['error_messages' => ['x']], 500);

    expect(fn () => onlinePayment($this))->toThrow(FinanceException::class, 'sedang bermasalah');

    expect(Payment::first()->status)->toBe('failed');

    $this->stubs = [];
    expect(onlinePayment($this)->status)->toBe('pending');
});

it('webhook sah melunasi tagihan; status akhir tidak datang dari browser', function () {
    $payment = onlinePayment($this);

    expect($this->invoice->fresh()->paid_total)->toBe(0);

    $this->postJson('/webhooks/midtrans', notification($payment))->assertOk();

    expect($payment->fresh()->status)->toBe('settlement')
        ->and($payment->fresh()->channel)->toBe('bca_va')
        ->and($this->invoice->fresh()->status)->toBe('paid')
        ->and(WebhookEvent::first()->processed_at)->not->toBeNull()
        ->and(WebhookEvent::first()->signature_valid)->toBeTrue();
});

it('webhook tanda tangan salah ditolak 403, tercatat, dan tidak berefek', function () {
    $payment = onlinePayment($this);
    $forged = notification($payment);
    $forged['signature_key'] = str_repeat('a', 128);

    $this->postJson('/webhooks/midtrans', $forged)->assertForbidden();

    expect($payment->fresh()->status)->toBe('pending')
        ->and($this->invoice->fresh()->paid_total)->toBe(0)
        ->and(WebhookEvent::first()->signature_valid)->toBeFalse()
        ->and(WebhookEvent::first()->processed_at)->toBeNull();
});

it('webhook ganda tidak menggandakan pembayaran', function () {
    $payment = onlinePayment($this);

    foreach (range(1, 3) as $attempt) {
        $this->postJson('/webhooks/midtrans', notification($payment))->assertOk();
    }

    expect(WebhookEvent::count())->toBe(1)
        ->and($this->invoice->fresh()->paid_total)->toBe(625000)
        ->and(CashTransaction::count())->toBe(1);
});

it('nominal webhook berbeda tidak melunasi, ditandai perlu ditinjau', function () {
    $payment = onlinePayment($this);
    $tampered = notification($payment, ['gross_amount' => '1000.00']);

    $this->postJson('/webhooks/midtrans', $tampered)->assertOk();

    expect($payment->fresh()->status)->toBe('pending')
        ->and($payment->fresh()->needs_review)->toBeTrue()
        ->and($this->invoice->fresh()->paid_total)->toBe(0);
});

it('memetakan status Midtrans sesuai PRD 8.5', function (array $data, string $expected) {
    $payment = onlinePayment($this);

    $this->postJson('/webhooks/midtrans', notification($payment, $data))->assertOk();

    expect($payment->fresh()->status)->toBe($expected);
})->with([
    'capture lolos' => [['transaction_status' => 'capture', 'fraud_status' => 'accept'], 'settlement'],
    'capture ditantang' => [['transaction_status' => 'capture', 'fraud_status' => 'challenge'], 'pending'],
    'pending' => [['transaction_status' => 'pending', 'status_code' => '201'], 'pending'],
    'expire' => [['transaction_status' => 'expire', 'status_code' => '407'], 'expired'],
    'deny' => [['transaction_status' => 'deny', 'status_code' => '202'], 'failed'],
    'cancel' => [['transaction_status' => 'cancel', 'status_code' => '202'], 'failed'],
    'failure' => [['transaction_status' => 'failure', 'status_code' => '202'], 'failed'],
]);

it('notifikasi terlambat tidak memundurkan status lunas', function () {
    $payment = onlinePayment($this);
    $this->postJson('/webhooks/midtrans', notification($payment))->assertOk();
    $this->postJson('/webhooks/midtrans', notification($payment, ['transaction_status' => 'pending', 'status_code' => '201']))->assertOk();
    $this->postJson('/webhooks/midtrans', notification($payment, ['transaction_status' => 'expire', 'status_code' => '407']))->assertOk();

    expect($payment->fresh()->status)->toBe('settlement')->and($this->invoice->fresh()->status)->toBe('paid');
});

it('kedaluwarsa mengembalikan tagihan agar bisa dibayar lagi', function () {
    $payment = onlinePayment($this);
    $this->postJson('/webhooks/midtrans', notification($payment, ['transaction_status' => 'expire', 'status_code' => '407']))->assertOk();

    expect($payment->fresh()->status)->toBe('expired')->and($this->invoice->fresh()->status)->toBe('unpaid');

    expect(onlinePayment($this)->id)->not->toBe($payment->id);
});

it('refund membuka kembali tagihan dan minta bendahara mencatat kas', function () {
    $payment = onlinePayment($this);
    $this->postJson('/webhooks/midtrans', notification($payment))->assertOk();
    $this->postJson('/webhooks/midtrans', notification($payment, ['transaction_status' => 'refund']))->assertOk();

    expect($payment->fresh()->status)->toBe('refunded')
        ->and($payment->fresh()->needs_review)->toBeTrue()
        ->and($this->invoice->fresh()->paid_total)->toBe(0)
        ->and(CashTransaction::where('type', 'out')->count())->toBe(0);
});

it('uang masuk setelah wali membatalkan tetap dilunasi bila tagihan masih terbuka', function () {
    $payment = onlinePayment($this);
    app(CancelOnlinePayment::class)->handle($payment);
    expect($payment->status)->toBe('failed');

    $this->postJson('/webhooks/midtrans', notification($payment))->assertOk();

    expect($payment->fresh()->status)->toBe('settlement')->and($this->invoice->fresh()->status)->toBe('paid');
});

it('uang masuk untuk tagihan yang sudah lunas lewat cara lain ditandai untuk ditinjau', function () {
    $payment = onlinePayment($this);
    app(CancelOnlinePayment::class)->handle($payment);

    $other = app(CreateOnlinePayment::class)->handle(collect([$this->invoice]), null, null, 'u');
    app(ApplySettlement::class)->handle($other);

    $this->postJson('/webhooks/midtrans', notification($payment))->assertOk();

    expect($payment->fresh()->status)->toBe('failed')
        ->and($payment->fresh()->needs_review)->toBeTrue()
        ->and($this->invoice->fresh()->paid_total)->toBe(625000);
});

it('menjawab 200 untuk order tak dikenal agar notifikasi uji Midtrans tidak diulang', function () {
    $data = ['order_id' => 'payment_notif_test', 'status_code' => '200', 'gross_amount' => '10000.00', 'transaction_status' => 'settlement'];
    $data['signature_key'] = hash('sha512', $data['order_id'].$data['status_code'].$data['gross_amount'].SERVER_KEY);

    $this->postJson('/webhooks/midtrans', $data)->assertOk();

    expect(WebhookEvent::first()->error)->toBe('Order tidak dikenal.');
});

it('menolak isi webhook yang bukan JSON', function () {
    $this->call('POST', '/webhooks/midtrans', [], [], [], ['CONTENT_TYPE' => 'application/json'], 'bukan json')->assertStatus(400);
});

it('sinkron menarik status dari Get Status API dan job hanya menyentuh yang menggantung sejam', function () {
    $payment = onlinePayment($this);
    $this->stubs['https://api.sandbox.midtrans.com/v2/*'] = Http::response(['transaction_status' => 'settlement', 'status_code' => '200', 'order_id' => $payment->gateway_order_id, 'gross_amount' => '625000.00']);

    expect(app(SyncPayment::class)->pending())->toBe(0);
    expect($payment->fresh()->status)->toBe('pending');

    $this->travel(2)->hours();
    expect(app(SyncPayment::class)->pending())->toBe(1);

    expect($payment->fresh()->status)->toBe('settlement')
        ->and($payment->fresh()->last_synced_at)->not->toBeNull()
        ->and($this->invoice->fresh()->status)->toBe('paid');
});

it('sinkron mengenali order yang tidak ada di Midtrans', function () {
    $payment = onlinePayment($this);
    $this->stubs['https://api.sandbox.midtrans.com/v2/*'] = Http::response(['status_code' => '404', 'status_message' => 'not found']);

    expect(app(SyncPayment::class)->handle($payment))->toBeNull();
    expect($payment->fresh()->status)->toBe('pending');
});

it('layar rekonsiliasi: menyinkron, menandai selisih, hanya bendahara yang bertindak', function () {
    $bendahara = tap(User::create(['name' => 'B', 'email' => 'b@uji.test', 'type' => 'staff']))->assignRole('bendahara');
    $payment = onlinePayment($this);
    $this->postJson('/webhooks/midtrans', notification($payment, ['gross_amount' => '1.00']));

    $this->actingAs($bendahara);
    $this->stubs['https://api.sandbox.midtrans.com/v2/*'] = Http::response(['transaction_status' => 'pending', 'status_code' => '201', 'gross_amount' => '625000.00']);

    Livewire::test(Reconciliation::class)
        ->assertSee('tidak sama dengan tagihan')
        ->call('sync', $payment->uuid)
        ->assertDispatched('toast')
        ->call('resolve', $payment->uuid)
        ->assertHasErrors('resolveNote')
        ->set('resolveNote', 'Sudah dicek ke Midtrans')
        ->call('resolve', $payment->uuid)
        ->assertHasNoErrors();

    expect($payment->fresh()->needs_review)->toBeFalse();

    $this->actingAs(tap(User::create(['name' => 'K', 'email' => 'k@uji.test', 'type' => 'staff']))->assignRole('kasir'));
    $this->get(route('staff.reconciliation'))->assertForbidden();
});
