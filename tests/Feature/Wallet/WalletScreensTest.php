<?php

use App\Modules\Core\Models\Guardian;
use App\Modules\Core\Models\Module;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\ModuleRegistry;
use App\Modules\Finance\Actions\BuildReceiptPdf;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Models\Payment;
use App\Modules\Pos\Actions\CheckoutOrder;
use App\Modules\Pos\Livewire\Cashier;
use App\Modules\Pos\Livewire\MerchantAdmin;
use App\Modules\Pos\Models\Merchant;
use App\Modules\Pos\Models\MerchantProduct;
use App\Modules\Pos\Models\PosOrder;
use App\Modules\Wallet\Actions\IssueCard;
use App\Modules\Wallet\Actions\ManageCard;
use App\Modules\Wallet\Actions\RecordCashTopup;
use App\Modules\Wallet\Livewire\CardAdmin;
use App\Modules\Wallet\Livewire\DeviceAdmin;
use App\Modules\Wallet\Livewire\GuardianTopup;
use App\Modules\Wallet\Livewire\GuardianWallet;
use App\Modules\Wallet\Livewire\StudentCardPage;
use App\Modules\Wallet\Livewire\WalletAdmin;
use App\Modules\Wallet\Models\Device;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Support\QrToken;
use App\Support\Navigation;
use App\Support\Pdf\PdfRenderer;
use Database\Seeders\FinanceSeeder;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    (new ModuleSeeder)->run();
    (new RoleSeeder)->run();
    (new FinanceSeeder)->run();
    Module::whereIn('module_key', ['wallet', 'card', 'pos'])->update(['is_enabled' => true]);
    app(ModuleRegistry::class)->forget();
    $this->travelTo('2026-11-02 03:00:00');

    $this->admin = tap(User::create(['name' => 'Admin', 'email' => 'a@uji.test', 'type' => 'staff']))->assignRole('admin');
    $this->bendahara = tap(User::create(['name' => 'Bendahara', 'email' => 'b@uji.test', 'type' => 'staff']))->assignRole('bendahara');
    $this->kasir = tap(User::create(['name' => 'Kasir', 'email' => 'k@uji.test', 'type' => 'staff']))->assignRole('kasir');

    $this->student = Student::create(['nis' => 'A1', 'name' => 'Ahmad', 'gender' => 'L']);
    $this->other = Student::create(['nis' => 'B1', 'name' => 'Budi', 'gender' => 'L']);
    $this->guardianUser = tap(User::create(['name' => 'Wali', 'phone' => '628120000', 'type' => 'guardian']))->assignRole('wali');
    $this->guardian = Guardian::create(['user_id' => $this->guardianUser->id, 'name' => 'Wali', 'phone_wa' => '628120000']);
    $this->guardian->students()->attach($this->student->id, ['relation' => 'ayah']);

    $this->card = app(IssueCard::class)->handle($this->student, 'AA11');
    app(IssueCard::class)->handle($this->other, 'BB22');
    app(RecordCashTopup::class)->handle($this->student, 100000, $this->bendahara->id);

    $this->merchant = Merchant::create(['name' => 'Warung A', 'payment_modes' => ['card', 'qr']]);
    $this->merchant->cashiers()->attach($this->kasir->id);
    $this->product = MerchantProduct::create(['merchant_id' => $this->merchant->id, 'name' => 'Roti', 'price' => 5000, 'barcode' => '899123']);
});

it('hak akses layar mengikuti matriks PRD', function (string $route, array $allowed, array $denied) {
    foreach ($allowed as $who) {
        $this->actingAs($this->{$who})->get(route($route))->assertOk();
    }

    foreach ($denied as $who) {
        $this->actingAs($this->{$who})->get(route($route))->assertForbidden();
    }
})->with([
    'dompet' => ['staff.wallets.index', ['admin', 'bendahara'], ['kasir']],
    'kartu' => ['staff.cards.index', ['admin', 'bendahara'], ['kasir']],
    'perangkat' => ['staff.cards.devices', ['admin'], ['bendahara', 'kasir']],
    'kasir' => ['staff.pos.index', ['admin', 'kasir'], ['bendahara']],
    'merchant' => ['staff.merchants.index', ['admin', 'bendahara'], ['kasir']],
]);

it('modul mati menutup layarnya', function () {
    Module::where('module_key', 'pos')->update(['is_enabled' => false]);
    app(ModuleRegistry::class)->forget();

    $this->actingAs($this->kasir)->get(route('staff.pos.index'))->assertForbidden()->assertSee('tidak aktif');
});

it('kasir hanya melihat merchant miliknya dan produknya', function () {
    Merchant::create(['name' => 'Warung Lain', 'payment_modes' => ['card']]);

    $this->actingAs($this->kasir);

    Livewire::test(Cashier::class)->assertSee('Roti')->assertDontSee('Warung Lain');
    expect(Livewire::test(Cashier::class, ['merchantUuid' => Merchant::where('name', 'Warung Lain')->value('uuid')])->get('merchantUuid'))->toBe($this->merchant->uuid);
});

it('kasir: keranjang, barcode, bayar, hasil, saldo terpotong, harga tidak bisa diubah klien', function () {
    $this->actingAs($this->kasir);

    Livewire::test(Cashier::class)
        ->call('add', $this->product->uuid)
        ->set('barcode', '899123')
        ->call('addBarcode')
        ->assertSet('cart', [$this->product->uuid => 2])
        ->assertSee('Rp 10.000')
        ->set('barcode', '000')
        ->call('addBarcode')
        ->assertHasErrors('barcode')
        ->call('startPayment')
        ->assertSet('stage', 'tap')
        ->set('scan', 'AA11')
        ->call('submitScan')
        ->assertSet('stage', 'result')
        ->assertSee('Sisa saldo Rp 90.000')
        ->assertSee('Ahmad')
        ->assertSet('cart', []);

    expect(Wallet::forStudent($this->student)->balance)->toBe(90000);
});

it('kasir: PIN diminta lewat keypad, salah menampilkan sisa percobaan, benar berhasil', function () {
    app(ManageCard::class)->setPin($this->card, '4827');
    $this->product->update(['price' => 25000]);
    $this->actingAs($this->kasir);

    Livewire::test(Cashier::class)
        ->call('add', $this->product->uuid)
        ->call('startPayment')
        ->set('scan', 'AA11')
        ->call('submitScan')
        ->assertSet('stage', 'pin')
        ->call('submitPin', '0000')
        ->assertSet('stage', 'pin')
        ->assertSee('Sisa 2 percobaan')
        ->call('submitPin', '4827')
        ->assertSet('stage', 'result')
        ->assertSee('Sisa saldo Rp 75.000');
});

it('kasir: ditolak menampilkan alasan dan keranjang tetap', function () {
    $this->product->update(['price' => 15000]);
    Wallet::forStudent($this->student)->update(['daily_limit' => 10000]);
    $this->actingAs($this->kasir);

    Livewire::test(Cashier::class)
        ->call('add', $this->product->uuid)
        ->call('startPayment')
        ->set('scan', 'AA11')
        ->call('submitScan')
        ->assertSet('stage', 'result')
        ->assertSee('Limit harian terlampaui (sisa Rp 10.000).')
        ->assertSet('cart', [$this->product->uuid => 1])
        ->call('backToTap')
        ->assertSet('stage', 'tap');
});

it('kasir: kirim ulang dengan keranjang sama tidak memotong dua kali', function () {
    $this->actingAs($this->kasir);

    $component = Livewire::test(Cashier::class)->call('add', $this->product->uuid)->call('startPayment');
    $key = $component->get('checkoutKey');
    $component->set('scan', 'AA11')->call('submitScan');

    app(CheckoutOrder::class)->handle($this->merchant, [$this->product->id => 1], null, 'AA11', null, $key, $this->kasir->id);

    expect(PosOrder::count())->toBe(1)->and(Wallet::forStudent($this->student)->balance)->toBe(95000);
});

it('kasir bisa membatalkan transaksinya, dari layar yang sama', function () {
    $this->actingAs($this->kasir);

    $component = Livewire::test(Cashier::class)->call('add', $this->product->uuid)->call('startPayment')->set('scan', 'AA11')->call('submitScan')->call('backToCart');
    $order = PosOrder::first();

    $component->call('askVoid', $order->uuid)->set('voidReason', 'Salah produk')->call('confirmVoid')->assertHasNoErrors()->assertSee('Dibatalkan');

    expect(Wallet::forStudent($this->student)->balance)->toBe(100000);
});

it('admin dompet: top-up tunai, penyesuaian wajib alasan, limit, tampilan buku besar', function () {
    $this->actingAs($this->bendahara);

    Livewire::test(WalletAdmin::class)
        ->call('pick', $this->student->uuid)
        ->assertSee('Rp 100.000')
        ->set('topup_amount', '50000')
        ->call('topup')
        ->assertHasNoErrors()
        ->set('adjust_amount', '1000')
        ->call('adjust')
        ->assertHasErrors('adjust_reason')
        ->set('adjust_reason', 'Koreksi')
        ->set('adjust_direction', 'debit')
        ->call('adjust')
        ->assertHasNoErrors()
        ->set('daily_limit', '25000')
        ->call('saveLimits')
        ->assertSee('Penyesuaian')
        ->assertSee('Koreksi');

    expect(Wallet::forStudent($this->student)->balance)->toBe(149000)->and(Wallet::forStudent($this->student)->daily_limit)->toBe(25000);
});

it('peran hanya-lihat tidak bisa mengubah saldo', function () {
    $this->actingAs(tap(User::create(['name' => 'Akademik', 'email' => 'c@uji.test', 'type' => 'staff']))->assignRole('admin_akademik'));

    Livewire::test(WalletAdmin::class)->call('pick', $this->student->uuid)->assertDontSee('Top-up Tunai')->set('topup_amount', '50000')->call('topup')->assertForbidden();
});

it('kartu: terbitkan, UID ganda ditolak, blokir wajib alasan, ganti, reset PIN', function () {
    $third = Student::create(['nis' => 'C1', 'name' => 'Candra', 'gender' => 'L']);
    $this->actingAs($this->admin);

    $component = Livewire::test(CardAdmin::class)
        ->set('issueSearch', 'Cand')
        ->assertSee('Candra')
        ->call('pickIssueStudent', $third->uuid)
        ->set('issueUid', 'AA11')
        ->call('issue')
        ->assertHasErrors('issueUid')
        ->set('issueUid', 'CC33')
        ->call('issue')
        ->assertHasNoErrors();

    expect($third->cards()->where('status', 'active')->count())->toBe(1);

    $component->call('openBlock', $this->card->uuid)->call('block')->assertHasErrors('blockReason')
        ->set('blockReason', 'Rusak')->call('block')->assertHasNoErrors();
    expect($this->card->fresh()->status)->toBe('blocked');

    $component->call('unblock', $this->card->uuid);
    expect($this->card->fresh()->status)->toBe('active');

    $component->call('openReplace', $this->card->uuid)->set('replaceUid', 'DD44')->call('replace')->assertHasNoErrors();
    expect($this->card->fresh()->status)->toBe('lost');
});

it('kartu: pencarian tidak mengabaikan filter lain, peran lihat tidak bisa mengubah', function () {
    $this->actingAs(tap(User::create(['name' => 'Akademik', 'email' => 'c@uji.test', 'type' => 'staff']))->assignRole('admin_akademik'));

    Livewire::test(CardAdmin::class)->assertSee('Ahmad')->assertDontSee('Terbitkan Kartu')->call('openBlock', $this->card->uuid)->assertForbidden();
});

it('cetak kartu hanya kartu aktif, PDF tidak memuat rahasia', function () {
    $captured = new class implements PdfRenderer
    {
        public string $html = '';

        public function render(string $html, string $format = 'A4'): string
        {
            $this->html = $html;

            return '%PDF';
        }
    };
    $this->app->instance(PdfRenderer::class, $captured);
    $this->actingAs($this->admin);

    $this->get(route('staff.cards.print', ['kartu' => $this->card->uuid]))->assertOk()->assertHeader('content-type', 'application/pdf');

    expect($captured->html)->toContain('Ahmad')->not->toContain($this->card->qr_secret);

    $this->actingAs($this->bendahara)->get(route('staff.cards.print', ['kartu' => 'bukan-uuid']))->assertStatus(404);
});

it('perangkat: kunci tampil sekali dan hanya hash yang tersimpan; cabut menghentikan', function () {
    $this->actingAs($this->admin);

    $component = Livewire::test(DeviceAdmin::class)->call('save')->assertHasErrors(['name', 'scopes'])
        ->set('name', 'Reader Kantin')->set('scopes', ['canteen'])->call('save')->assertHasNoErrors();

    $key = $component->get('newKey');
    $device = Device::first();
    expect($key)->toStartWith('dev_')->and($device->api_key_hash)->toBe(hash('sha256', $key));

    $this->withToken($key)->postJson('/api/v1/device/scan', ['scope' => 'canteen', 'card_uid' => 'AA11'])->assertOk();

    $component->call('rotate', $device->uuid);
    $this->withToken($key)->postJson('/api/v1/device/scan', ['scope' => 'canteen', 'card_uid' => 'AA11'])->assertUnauthorized();

    $component->call('toggle', $device->uuid);
    $this->withToken($component->get('newKey'))->postJson('/api/v1/device/scan', ['scope' => 'canteen', 'card_uid' => 'AA11'])->assertUnauthorized();
});

it('log pindai terbuka dan bisa diekspor', function () {
    $this->actingAs($this->admin)->get(route('staff.cards.scans'))->assertOk();
    $this->get(route('staff.cards.scans.export'))->assertOk();
    $this->actingAs($this->bendahara)->get(route('staff.cards.scans'))->assertOk();
    $this->actingAs($this->kasir)->get(route('staff.cards.scans'))->assertForbidden();
});

it('merchant: tambah, kasir terhubung; hanya pengelola pusat', function () {
    $this->actingAs($this->admin);

    Livewire::test(MerchantAdmin::class)
        ->call('createMerchant')
        ->set('name', 'Mart Pesantren')->set('type', 'mart')->set('commission_percent', '2.5')->set('cashiers', [(string) $this->kasir->id])
        ->call('saveMerchant')->assertHasNoErrors();

    $merchant = Merchant::where('name', 'Mart Pesantren')->first();
    expect($merchant->cashiers()->count())->toBe(1)->and((float) $merchant->commission_percent)->toBe(2.5)->and($merchant->payment_modes)->toBe(['card', 'qr']);

    $this->actingAs($this->bendahara);
    Livewire::test(MerchantAdmin::class)->assertDontSee('Tambah Merchant')->call('createMerchant')->assertForbidden();
});

describe('wali', function () {
    it('hanya melihat dompet anaknya; uuid anak lain diabaikan', function () {
        $this->actingAs($this->guardianUser);

        Livewire::test(GuardianWallet::class)->assertSee('Rp 100.000')->assertSee('Ahmad');
        Livewire::test(GuardianWallet::class, ['childUuid' => $this->other->uuid])->assertSee('Ahmad')->assertDontSee('Budi');
        Livewire::test(GuardianWallet::class)->call('selectChild', $this->other->uuid)->assertNotFound();
    });

    it('limit langsung berlaku, kartu bisa diblokir dua langkah, PIN divalidasi', function () {
        $this->actingAs($this->guardianUser);

        Livewire::test(GuardianWallet::class)
            ->set('daily_limit', '5000')->set('wallet_blocked', true)->call('saveLimits')
            ->set('pin', '1234')->set('pin_confirmation', '1234')->call('savePin')->assertHasErrors('pin')
            ->set('pin', '4827')->set('pin_confirmation', '4800')->call('savePin')->assertHasErrors('pin_confirmation')
            ->set('pin_confirmation', '4827')->call('savePin')->assertHasNoErrors()
            ->call('blockCard');

        expect(Wallet::forStudent($this->student)->daily_limit)->toBe(5000)
            ->and(Wallet::forStudent($this->student)->status)->toBe('blocked')
            ->and($this->card->fresh()->status)->toBe('blocked')
            ->and($this->card->fresh()->blocked_reason)->toContain('Diblokir wali');
    });

    it('wali tidak bisa membuka blokir sendiri', function () {
        app(ManageCard::class)->block($this->card, 'blocked', 'Uji');
        $this->actingAs($this->guardianUser);

        Livewire::test(GuardianWallet::class)->assertSee('Hubungi admin')->assertDontSee('Buka Blokir')->call('blockCard')->assertNotFound();
    });

    it('struk digital terbuka dari riwayat, tidak bisa membuka transaksi anak lain', function () {
        $order = app(CheckoutOrder::class)->handle($this->merchant, [$this->product->id => 2], null, 'AA11', null, 'k', $this->kasir->id)->order;
        $this->actingAs($this->guardianUser);

        $transaction = $order->walletTransaction;

        Livewire::test(GuardianWallet::class)
            ->call('openReceipt', $transaction->uuid)
            ->assertSee($order->receipt_no)
            ->assertSee('Roti x2');

        app(RecordCashTopup::class)->handle($this->other, 20000, $this->bendahara->id);
        $foreign = Wallet::forStudent($this->other)->transactions()->first();
        expect(fn () => Livewire::test(GuardianWallet::class)->call('openReceipt', $foreign->uuid))->toThrow(ModelNotFoundException::class);
    });

    it('top-up transfer: nominal divalidasi, pembayaran tercipta dan bisa dilihat wali', function () {
        $account = BankAccount::create(['bank_name' => 'B', 'account_no' => '1', 'account_holder' => 'P']);
        $this->actingAs($this->guardianUser);

        Livewire::test(GuardianTopup::class)
            ->set('amount', '5000')->set('bank_account', $account->uuid)->call('payByTransfer')->assertHasErrors('amount')
            ->set('amount', '100000')->call('payByTransfer')->assertHasNoErrors()->assertRedirect();

        $payment = Payment::where('purpose', 'wallet_topup')->where('method', 'manual_transfer')->first();
        expect($this->guardianUser->can('view', $payment))->toBeTrue()
            ->and($payment->studentNames())->toBe('Ahmad');

        $this->get(route('wali.payments.show', $payment))->assertOk()->assertSee('Ahmad')->assertSee('Kembali ke Dompet');

        $stranger = tap(User::create(['name' => 'Lain', 'phone' => '628999', 'type' => 'guardian']))->assignRole('wali');
        expect($stranger->can('view', $payment))->toBeFalse();
    });

    it('kwitansi top-up memuat nama santri dan keterangan', function () {
        $captured = new class implements PdfRenderer
        {
            public string $html = '';

            public function render(string $html, string $format = 'A4'): string
            {
                $this->html = $html;

                return '%PDF';
            }
        };
        $this->app->instance(PdfRenderer::class, $captured);

        app(BuildReceiptPdf::class)->handle(Payment::where('purpose', 'wallet_topup')->first());

        expect($captured->html)->toContain('Ahmad')->toContain('Top-up dompet');
    });
});

it('santri melihat QR dinamis dan saldo, QR hilang bila kartu diblokir', function () {
    $user = tap(User::create(['name' => 'Ahmad', 'username' => 'A1', 'type' => 'student']))->assignRole('santri');
    $this->student->update(['user_id' => $user->id]);
    $this->actingAs($user);

    Livewire::test(StudentCardPage::class)->assertSee('Rp 100.000')->assertSee('QR berganti dalam')->assertSee('NIS A1');

    app(ManageCard::class)->block($this->card, 'blocked', 'Uji');
    Livewire::test(StudentCardPage::class)->assertDontSee('QR berganti dalam')->assertSee('tidak bisa dipakai');
});

it('jendela QR bergeser mengikuti waktu', function () {
    $token = QrToken::dynamic($this->card);
    expect(QrToken::secondsLeft())->toBeBetween(1, 30);

    $this->travel(30)->seconds();
    expect(QrToken::dynamic($this->card))->not->toBe($token);
});

it('menu mengikuti modul dan izin', function () {
    $keys = fn (User $user) => collect(app(Navigation::class)->staff())->flatMap(fn ($group) => $group['items'])->pluck('key');

    $this->actingAs($this->kasir);
    expect($keys($this->kasir))->toContain('pos')->not->toContain('wallets')->not->toContain('invoices');

    $this->actingAs($this->bendahara);
    expect($keys($this->bendahara))->toContain('wallets', 'cards')->toContain('merchants', 'payouts')->not->toContain('pos');
});

it('parameter URL rusak tidak membuat galat', function () {
    $this->actingAs($this->admin);

    $this->get(route('staff.wallets.index', ['santri' => 'bukan-uuid']))->assertOk();
    $this->get(route('staff.pos.index', ['merchant' => 'bukan-uuid']))->assertOk();
});

it('hasil pindai berisi sampah ditolak dengan rapi', function (string $garbage) {
    $this->actingAs($this->kasir);

    Livewire::test(Cashier::class)->call('add', $this->product->uuid)->call('startPayment')->set('scan', $garbage)->call('submitScan')->assertSet('stage', 'result')->assertSee('Ditolak');

    expect(Wallet::forStudent($this->student)->balance)->toBe(100000);
})->with(['D1.abc.x.y', 'S1.', 'D1.', "'; DROP TABLE wallets; --", 'TIDAK-ADA']);
