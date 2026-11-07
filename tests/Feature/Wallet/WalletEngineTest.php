<?php

use App\Modules\Core\Models\Guardian;
use App\Modules\Core\Models\InAppNotification;
use App\Modules\Core\Models\Module;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\ModuleRegistry;
use App\Modules\Finance\Actions\ApplySettlement;
use App\Modules\Finance\Actions\FinanceException;
use App\Modules\Finance\Actions\RefundPayment;
use App\Modules\Finance\Actions\RejectManualPayment;
use App\Modules\Finance\Actions\SubmitPaymentProof;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Models\CashCategory;
use App\Modules\Finance\Models\CashTransaction;
use App\Modules\Finance\Models\Payment;
use App\Modules\Notification\Models\GuardianNotificationPref;
use App\Modules\Notification\Models\NotificationLog;
use App\Modules\Pos\Actions\CheckoutOrder;
use App\Modules\Pos\Actions\VoidOrder;
use App\Modules\Pos\Models\Merchant;
use App\Modules\Pos\Models\MerchantLedgerEntry;
use App\Modules\Pos\Models\MerchantProduct;
use App\Modules\Pos\Models\PosOrder;
use App\Modules\Wallet\Actions\AdjustWallet;
use App\Modules\Wallet\Actions\CreateTopup;
use App\Modules\Wallet\Actions\IssueCard;
use App\Modules\Wallet\Actions\ManageCard;
use App\Modules\Wallet\Actions\ReconcileWallets;
use App\Modules\Wallet\Actions\RecordCashTopup;
use App\Modules\Wallet\Actions\WithdrawBalance;
use App\Modules\Wallet\Models\CardScan;
use App\Modules\Wallet\Models\Device;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\WalletTransaction;
use App\Modules\Wallet\Support\QrToken;
use App\Modules\Wallet\Support\WalletException;
use Database\Seeders\FinanceSeeder;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    (new ModuleSeeder)->run();
    (new RoleSeeder)->run();
    (new FinanceSeeder)->run();
    Module::whereIn('module_key', ['wallet', 'card', 'pos'])->update(['is_enabled' => true]);
    app(ModuleRegistry::class)->forget();
    Storage::fake('local');
    Queue::fake();
    $this->travelTo('2026-11-02 03:00:00');

    $this->staff = tap(User::create(['name' => 'Bendahara', 'email' => 'b@uji.test', 'type' => 'staff']))->assignRole('bendahara');
    $this->student = Student::create(['nis' => 'A1', 'name' => 'Ahmad', 'gender' => 'L']);
    $this->wallet = Wallet::forStudent($this->student);
    $this->card = app(IssueCard::class)->handle($this->student, '04 a1 b2');
    $this->merchant = Merchant::create(['name' => 'Warung A', 'payment_modes' => ['card', 'qr']]);
    $this->product = MerchantProduct::create(['merchant_id' => $this->merchant->id, 'name' => 'Roti', 'price' => 5000]);
});

function topUp($context, int $amount = 100000): void
{
    app(RecordCashTopup::class)->handle($context->student, $amount, $context->staff->id);
    $context->wallet->refresh();
}

function buy($context, int $qty = 1, ?string $credential = '04A1B2', ?string $pin = null, ?string $key = null)
{
    return app(CheckoutOrder::class)->handle($context->merchant, [$context->product->id => $qty], null, $credential, $pin, $key ?? (string) Str::uuid(), $context->staff->id);
}

it('saldo tidak bisa negatif walau baris diubah langsung di database', function () {
    expect(fn () => DB::transaction(fn () => DB::table('wallets')->where('id', $this->wallet->id)->update(['balance' => -1])))
        ->toThrow(QueryException::class);
});

it('buku besar tidak bisa diubah atau dihapus', function () {
    topUp($this);
    $entry = WalletTransaction::first();

    expect(fn () => DB::transaction(fn () => DB::table('wallet_transactions')->where('id', $entry->id)->update(['amount' => 1])))->toThrow(QueryException::class)
        ->and(fn () => DB::transaction(fn () => DB::table('wallet_transactions')->where('id', $entry->id)->delete()))->toThrow(QueryException::class);
});

it('top-up tunai: saldo bertambah, kas tercatat, sesuai buku besar', function () {
    topUp($this, 50000);

    expect($this->wallet->balance)->toBe(50000)
        ->and((int) WalletTransaction::where('type', 'topup')->sum('amount'))->toBe(50000)
        ->and(CashTransaction::where('category_id', CashCategory::named(CashCategory::WALLET_TOPUP, 'in')->id)->value('amount'))->toBe(50000)
        ->and(app(ReconcileWallets::class)->handle())->toBe([]);
});

it('top-up transfer: saldo baru bertambah setelah verifikasi, ditolak tidak menambah', function () {
    $account = BankAccount::create(['bank_name' => 'B', 'account_no' => '1', 'account_holder' => 'P']);
    $payment = app(CreateTopup::class)->viaTransfer($this->student, 50000, $account, null, null);

    expect($this->wallet->fresh()->balance)->toBe(0);
    app(SubmitPaymentProof::class)->handle($payment, UploadedFile::fake()->image('a.jpg'));
    expect($this->wallet->fresh()->balance)->toBe(0);

    app(RejectManualPayment::class)->handle($payment, 'Buram', $this->staff->id);
    expect($this->wallet->fresh()->balance)->toBe(0);

    app(SubmitPaymentProof::class)->handle($payment->fresh(), UploadedFile::fake()->image('b.jpg'));
    expect(app(ApplySettlement::class)->handle($payment->fresh(), $this->staff->id))->toBeTrue()
        ->and($this->wallet->fresh()->balance)->toBe(50000)
        ->and(app(ApplySettlement::class)->handle($payment->fresh(), $this->staff->id))->toBeFalse()
        ->and($this->wallet->fresh()->balance)->toBe(50000);
});

it('nominal top-up dibatasi', function () {
    $account = BankAccount::create(['bank_name' => 'B', 'account_no' => '1', 'account_holder' => 'P']);

    expect(fn () => app(CreateTopup::class)->viaTransfer($this->student, 5000, $account, null, null))->toThrow(WalletException::class, 'Rp 10.000');
});

it('pengembalian top-up mengurangi saldo, ditolak bila saldonya sudah terpakai', function () {
    topUp($this, 10000);
    $payment = $this->wallet->transactions()->first();
    $paymentModel = Payment::find($payment->ref_id);
    buy($this);

    expect(fn () => app(RefundPayment::class)->handle($paymentModel, 'Salah kirim', $this->staff->id))->toThrow(FinanceException::class, 'tidak cukup');
    expect($paymentModel->fresh()->status)->toBe('settlement');

    topUp($this, 5000);
    app(RefundPayment::class)->handle($paymentModel, 'Salah kirim', $this->staff->id);
    expect($this->wallet->fresh()->balance)->toBe(0)->and($paymentModel->fresh()->status)->toBe('refunded');
});

it('belanja memotong saldo, membuat struk, dan mengkredit merchant dengan komisi', function () {
    topUp($this);
    $this->merchant->update(['commission_percent' => 2]);

    $result = buy($this, 3);

    expect($result->ok)->toBeTrue()->and($result->balance)->toBe(85000)->and($result->order->total)->toBe(15000)
        ->and($result->order->receipt_no)->toStartWith('ST-')
        ->and($this->wallet->fresh()->balance)->toBe(85000)
        ->and($this->merchant->fresh()->balance())->toBe(14700)
        ->and(MerchantLedgerEntry::where('type', 'sale')->value('amount'))->toBe(15000)
        ->and(MerchantLedgerEntry::where('type', 'commission')->value('amount'))->toBe(-300)
        ->and(MerchantLedgerEntry::mismatchedMerchants())->toBe([])
        ->and($result->order->items()->first()->name)->toBe('Roti')
        ->and(CardScan::where('result', 'ok')->count())->toBe(1);
});

it('harga dari server, bukan dari layar; produk merchant lain ditolak', function () {
    topUp($this);
    $other = MerchantProduct::create(['merchant_id' => Merchant::create(['name' => 'Lain'])->id, 'name' => 'X', 'price' => 1000]);

    $result = app(CheckoutOrder::class)->handle($this->merchant, [$other->id => 1], null, '04A1B2', null, 'k1', null);

    expect($result->ok)->toBeFalse()->and($result->code)->toBe('invalid_cart')->and($this->wallet->fresh()->balance)->toBe(100000);
});

it('kunci sama tidak memotong dua kali dan mengembalikan hasil yang sama', function () {
    topUp($this);

    $first = buy($this, 1, key: 'tap-1');
    $second = buy($this, 1, key: 'tap-1');

    expect($first->order->id)->toBe($second->order->id)->and($second->ok)->toBeTrue()->and($second->balance)->toBe(95000)
        ->and($this->wallet->fresh()->balance)->toBe(95000)->and(PosOrder::count())->toBe(1);
});

it('contoh PRD: limit harian 25 ribu, sudah 12 ribu, belanja 15 ribu ditolak dengan sisa', function () {
    topUp($this);
    $this->wallet->update(['daily_limit' => 25000]);
    $this->product->update(['price' => 12000]);
    buy($this);

    $this->product->update(['price' => 15000]);
    $result = buy($this);

    expect($result->ok)->toBeFalse()->and($result->code)->toBe('daily_limit')->and($result->message)->toBe('Limit harian terlampaui (sisa Rp 13.000).')
        ->and($this->wallet->fresh()->balance)->toBe(88000);
});

it('limit harian mengikuti hari pesantren, dan pembatalan mengembalikan limit', function () {
    topUp($this);
    $this->wallet->update(['daily_limit' => 5000]);

    $this->travelTo('2026-11-02 16:30:00');
    $order = buy($this)->order;
    expect(buy($this)->code)->toBe('daily_limit');

    app(VoidOrder::class)->handle($order, 'Salah input', tap(User::create(['name' => 'Adm', 'email' => 'x@uji.test', 'type' => 'staff']))->assignRole('admin'));
    expect(buy($this)->ok)->toBeTrue()->and(buy($this)->code)->toBe('daily_limit');

    $this->travelTo('2026-11-02 17:01:00');
    expect(buy($this)->ok)->toBeTrue();
});

it('limit per transaksi, saldo kurang, dan dompet diblokir ditolak dengan alasan jelas', function () {
    topUp($this, 10000);

    $this->wallet->update(['per_transaction_limit' => 4000]);
    expect(buy($this)->code)->toBe('per_transaction_limit');

    $this->wallet->update(['per_transaction_limit' => null]);
    expect(buy($this, 3)->message)->toBe('Saldo kurang (sisa Rp 10.000).');

    $this->wallet->update(['status' => 'blocked']);
    expect(buy($this)->code)->toBe('wallet_blocked');
});

it('PIN diminta hanya di atas ambang; salah 3 kali mengunci 15 menit; tepat di ambang tidak diminta', function () {
    topUp($this);
    app(ManageCard::class)->setPin($this->card, '4827');

    expect(buy($this, 4)->ok)->toBeTrue();
    expect(buy($this, 5)->code)->toBe('pin_required');
    expect(buy($this, 5, pin: '0000')->message)->toBe('PIN salah. Sisa 2 percobaan.');
    expect(buy($this, 5, pin: '0000')->message)->toBe('PIN salah. Sisa 1 percobaan.');
    expect(buy($this, 5, pin: '0000')->code)->toBe('card_locked');

    expect(buy($this, 5, pin: '4827')->code)->toBe('card_locked');
    expect(buy($this, 1)->code)->toBe('card_locked');

    $this->travel(16)->minutes();
    expect(buy($this, 5, pin: '4827')->ok)->toBeTrue();
    expect($this->wallet->fresh()->balance)->toBe(55000);
});

it('merchant bisa menimpa ambang PIN; PIN belum dibuat ditolak dengan arahan', function () {
    topUp($this);
    $this->merchant->update(['pin_threshold' => 1000]);

    expect(buy($this)->code)->toBe('pin_unset');
});

it('PIN mudah ditebak ditolak dan disimpan sebagai hash', function () {
    $manage = app(ManageCard::class);

    foreach (['123', '1111', '1234', '98765', 'abcd'] as $pin) {
        expect(fn () => $manage->setPin($this->card, $pin))->toThrow(WalletException::class);
    }

    $manage->setPin($this->card, '4827');
    expect($this->card->fresh()->pin_hash)->not->toContain('4827');
});

it('satu santri satu kartu aktif; UID ganda ditolak', function () {
    expect(fn () => app(IssueCard::class)->handle($this->student, 'BARU'))->toThrow(WalletException::class, 'kartu aktif');

    $other = Student::create(['nis' => 'A2', 'name' => 'Budi', 'gender' => 'L']);
    expect(fn () => app(IssueCard::class)->handle($other, '04-A1-B2'))->toThrow(WalletException::class, 'sudah dipakai');
});

it('kartu hilang: langsung ditolak, saldo tetap, kartu pengganti jalan dengan PIN yang sama', function () {
    topUp($this);
    app(ManageCard::class)->setPin($this->card, '4827');
    app(ManageCard::class)->block($this->card, 'lost', 'Hilang');

    $blocked = buy($this);
    expect($blocked->code)->toBe('card_blocked')->and(CardScan::where('result', 'blocked')->count())->toBe(1)->and($this->wallet->fresh()->balance)->toBe(100000);

    $new = app(ManageCard::class)->replace($this->card->fresh(), 'CC11');
    expect(buy($this, 1, 'CC11')->ok)->toBeTrue()->and($new->pin_hash)->toBe($this->card->fresh()->pin_hash)
        ->and(buy($this, 1, '04A1B2')->code)->toBe('card_blocked')
        ->and(fn () => app(ManageCard::class)->unblock($this->card->fresh()))->toThrow(WalletException::class);
});

it('QR dinamis: sah sekali, ditolak bila dipakai ulang, screenshot lama ditolak, token palsu ditolak', function () {
    topUp($this);

    $token = QrToken::dynamic($this->card);
    expect(buy($this, 1, $token)->ok)->toBeTrue();
    expect(buy($this, 1, $token)->message)->toContain('sudah dipakai');

    $this->travel(30)->seconds();
    $old = QrToken::dynamic($this->card);
    $this->travel(30)->seconds();
    expect(buy($this, 1, $old)->ok)->toBeTrue();

    $this->travel(61)->seconds();
    $stale = QrToken::dynamic($this->card);
    $this->travel(61)->seconds();
    expect(buy($this, 1, $stale)->message)->toContain('kedaluwarsa');

    $forged = 'D1.'.$this->card->uuid.'.'.floor(now()->getTimestamp() / 30).'.'.str_repeat('a', 32);
    expect(buy($this, 1, $forged)->message)->toBe('QR tidak dikenali.');
    expect(buy($this, 1, QrToken::printed($this->card))->message)->toContain('QR cetak');
    expect($this->wallet->fresh()->balance)->toBe(90000);
});

it('kartu tidak dikenal tercatat di log', function () {
    expect(buy($this, 1, 'TIDAKADA')->code)->toBe('card_not_found')->and(CardScan::where('result', 'not_found')->count())->toBe(1);
});

it('void: saldo kembali lewat baris baru, merchant ikut, tidak bisa dua kali', function () {
    topUp($this);
    $this->merchant->update(['commission_percent' => 2]);
    $order = buy($this, 3)->order;
    $admin = tap(User::create(['name' => 'Adm', 'email' => 'x@uji.test', 'type' => 'staff']))->assignRole('admin');

    expect(fn () => app(VoidOrder::class)->handle($order, ' ', $admin))->toThrow(WalletException::class, 'Alasan');

    app(VoidOrder::class)->handle($order, 'Barang kurang', $admin);

    expect($this->wallet->fresh()->balance)->toBe(100000)
        ->and($this->merchant->fresh()->balance())->toBe(0)
        ->and(MerchantLedgerEntry::mismatchedMerchants())->toBe([])
        ->and(WalletTransaction::where('type', 'spend')->count())->toBe(1)
        ->and($order->fresh()->status)->toBe('void');

    expect(fn () => app(VoidOrder::class)->handle($order->fresh(), 'Lagi', $admin))->toThrow(WalletException::class, 'sudah dibatalkan');
    expect($this->wallet->fresh()->balance)->toBe(100000);
});

it('kasir hanya bisa void transaksinya sendiri dalam 10 menit', function () {
    topUp($this);
    $kasir = tap(User::create(['name' => 'K', 'email' => 'k@uji.test', 'type' => 'staff']))->assignRole('kasir');
    $this->merchant->cashiers()->attach($kasir->id);
    $orderLain = buy($this)->order;
    $order = app(CheckoutOrder::class)->handle($this->merchant, [$this->product->id => 1], null, '04A1B2', null, 'k2', $kasir->id)->order;

    expect(fn () => app(VoidOrder::class)->handle($orderLain, 'x', $kasir))->toThrow(WalletException::class);

    $this->travel(11)->minutes();
    expect(fn () => app(VoidOrder::class)->handle($order, 'x', $kasir))->toThrow(WalletException::class, 'pengelola');

    $this->travel(-5)->minutes();
    app(VoidOrder::class)->handle($order, 'Salah', $kasir);
    expect($order->fresh()->status)->toBe('void');
});

it('penyesuaian wajib alasan dan tercatat; tidak bisa membuat saldo negatif', function () {
    expect(fn () => app(AdjustWallet::class)->handle($this->wallet, 'credit', 1000, ' ', $this->staff->id))->toThrow(WalletException::class, 'Alasan');
    expect(fn () => app(AdjustWallet::class)->handle($this->wallet, 'debit', 1000, 'Koreksi', $this->staff->id))->toThrow(WalletException::class, 'tidak cukup');

    app(AdjustWallet::class)->handle($this->wallet, 'credit', 1000, 'Koreksi', $this->staff->id);
    expect($this->wallet->fresh()->balance)->toBe(1000)->and(DB::table('audit_logs')->where('action', 'wallet_adjust')->exists())->toBeTrue();
});

it('tarik saldo hanya untuk santri tidak aktif, tercatat di kas dan penerimanya', function () {
    topUp($this, 40000);
    $wallet = $this->wallet->fresh();

    expect(fn () => app(WithdrawBalance::class)->handle($wallet, 'Bapak', $this->staff->id))->toThrow(WalletException::class, 'lulus');

    $this->student->update(['status' => 'graduated']);
    expect(app(WithdrawBalance::class)->handle($wallet->fresh(), 'Bapak Ahmad', $this->staff->id))->toBe(40000)
        ->and($this->wallet->fresh()->balance)->toBe(0)
        ->and(WalletTransaction::where('type', 'refund')->where('direction', 'debit')->value('note'))->toContain('Bapak Ahmad')
        ->and(CashTransaction::where('type', 'out')->value('amount'))->toBe(40000)
        ->and(fn () => app(WithdrawBalance::class)->handle($wallet->fresh(), 'Bapak', $this->staff->id))->toThrow(WalletException::class, 'kosong');
});

it('rekonsiliasi harian menangkap selisih dan memberi tahu admin', function () {
    topUp($this);
    $admin = tap(User::create(['name' => 'Adm', 'email' => 'x@uji.test', 'type' => 'staff']))->assignRole('admin');

    expect(app(ReconcileWallets::class)->handle())->toBe([]);

    DB::table('wallets')->where('id', $this->wallet->id)->update(['balance' => 999]);

    expect(app(ReconcileWallets::class)->handle())->toHaveCount(1)
        ->and(InAppNotification::where('user_id', $admin->id)->where('title', 'Selisih saldo terdeteksi')->exists())->toBeTrue();
});

describe('notifikasi', function () {
    beforeEach(function () {
        Module::where('module_key', 'notification_wa')->update(['is_enabled' => true]);
        app(ModuleRegistry::class)->forget();
        $this->guardianUser = tap(User::create(['name' => 'Wali', 'phone' => '628120000', 'type' => 'guardian']))->assignRole('wali');
        Guardian::create(['user_id' => $this->guardianUser->id, 'name' => 'Wali', 'phone_wa' => '628120000'])->students()->attach($this->student->id, ['relation' => 'ayah']);
    });

    it('top-up berhasil mengabari saldo baru, bukan pesan tagihan', function () {
        topUp($this, 50000);

        expect(NotificationLog::where('event_key', 'topup_success')->first()->payload['message'])->toContain('Rp 50.000')->toContain('Ahmad')
            ->and(NotificationLog::where('event_key', 'payment_success')->count())->toBe(0);
    });

    it('kartu diblokir mengabari wali dan admin', function () {
        $admin = tap(User::create(['name' => 'Adm', 'email' => 'x@uji.test', 'phone' => '628130000', 'type' => 'staff']))->assignRole('admin');

        app(ManageCard::class)->block($this->card, 'lost', 'Hilang');

        expect(NotificationLog::where('event_key', 'card_blocked')->pluck('recipient')->all())->toContain('628120000', '628130000');
    });

    it('saldo di bawah ambang mengabari sekali saat melewati ambang', function () {
        topUp($this, 15000);

        buy($this);
        expect(NotificationLog::where('event_key', 'wallet_low')->count())->toBe(0);

        $this->product->update(['price' => 1000]);
        buy($this);
        expect(NotificationLog::where('event_key', 'wallet_low')->count())->toBe(1);

        buy($this);
        expect(NotificationLog::where('event_key', 'wallet_low')->count())->toBe(1);
    });

    it('kabar belanja mati kecuali wali mengaktifkannya', function () {
        topUp($this);

        buy($this);
        expect(NotificationLog::where('event_key', 'wallet_spend')->count())->toBe(0);

        GuardianNotificationPref::create(['guardian_id' => Guardian::first()->id, 'category' => 'spend', 'is_enabled' => true]);
        buy($this);
        expect(NotificationLog::where('event_key', 'wallet_spend')->first()->payload['message'])->toContain('Warung A')->toContain('Rp 5.000');
    });
});

describe('API perangkat', function () {
    beforeEach(function () {
        $this->key = 'kunci-perangkat-uji';
        $this->device = Device::create(['name' => 'Reader', 'scopes' => ['canteen', 'attendance'], 'api_key_hash' => hash('sha256', $this->key)]);
    });

    it('menolak kunci salah dan perangkat yang dicabut', function () {
        $this->postJson('/api/v1/device/scan', ['scope' => 'canteen', 'card_uid' => '04A1B2'])->assertUnauthorized();

        $this->device->update(['is_active' => false]);
        $this->withToken($this->key)->postJson('/api/v1/device/scan', ['scope' => 'canteen', 'card_uid' => '04A1B2'])->assertUnauthorized();
    });

    it('scope perangkat dan modul mati ditolak dengan pesan jelas', function () {
        $this->withToken($this->key)->postJson('/api/v1/device/scan', ['scope' => 'gate', 'card_uid' => '04A1B2'])->assertForbidden();

        Module::where('module_key', 'attendance')->update(['is_enabled' => false]);
        app(ModuleRegistry::class)->forget();

        $this->withToken($this->key)->postJson('/api/v1/device/scan', ['scope' => 'attendance', 'card_uid' => '04A1B2'])
            ->assertOk()->assertJson(['ok' => false, 'result' => 'denied', 'message' => 'Akses ini belum aktif di pesantren.']);
    });

    it('pindai mengembalikan data ringkas dan mencatat log', function () {
        $this->withToken($this->key)->postJson('/api/v1/device/scan', ['scope' => 'canteen', 'card_uid' => '04a1b2'])
            ->assertOk()->assertJson(['ok' => true, 'student' => ['name' => 'Ahmad']])->assertJsonMissingPath('student.id');

        expect(CardScan::where('device_id', $this->device->id)->where('result', 'ok')->exists())->toBeTrue()
            ->and($this->device->fresh()->last_seen_at)->not->toBeNull();

        app(ManageCard::class)->block($this->card, 'blocked', 'Uji');
        $this->withToken($this->key)->postJson('/api/v1/device/scan', ['scope' => 'canteen', 'card_uid' => '04A1B2'])->assertJson(['ok' => false, 'result' => 'blocked']);
    });

    it('charge: dobel kirim tidak memotong dua kali, merchant ikut bertambah', function () {
        topUp($this);
        $payload = ['card_uid' => '04A1B2', 'amount' => 7000, 'merchant_id' => $this->merchant->uuid, 'idempotency_key' => 'alat-1'];

        $this->withToken($this->key)->postJson('/api/v1/device/wallet/charge', $payload)->assertOk()->assertJson(['ok' => true, 'balance' => 93000]);
        $this->withToken($this->key)->postJson('/api/v1/device/wallet/charge', $payload)->assertOk()->assertJson(['ok' => true, 'balance' => 93000]);

        expect($this->wallet->fresh()->balance)->toBe(93000)->and($this->merchant->fresh()->balance())->toBe(7000);
    });

    it('charge di atas ambang tanpa PIN ditolak', function () {
        topUp($this);
        app(ManageCard::class)->setPin($this->card, '4827');

        $this->withToken($this->key)->postJson('/api/v1/device/wallet/charge', ['card_uid' => '04A1B2', 'amount' => 25000, 'merchant_id' => $this->merchant->uuid, 'idempotency_key' => 'alat-2'])
            ->assertOk()->assertJson(['ok' => false, 'code' => 'pin_required']);
    });
});
