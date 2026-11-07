<?php

use App\Modules\Core\Models\Module;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\ModuleRegistry;
use App\Modules\Pos\Actions\CheckoutOrder;
use App\Modules\Pos\Actions\ManagePurchase;
use App\Modules\Pos\Actions\ManageShift;
use App\Modules\Pos\Actions\ReturnItem;
use App\Modules\Pos\Actions\VoidOrder;
use App\Modules\Pos\Livewire\Cashier;
use App\Modules\Pos\Livewire\OrderHistory;
use App\Modules\Pos\Livewire\Promotions;
use App\Modules\Pos\Livewire\Purchasing;
use App\Modules\Pos\Models\Merchant;
use App\Modules\Pos\Models\MerchantLedgerEntry;
use App\Modules\Pos\Models\MerchantProduct;
use App\Modules\Pos\Models\PosPromotion;
use App\Modules\Pos\Models\ProductCategory;
use App\Modules\Pos\Support\SalesStats;
use App\Modules\Wallet\Actions\IssueCard;
use App\Modules\Wallet\Actions\RecordCashTopup;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Support\WalletException;
use Database\Seeders\FinanceSeeder;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    (new ModuleSeeder)->run();
    (new RoleSeeder)->run();
    (new FinanceSeeder)->run();
    Module::whereIn('module_key', ['wallet', 'card', 'pos'])->update(['is_enabled' => true]);
    app(ModuleRegistry::class)->forget();
    Storage::fake('local');
    Queue::fake();
    $this->travelTo('2026-11-02 03:00:00');

    $this->bendahara = tap(User::create(['name' => 'Bendahara', 'email' => 'b@uji.test', 'type' => 'staff']))->assignRole('bendahara');
    $this->pengelola = tap(User::create(['name' => 'Pengelola', 'email' => 'p@uji.test', 'type' => 'staff']))->assignRole('pengelola_merchant');
    $this->kasir = tap(User::create(['name' => 'Kasir', 'email' => 'k@uji.test', 'type' => 'staff']))->assignRole('kasir');
    $this->kasirLain = tap(User::create(['name' => 'Kasir Lain', 'email' => 'k2@uji.test', 'type' => 'staff']))->assignRole('kasir');

    $this->student = Student::create(['nis' => 'A1', 'name' => 'Ahmad', 'gender' => 'L']);
    app(IssueCard::class)->handle($this->student, 'AA11');
    app(RecordCashTopup::class)->handle($this->student, 200000, $this->bendahara->id);

    $this->merchant = Merchant::create(['name' => 'Koperasi', 'pic_user_id' => $this->pengelola->id, 'payment_modes' => ['card', 'qr', 'cash'], 'commission_percent' => 10]);
    $this->merchant->cashiers()->attach($this->kasir->id);
    $this->other = Merchant::create(['name' => 'Warung Lain', 'payment_modes' => ['card']]);
    $this->other->cashiers()->attach($this->kasirLain->id);
    $this->snack = ProductCategory::create(['merchant_id' => $this->merchant->id, 'name' => 'Jajanan']);
    $this->roti = MerchantProduct::create(['merchant_id' => $this->merchant->id, 'category_id' => $this->snack->id, 'name' => 'Roti', 'price' => 5000, 'cost_price' => 3000, 'stock' => 50, 'min_stock' => 5]);
    $this->air = MerchantProduct::create(['merchant_id' => $this->merchant->id, 'name' => 'Air', 'price' => 3000, 'cost_price' => 2000, 'stock' => 10, 'min_stock' => 20]);
});

function cardBuy($context, array $lines, ?string $key = null)
{
    return app(CheckoutOrder::class)->handle($context->merchant, $lines, null, 'AA11', null, $key ?? (string) Str::uuid(), $context->kasir->id);
}

function balance($context): int
{
    return (int) Wallet::forStudent($context->student)->fresh()->balance;
}

describe('promo', function () {
    it('dipakai otomatis, tidak ditumpuk, dan margin laporan memakai harga setelah promo', function () {
        PosPromotion::create(['merchant_id' => $this->merchant->id, 'name' => 'Semua 10%', 'kind' => 'percent', 'value' => 10]);
        PosPromotion::create(['merchant_id' => $this->merchant->id, 'product_id' => $this->roti->id, 'name' => 'Beli 3 Roti', 'kind' => 'amount', 'value' => 1000, 'min_qty' => 3]);
        PosPromotion::create(['merchant_id' => $this->merchant->id, 'name' => 'Sudah lewat', 'kind' => 'percent', 'value' => 90, 'ends_on' => '2026-11-01']);

        $order = cardBuy($this, [$this->roti->id => 3, $this->air->id => 1])->order;
        $items = $order->items()->get()->keyBy('name');

        // Roti: 15.000, 10% = 1.500 kalah dengan 3 x 1.000 = 3.000. Air: 10% dari 3.000 = 300.
        expect($items['Roti']->discount)->toBe(3000)->and($items['Roti']->amount)->toBe(12000)
            ->and($items['Air']->discount)->toBe(300)->and($items['Air']->amount)->toBe(2700)
            ->and($order->total)->toBe(14700)->and($order->discount)->toBe(3300)
            ->and(balance($this))->toBe(200000 - 14700);

        $summary = SalesStats::summary([$this->merchant->id], '2026-11-02', '2026-11-02');
        expect($summary['revenue'])->toBe(14700)->and($summary['discount'])->toBe(3300)
            ->and($summary['margin'])->toBe(14700 - 3 * 3000 - 2000);
    });

    it('layar promo: hanya pengelola, persen di atas 100 ditolak', function () {
        $this->actingAs($this->pengelola);
        Livewire::test(Promotions::class, ['merchant' => $this->merchant])->call('create')
            ->set('name', 'Gila')->set('kind', 'percent')->set('value', '150')->call('save')->assertHasErrors('value')
            ->set('value', '20')->set('target', 'category')->set('category', $this->snack->uuid)->call('save')->assertHasNoErrors();
        expect(PosPromotion::first()->category_id)->toBe($this->snack->id);

        $this->actingAs($this->kasir);
        Livewire::test(Promotions::class, ['merchant' => $this->merchant])->call('create')->assertForbidden();
    });
});

describe('tunai', function () {
    it('pembeli bukan santri: kembalian, stok berkurang, dompet dan buku besar merchant tidak tersentuh, kirim ulang aman', function () {
        $order = app(CheckoutOrder::class)->cash($this->merchant, [$this->roti->id => 2], 20000, 'Ustadz Hasan', 'tunai-1', $this->kasir->id);
        $again = app(CheckoutOrder::class)->cash($this->merchant, [$this->roti->id => 2], 20000, 'Ustadz Hasan', 'tunai-1', $this->kasir->id);

        expect($again->id)->toBe($order->id)
            ->and($order->change_amount)->toBe(10000)->and($order->student_id)->toBeNull()->and($order->buyerName())->toBe('Ustadz Hasan')
            ->and($this->roti->fresh()->stock)->toBe(48)
            ->and(balance($this))->toBe(200000)
            ->and(MerchantLedgerEntry::where('merchant_id', $this->merchant->id)->count())->toBe(0);

        expect(fn () => app(CheckoutOrder::class)->cash($this->merchant, [$this->roti->id => 1], 1000, null, 'tunai-2', $this->kasir->id))->toThrow(WalletException::class, 'kurang')
            ->and(fn () => app(CheckoutOrder::class)->cash($this->other, [], 1000, null, 'tunai-3', $this->kasirLain->id))->toThrow(WalletException::class, 'belum menerima');

        app(VoidOrder::class)->handle($order, 'Salah ambil', $this->pengelola);
        expect($order->fresh()->status)->toBe('void')->and($this->roti->fresh()->stock)->toBe(50);
    });

    it('layar kasir: bayar tunai menampilkan kembalian dan tombol struk', function () {
        $this->actingAs($this->kasir);
        Livewire::test(Cashier::class)->call('add', $this->roti->uuid)->call('startCash')->set('cashPaid', '10000')->call('payCash')
            ->assertHasNoErrors()->assertSee('Kembalian Rp 5.000')->assertSee('Cetak Struk');
    });
});

describe('shift', function () {
    it('tutup shift menghitung tunai seharusnya dan selisih; satu shift terbuka per kasir', function () {
        $shifts = app(ManageShift::class);
        $shift = $shifts->open($this->merchant, $this->kasir, 50000);
        expect(fn () => $shifts->open($this->merchant, $this->kasir, 0))->toThrow(WalletException::class, 'masih terbuka');

        cardBuy($this, [$this->roti->id => 1]);
        app(CheckoutOrder::class)->cash($this->merchant, [$this->air->id => 2], 10000, null, 'k1', $this->kasir->id);

        expect(fn () => $shifts->close($shift, $this->kasirLain, 0, null))->toThrow(WalletException::class, 'kasir lain');
        $closed = $shifts->close($shift, $this->kasir, 55000, 'Kurang seribu');

        expect($closed->order_count)->toBe(2)->and($closed->card_sales)->toBe(5000)->and($closed->cash_sales)->toBe(6000)
            ->and($closed->expected_cash)->toBe(56000)->and($closed->difference())->toBe(-1000);
        expect(fn () => $shifts->close($shift, $this->kasir, 1, null))->toThrow(WalletException::class, 'sudah ditutup');
    });

    it('layar kasir membuka dan menutup shift', function () {
        $this->actingAs($this->kasir);
        Livewire::test(Cashier::class)->set('openingCash', '100000')->call('openShift')->assertHasNoErrors()->assertSee('Shift sejak')
            ->call('closeShift')->assertHasErrors('countedCash')
            ->set('countedCash', '100000')->call('closeShift')->assertHasNoErrors()->assertSee('Buka Shift');
    });
});

describe('retur', function () {
    it('retur sebagian kartu: saldo, komisi, buku besar, stok; total retur tepat sama dengan harga bersih', function () {
        PosPromotion::create(['merchant_id' => $this->merchant->id, 'product_id' => $this->air->id, 'name' => 'Air', 'kind' => 'amount', 'value' => 333]);
        $order = cardBuy($this, [$this->air->id => 3])->order;
        $item = $order->items()->first();
        expect($item->amount)->toBe(3 * 3000 - 999);

        $returns = app(ReturnItem::class);
        expect(fn () => $returns->handle($order, $item, 1, true, 'Bocor', $this->kasir))->toThrow(WalletException::class, 'pengelola');

        $returns->handle($order, $item, 1, true, 'Bocor', $this->pengelola);
        $returns->handle($order, $item->fresh(), 1, false, 'Penyok', $this->pengelola);
        expect(fn () => $returns->handle($order, $item->fresh(), 2, true, 'x', $this->pengelola))->toThrow(WalletException::class, 'Sisa');
        $returns->handle($order, $item->fresh(), 1, true, 'Bocor', $this->pengelola);

        $order->refresh();
        expect($order->returned_amount)->toBe($item->amount)
            ->and(balance($this))->toBe(200000)
            ->and($this->air->fresh()->stock)->toBe(10 - 3 + 2)
            ->and($this->merchant->balance())->toBe(0)
            ->and($order->canBeVoidedBy($this->pengelola))->toBeFalse()
            ->and(SalesStats::summary([$this->merchant->id], '2026-11-02', '2026-11-02')['revenue'])->toBe(0);
    });

    it('layar transaksi: retur tunai mengingatkan mengembalikan uang', function () {
        $order = app(CheckoutOrder::class)->cash($this->merchant, [$this->roti->id => 2], 10000, null, 'k9', $this->kasir->id);
        $this->actingAs($this->pengelola);
        Livewire::test(OrderHistory::class, ['merchant' => $this->merchant])->call('open', $order->uuid)->assertSee('Roti')
            ->call('startReturn', $order->items()->first()->id)->set('returnQty', '1')->set('returnReason', 'Basi')->call('confirmReturn')
            ->assertHasNoErrors()->assertDispatched('toast', message: 'Retur tercatat. Kembalikan uang tunai Rp 5.000 ke pembeli.', tone: 'success');
        expect($order->fresh()->returned_amount)->toBe(5000);

        $this->actingAs($this->kasirLain)->get(route('staff.merchants.orders', $this->merchant))->assertForbidden();
    });
});

describe('pembelian', function () {
    it('stok dan harga modal bertambah, utang tercatat lalu dilunasi, produk tanpa stok mulai dilacak', function () {
        $loose = MerchantProduct::create(['merchant_id' => $this->merchant->id, 'name' => 'Gorengan', 'price' => 1000]);
        $purchase = app(ManagePurchase::class)->create($this->merchant, null, '2026-11-02', [
            ['product_id' => $this->air->id, 'qty' => 24, 'unit_cost' => 2200, 'expires_on' => '2026-11-20'],
            ['product_id' => $loose->id, 'qty' => 30, 'unit_cost' => 600],
        ], 30000, '2026-11-30', null, $this->pengelola);

        expect($purchase->total)->toBe(24 * 2200 + 30 * 600)->and($purchase->paid_amount)->toBe(30000)
            ->and($this->air->fresh()->stock)->toBe(34)->and($this->air->fresh()->cost_price)->toBe(2200)
            ->and($loose->fresh()->stock)->toBe(30);

        expect(fn () => app(ManagePurchase::class)->pay($purchase, $purchase->outstanding() + 1, 'cash', '2026-11-03', null, $this->pengelola))->toThrow(WalletException::class);
        app(ManagePurchase::class)->pay($purchase, $purchase->outstanding(), 'transfer', '2026-11-03', null, $this->pengelola);
        expect($purchase->fresh()->statusLabel())->toBe('Lunas');
    });

    it('layar: perlu belanja dari stok menipis, kedaluwarsa terlihat, kasir tidak bisa mencatat', function () {
        $this->actingAs($this->pengelola);
        Livewire::test(Purchasing::class, ['merchant' => $this->merchant])->call('selectTab', 'belanja')->assertSee('Air')->assertDontSee('Roti')
            ->call('restockAll')->assertSet('rows.0.product', $this->air->uuid)->assertSet('rows.0.unit_cost', '2000')
            ->set('rows.0.qty', '12')->set('rows.0.expires_on', '2026-11-10')->call('savePurchase')->assertHasNoErrors()
            ->call('selectTab', 'kedaluwarsa')->assertSee('Air')->assertSee('10 Nov 2026');
        expect($this->air->fresh()->stock)->toBe(22);

        $this->actingAs($this->kasir);
        Livewire::test(Purchasing::class, ['merchant' => $this->merchant])->call('newPurchase')->assertForbidden();
    });
});

it('struk thermal: kasir merchant itu bisa, kasir merchant lain tidak', function () {
    $order = app(CheckoutOrder::class)->cash($this->merchant, [$this->roti->id => 1], 10000, null, 'k5', $this->kasir->id);
    $this->actingAs($this->kasir)->get(route('staff.pos.receipt', $order))->assertOk()->assertSee('Kembali')->assertSee('Rp 5.000');
    $this->actingAs($this->kasir)->get(route('staff.pos.receipt', $order).'?lebar=80')->assertOk()->assertSee('size: 80mm', false);
    $this->actingAs($this->kasirLain)->get(route('staff.pos.receipt', $order))->assertForbidden();
});
