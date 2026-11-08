<?php

use App\Modules\Core\Models\Guardian;
use App\Modules\Core\Models\InAppNotification;
use App\Modules\Core\Models\Module;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\ModuleRegistry;
use App\Modules\Finance\Exports\ArrayExport;
use App\Modules\Finance\Models\CashCategory;
use App\Modules\Finance\Models\CashTransaction;
use App\Modules\Notification\Models\NotificationLog;
use App\Modules\Pos\Actions\CheckoutOrder;
use App\Modules\Pos\Actions\ManagePayout;
use App\Modules\Pos\Actions\StockLedger;
use App\Modules\Pos\Actions\VoidOrder;
use App\Modules\Pos\Livewire\Cashier;
use App\Modules\Pos\Livewire\CatalogImport;
use App\Modules\Pos\Livewire\MerchantAdmin;
use App\Modules\Pos\Livewire\PayoutQueue;
use App\Modules\Pos\Livewire\ProductCatalog;
use App\Modules\Pos\Livewire\SalesReport;
use App\Modules\Pos\Livewire\StockScreen;
use App\Modules\Pos\Models\Merchant;
use App\Modules\Pos\Models\MerchantLedgerEntry;
use App\Modules\Pos\Models\MerchantPayout;
use App\Modules\Pos\Models\MerchantProduct;
use App\Modules\Pos\Models\PosOrder;
use App\Modules\Pos\Models\PosPromotion;
use App\Modules\Pos\Models\ProductCategory;
use App\Modules\Pos\Models\StockMovement;
use App\Modules\Pos\Support\SalesStats;
use App\Modules\Wallet\Actions\IssueCard;
use App\Modules\Wallet\Actions\ReconcileWallets;
use App\Modules\Wallet\Actions\RecordCashTopup;
use App\Modules\Wallet\Livewire\GuardianSpendingRules;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\WalletMerchantLimit;
use App\Modules\Wallet\Support\WalletException;
use App\Support\Pdf\PdfRenderer;
use Database\Seeders\FinanceSeeder;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;

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
    $this->admin = tap(User::create(['name' => 'Admin', 'email' => 'a@uji.test', 'type' => 'staff']))->assignRole('admin');
    $this->pengelola = tap(User::create(['name' => 'Pengelola', 'email' => 'p@uji.test', 'phone' => '628130000', 'type' => 'staff']))->assignRole('pengelola_merchant');
    $this->kasir = tap(User::create(['name' => 'Kasir', 'email' => 'k@uji.test', 'type' => 'staff']))->assignRole('kasir');

    $this->student = Student::create(['nis' => 'A1', 'name' => 'Ahmad', 'gender' => 'L']);
    app(IssueCard::class)->handle($this->student, 'AA11');
    app(RecordCashTopup::class)->handle($this->student, 200000, $this->bendahara->id);
    $this->wallet = Wallet::forStudent($this->student);

    $this->merchant = Merchant::create(['name' => 'Warung A', 'pic_user_id' => $this->pengelola->id, 'payment_modes' => ['card', 'qr'], 'commission_percent' => 2]);
    $this->merchant->cashiers()->attach($this->kasir->id);
    $this->other = Merchant::create(['name' => 'Warung Lain', 'payment_modes' => ['card', 'qr']]);
    $this->product = MerchantProduct::create(['merchant_id' => $this->merchant->id, 'name' => 'Roti', 'price' => 5000, 'cost_price' => 3000]);
});

function martBuy($context, array $lines, ?Merchant $merchant = null, ?string $key = null)
{
    return app(CheckoutOrder::class)->handle($merchant ?? $context->merchant, $lines, null, 'AA11', null, $key ?? (string) Str::uuid(), $context->kasir->id);
}

function setStock(MerchantProduct $product, int $stock, ?int $min = null): void
{
    MerchantProduct::query()->whereKey($product->id)->update(['stock' => $stock, 'min_stock' => $min]);
    $product->refresh();
}

describe('stok', function () {
    it('berkurang saat terjual, kembali saat void, mutasi tercatat', function () {
        setStock($this->product, 5);

        $order = martBuy($this, [$this->product->id => 2])->order;
        expect($this->product->fresh()->stock)->toBe(3)
            ->and(StockMovement::where('type', 'sale')->value('quantity'))->toBe(-2)
            ->and(StockMovement::where('type', 'sale')->value('stock_after'))->toBe(3);

        app(VoidOrder::class)->handle($order, 'Batal', $this->admin);
        expect($this->product->fresh()->stock)->toBe(5)->and(StockMovement::where('type', 'void')->value('quantity'))->toBe(2);
    });

    it('stok kurang: seluruh order ditolak, saldo tetap', function () {
        setStock($this->product, 1);

        $result = martBuy($this, [$this->product->id => 2]);

        expect($result->ok)->toBeFalse()->and($result->code)->toBe('out_of_stock')->and($result->message)->toContain('Roti')->toContain('1')
            ->and($this->wallet->fresh()->balance)->toBe(200000)->and($this->product->fresh()->stock)->toBe(1);
    });

    it('produk tanpa hitungan stok tidak pernah kehabisan dan tidak membuat mutasi', function () {
        martBuy($this, [$this->product->id => 100 > 0 ? 3 : 1]);

        expect($this->product->fresh()->stock)->toBeNull()->and(StockMovement::count())->toBe(0);
    });

    it('stok tidak bisa minus di database dan mutasi tidak bisa diubah', function () {
        setStock($this->product, 1);

        expect(fn () => DB::transaction(fn () => DB::table('merchant_products')->where('id', $this->product->id)->update(['stock' => -1])))->toThrow(QueryException::class);

        app(StockLedger::class)->stockIn($this->product, 1, null, $this->admin->id);
        $movement = StockMovement::first();
        expect(fn () => DB::transaction(fn () => DB::table('stock_movements')->where('id', $movement->id)->update(['quantity' => 9])))->toThrow(QueryException::class);
    });

    it('stok masuk, keluar wajib alasan, opname mencatat selisih saja', function () {
        setStock($this->product, 10);
        $stock = app(StockLedger::class);

        $stock->stockIn($this->product, 5, 'Kulakan', $this->admin->id);
        expect($this->product->fresh()->stock)->toBe(15);

        expect(fn () => $stock->stockOut($this->product, 1, ' ', $this->admin->id))->toThrow(WalletException::class, 'Alasan');
        expect(fn () => $stock->stockOut($this->product, 99, 'Rusak', $this->admin->id))->toThrow(WalletException::class, 'tidak cukup');

        $stock->stockOut($this->product, 2, 'Rusak', $this->admin->id);
        expect($stock->opname($this->product->fresh(), 13, null, $this->admin->id))->toBe(0)
            ->and(StockMovement::where('type', 'adjust')->count())->toBe(0)
            ->and($stock->opname($this->product->fresh(), 10, 'Hilang', $this->admin->id))->toBe(-3)
            ->and(StockMovement::where('type', 'adjust')->value('note'))->toBe('Opname: Hilang')
            ->and($this->product->fresh()->stock)->toBe(10);
    });

    it('menipis memberi tahu pengelola sekali saat melewati batas', function () {
        Module::where('module_key', 'notification_wa')->update(['is_enabled' => true]);
        app(ModuleRegistry::class)->forget();
        setStock($this->product, 5, 2);

        martBuy($this, [$this->product->id => 2]);
        expect(NotificationLog::where('event_key', 'stock_low')->count())->toBe(0);

        martBuy($this, [$this->product->id => 1]);
        expect(NotificationLog::where('event_key', 'stock_low')->first()->payload['message'])->toContain('Roti')->toContain('2');

        martBuy($this, [$this->product->id => 1]);
        expect(NotificationLog::where('event_key', 'stock_low')->count())->toBe(1)
            ->and(InAppNotification::where('user_id', $this->pengelola->id)->exists())->toBeTrue();
    });

    it('layar stok: opname menampilkan selisih, kasir hanya melihat', function () {
        setStock($this->product, 10);
        $this->actingAs($this->pengelola);

        Livewire::test(StockScreen::class, ['merchant' => $this->merchant])
            ->call('open', $this->product->uuid, 'opname')
            ->set('quantity', 'abc')->call('submit')->assertHasErrors('quantity')
            ->set('quantity', '8')->call('submit')->assertHasNoErrors()
            ->assertSee('Selisih -2')
            ->assertSee('Penyesuaian');

        $this->actingAs($this->kasir);
        Livewire::test(StockScreen::class, ['merchant' => $this->merchant])->assertDontSee('Masuk')->call('open', $this->product->uuid, 'in')->assertForbidden();
    });
});

describe('aturan wali', function () {
    it('contoh PRD: limit warung 10 ribu, sudah 7 ribu, belanja 5 ribu ditolak', function () {
        WalletMerchantLimit::create(['wallet_id' => $this->wallet->id, 'merchant_id' => $this->merchant->id, 'daily_limit' => 10000]);
        $this->product->update(['price' => 7000]);
        martBuy($this, [$this->product->id => 1]);

        $this->product->update(['price' => 5000]);
        $result = martBuy($this, [$this->product->id => 1]);

        expect($result->code)->toBe('merchant_limit')->and($result->message)->toBe('Limit warung ini terlampaui (sisa Rp 3.000).')
            ->and(martBuy($this, [MerchantProduct::create(['merchant_id' => $this->other->id, 'name' => 'X', 'price' => 5000])->id => 1], $this->other)->ok)->toBeTrue();
    });

    it('merchant diblokir ditolak dengan pesan netral', function () {
        WalletMerchantLimit::create(['wallet_id' => $this->wallet->id, 'merchant_id' => $this->merchant->id, 'is_blocked' => true]);

        expect(martBuy($this, [$this->product->id => 1])->code)->toBe('merchant_blocked');
    });

    it('kategori diblokir: seluruh order ditolak, kasir melihat itemnya, tidak ada yang terpotong', function () {
        $category = ProductCategory::create(['merchant_id' => $this->merchant->id, 'name' => 'Jajanan', 'is_restricted' => true]);
        $snack = MerchantProduct::create(['merchant_id' => $this->merchant->id, 'category_id' => $category->id, 'name' => 'Permen', 'price' => 1000]);
        WalletMerchantLimit::create(['wallet_id' => $this->wallet->id, 'category_id' => $category->id, 'is_blocked' => true]);

        $result = martBuy($this, [$this->product->id => 1, $snack->id => 1]);

        expect($result->code)->toBe('category_blocked')->and($result->message)->toContain('Permen')->not->toContain('Jajanan')
            ->and($this->wallet->fresh()->balance)->toBe(200000)
            ->and(martBuy($this, [$this->product->id => 1])->ok)->toBeTrue();
    });

    it('batas harian per kategori dihitung dari belanja hari ini', function () {
        $category = ProductCategory::create(['merchant_id' => $this->merchant->id, 'name' => 'Jajanan', 'is_restricted' => true]);
        $snack = MerchantProduct::create(['merchant_id' => $this->merchant->id, 'category_id' => $category->id, 'name' => 'Permen', 'price' => 3000]);
        WalletMerchantLimit::create(['wallet_id' => $this->wallet->id, 'category_id' => $category->id, 'daily_limit' => 5000]);

        expect(martBuy($this, [$snack->id => 1])->ok)->toBeTrue();
        $denied = martBuy($this, [$snack->id => 1]);
        expect($denied->code)->toBe('category_limit')->and($denied->message)->toContain('sisa Rp 2.000');

        $this->travelTo('2026-11-03 03:00:00');
        expect(martBuy($this, [$snack->id => 1])->ok)->toBeTrue();
    });

    it('void mengembalikan batas merchant', function () {
        WalletMerchantLimit::create(['wallet_id' => $this->wallet->id, 'merchant_id' => $this->merchant->id, 'daily_limit' => 5000]);
        $order = martBuy($this, [$this->product->id => 1])->order;
        expect(martBuy($this, [$this->product->id => 1])->code)->toBe('merchant_limit');

        app(VoidOrder::class)->handle($order, 'Batal', $this->admin);
        expect(martBuy($this, [$this->product->id => 1])->ok)->toBeTrue();
    });

    it('layar W-15: simpan, kosongkan menghapus aturan, hanya anaknya', function () {
        $guardianUser = tap(User::create(['name' => 'Wali', 'phone' => '628120000', 'type' => 'guardian']))->assignRole('wali');
        Guardian::create(['user_id' => $guardianUser->id, 'name' => 'Wali', 'phone_wa' => '628120000'])->students()->attach($this->student->id, ['relation' => 'ayah']);
        $category = ProductCategory::create(['name' => 'Jajanan', 'is_restricted' => true]);
        ProductCategory::create(['name' => 'Bebas', 'is_restricted' => false]);
        $stranger = Student::create(['nis' => 'B1', 'name' => 'Budi', 'gender' => 'L']);
        $this->actingAs($guardianUser);

        $component = Livewire::test(GuardianSpendingRules::class)
            ->assertSee('Jajanan')->assertDontSee('Bebas')
            ->set('daily_limit', '25000')
            ->set("merchantRules.{$this->merchant->uuid}.limit", '10000')
            ->set("categoryRules.{$category->uuid}.blocked", true)
            ->call('save')
            ->assertDispatched('toast');

        expect($this->wallet->fresh()->daily_limit)->toBe(25000)
            ->and(WalletMerchantLimit::where('merchant_id', $this->merchant->id)->value('daily_limit'))->toBe(10000)
            ->and(WalletMerchantLimit::where('category_id', $category->id)->value('is_blocked'))->toBeTrue();

        $component->set("merchantRules.{$this->merchant->uuid}.limit", '')->call('save');
        expect(WalletMerchantLimit::where('merchant_id', $this->merchant->id)->exists())->toBeFalse();

        Livewire::test(GuardianSpendingRules::class, ['childUuid' => $stranger->uuid])->assertSee('Ahmad')->assertDontSee('Budi');
    });
});

describe('katalog', function () {
    it('pengelola mengelola merchant sendiri saja; kasir hanya melihat', function () {
        $this->actingAs($this->pengelola);
        Livewire::test(ProductCatalog::class, ['merchant' => $this->merchant])->call('create')->assertHasNoErrors();
        $this->get(route('staff.merchants.catalog', $this->other))->assertForbidden();

        $this->actingAs($this->kasir);
        Livewire::test(ProductCatalog::class, ['merchant' => $this->merchant])->assertDontSee('Tambah Produk')->call('create')->assertForbidden();

        $this->actingAs($this->bendahara);
        Livewire::test(ProductCatalog::class, ['merchant' => $this->merchant])->assertSee('Roti')->call('create')->assertForbidden();
    });

    it('tambah produk dengan stok awal, foto, kategori; barcode unik per merchant', function () {
        MerchantProduct::create(['merchant_id' => $this->other->id, 'name' => 'Sama', 'price' => 1, 'barcode' => '777']);
        $category = ProductCategory::create(['merchant_id' => $this->merchant->id, 'name' => 'Minuman']);
        $this->actingAs($this->pengelola);

        $component = Livewire::test(ProductCatalog::class, ['merchant' => $this->merchant])
            ->call('create')
            ->set('name', 'Air')->set('price', '3.000')->set('cost_price', '2000')->set('barcode', '777')->set('category_id', (string) $category->id)
            ->set('track_stock', true)->set('initial_stock', '12')->set('min_stock', '3')
            ->set('photo', UploadedFile::fake()->image('air.jpg'))
            ->call('save')->assertHasNoErrors();

        $product = MerchantProduct::where('name', 'Air')->first();
        expect($product->price)->toBe(3000)->and($product->stock)->toBe(12)->and($product->min_stock)->toBe(3)->and($product->photo_path)->not->toBeNull()
            ->and(StockMovement::where('product_id', $product->id)->value('note'))->toBe('Stok awal');
        Storage::disk('local')->assertExists($product->photo_path);

        $this->get(route('staff.merchants.product-photo', $product))->assertOk();

        $component->call('create')->set('name', 'Teh')->set('price', '3000')->set('barcode', '777')->call('save')->assertHasErrors('barcode');
    });

    it('kategori merchant lain tidak bisa dipakai', function () {
        $foreign = ProductCategory::create(['merchant_id' => $this->other->id, 'name' => 'Asing']);
        $this->actingAs($this->pengelola);

        Livewire::test(ProductCatalog::class, ['merchant' => $this->merchant])->call('create')->set('name', 'A')->set('price', '1000')->set('category_id', (string) $foreign->id)->call('save')->assertHasErrors('category_id');
    });

    it('ubah harga massal: persen dan nominal, harga minus ditolak', function () {
        $this->actingAs($this->pengelola);

        $component = Livewire::test(ProductCatalog::class, ['merchant' => $this->merchant])
            ->set('selected', [$this->product->uuid])->set('bulk_mode', 'percent')->set('bulk_value', '10')->call('applyBulkPrice')->assertHasNoErrors();
        expect($this->product->fresh()->price)->toBe(5500);

        $component->set('selected', [$this->product->uuid])->set('bulk_mode', 'amount')->set('bulk_value', '-9000')->call('applyBulkPrice')->assertHasErrors('bulk_value');
        expect($this->product->fresh()->price)->toBe(5500);
    });

    it('impor: pratinjau, baris salah dilewati, barcode lama memperbarui, stok jadi mutasi, aman diulang', function () {
        MerchantProduct::create(['merchant_id' => $this->merchant->id, 'name' => 'Lama', 'price' => 1000, 'barcode' => '111']);
        $path = 'impor-produk.xlsx';
        Excel::store(new ArrayExport(CatalogImport::HEADINGS, [
            ['Lama Baru', 2000, 1500, '111', 'Minuman', 10, 2],
            ['Baru', 3000, '', '222', 'Minuman', '', ''],
            ['', 0, '', '', '', 'x', ''],
            ['Ganda', 1000, '', '222', '', '', ''],
        ]), $path, 'local');
        $file = UploadedFile::fake()->createWithContent('impor.xlsx', Storage::disk('local')->get($path));
        $this->actingAs($this->pengelola);

        $component = Livewire::test(CatalogImport::class, ['merchant' => $this->merchant])
            ->set('file', $file)->call('check')
            ->assertSee('1 produk baru')->assertSee('1 diperbarui')->assertSee('2 baris bermasalah')
            ->call('import');

        expect($component->get('imported'))->toBe(['created' => 1, 'updated' => 1]);

        $updated = MerchantProduct::where('barcode', '111')->first();
        expect($updated->name)->toBe('Lama Baru')->and($updated->price)->toBe(2000)->and($updated->stock)->toBe(10)
            ->and(StockMovement::where('product_id', $updated->id)->value('quantity'))->toBe(10)
            ->and(ProductCategory::where('name', 'Minuman')->where('merchant_id', $this->merchant->id)->count())->toBe(1);

        Livewire::test(CatalogImport::class, ['merchant' => $this->merchant])->set('file', $file)->call('check')->call('import');
        expect(MerchantProduct::where('barcode', '222')->count())->toBe(1)->and(StockMovement::where('product_id', $updated->id)->count())->toBe(1);

        $this->actingAs($this->kasir)->get(route('staff.merchants.import', $this->merchant))->assertForbidden();
    });

    it('daftar harga PDF', function () {
        $this->app->instance(PdfRenderer::class, new class implements PdfRenderer
        {
            public function render(string $html, string $format = 'A4'): string
            {
                return '%PDF'.$html;
            }
        });

        $drinks = ProductCategory::create(['merchant_id' => $this->merchant->id, 'name' => 'Minuman']);
        MerchantProduct::create(['merchant_id' => $this->merchant->id, 'category_id' => $drinks->id, 'name' => 'Teh Kotak', 'price' => 5000]);
        MerchantProduct::create(['merchant_id' => $this->merchant->id, 'name' => 'Pulpen Kosong', 'price' => 3000, 'stock' => 0]);
        MerchantProduct::create(['merchant_id' => $this->merchant->id, 'name' => 'Produk Nonaktif', 'price' => 1000, 'is_active' => false]);
        PosPromotion::create(['merchant_id' => $this->merchant->id, 'category_id' => $drinks->id, 'name' => 'Promo Minuman', 'kind' => 'percent', 'value' => 20]);

        $this->actingAs($this->pengelola)->get(route('staff.merchants.pricelist', $this->merchant))->assertOk()->assertHeader('content-type', 'application/pdf')
            ->assertSeeInOrder(['1 produk sedang promo', 'Minuman', 'Teh Kotak', 'Rp 5.000', 'Rp 4.000', 'Lainnya', 'Pulpen Kosong', 'Habis'])
            ->assertDontSee('Produk Nonaktif');
        $this->get(route('staff.merchants.pricelist', $this->other))->assertForbidden();
    });

    it('kasir: produk habis tidak bisa ditambah, sisa stok terlihat', function () {
        setStock($this->product, 0);
        $this->actingAs($this->kasir);

        Livewire::test(Cashier::class)->assertSee('Habis')->call('add', $this->product->uuid)->assertSet('cart', []);
    });
});

describe('pencairan', function () {
    beforeEach(function () {
        $this->merchant->update(['pin_threshold' => 1000000]);
        martBuy($this, [$this->product->id => 10]);
    });

    it('alur penuh: ajukan, setujui, bayar; saldo dan kas berubah saat dibayar', function () {
        $payouts = app(ManagePayout::class);
        expect($this->merchant->balance())->toBe(49000);

        $payout = $payouts->request($this->merchant, 30000, 'cash', 'Bulanan', $this->pengelola->id);
        expect($payouts->available($this->merchant))->toBe(19000)->and($this->merchant->balance())->toBe(49000)->and(CashTransaction::where('source_type', 'merchant_payout')->count())->toBe(0);

        expect(fn () => $payouts->request($this->merchant, 20000, 'cash', null, $this->pengelola->id))->toThrow(WalletException::class, 'Rp 19.000');

        expect(fn () => $payouts->pay($payout, $this->bendahara->id))->toThrow(WalletException::class, 'Status');

        $payouts->approve($payout, $this->bendahara->id);
        $payouts->pay($payout, $this->bendahara->id);

        expect($payout->fresh()->status)->toBe('paid')->and($this->merchant->balance())->toBe(19000)
            ->and(MerchantLedgerEntry::where('type', 'payout')->value('amount'))->toBe(-30000)
            ->and(CashTransaction::where('category_id', CashCategory::named(CashCategory::MERCHANT_PAYOUT, 'out')->id)->value('amount'))->toBe(30000)
            ->and(MerchantLedgerEntry::mismatchedMerchants())->toBe([]);

        expect(fn () => $payouts->pay($payout->fresh(), $this->bendahara->id))->toThrow(WalletException::class, 'Status');
        expect($this->merchant->balance())->toBe(19000);
    });

    it('ditolak membebaskan saldo; alasan wajib', function () {
        $payouts = app(ManagePayout::class);
        $payout = $payouts->request($this->merchant, 49000, 'cash', null, $this->pengelola->id);

        expect(fn () => $payouts->reject($payout, ' ', $this->bendahara->id))->toThrow(WalletException::class, 'Alasan');

        $payouts->reject($payout, 'Belum waktunya', $this->bendahara->id);
        expect($payouts->available($this->merchant))->toBe(49000);
    });

    it('void setelah saldo disalurkan ditolak, tidak membuat saldo minus', function () {
        $order = PosOrder();
        $payouts = app(ManagePayout::class);
        $payout = $payouts->request($this->merchant, 49000, 'cash', null, $this->pengelola->id);
        $payouts->approve($payout, $this->bendahara->id);
        $payouts->pay($payout, $this->bendahara->id);

        expect(fn () => app(VoidOrder::class)->handle($order, 'Batal', $this->admin))->toThrow(WalletException::class, 'disalurkan');
        expect($this->wallet->fresh()->balance)->toBe(150000);
    });

    it('layar: pengelola mengajukan, bendahara memproses, pengelola merchant lain tidak bisa', function () {
        $this->actingAs($this->pengelola);
        Livewire::test(SalesReport::class, ['merchant' => $this->merchant])
            ->set('payout_amount', '99999999')->call('requestPayout')->assertHasErrors('payout_amount')
            ->set('payout_amount', '10000')->call('requestPayout')->assertHasNoErrors();

        $this->get(route('staff.payouts.index'))->assertForbidden();
        $this->get(route('staff.merchants.report', $this->other))->assertForbidden();

        $payout = MerchantPayout::first();
        $this->actingAs($this->bendahara);

        Livewire::test(PayoutQueue::class)->assertSee('Warung A')->call('approve', $payout->uuid)->assertDispatched('toast')->call('pay', $payout->uuid);
        expect($payout->fresh()->status)->toBe('paid');

        Livewire::test(SalesReport::class, ['merchant' => $this->merchant])->assertDontSee('Ajukan Pencairan')->call('requestPayout')->assertForbidden();
    });

    it('status pencairan mengabari pengelola', function () {
        Module::where('module_key', 'notification_wa')->update(['is_enabled' => true]);
        app(ModuleRegistry::class)->forget();
        $payouts = app(ManagePayout::class);
        $payout = $payouts->request($this->merchant, 10000, 'cash', null, $this->pengelola->id);

        $payouts->approve($payout, $this->bendahara->id);

        expect(NotificationLog::where('event_key', 'payout_status')->where('recipient', '628130000')->first()->payload['message'])->toContain('Rp 10.000')->toContain('Disetujui');
    });
});

describe('laporan', function () {
    it('angka cocok dengan transaksi: komisi, margin, void tidak terhitung', function () {
        $order = martBuy($this, [$this->product->id => 2])->order;
        martBuy($this, [$this->product->id => 1]);
        $third = martBuy($this, [$this->product->id => 3])->order;
        app(VoidOrder::class)->handle($third, 'Batal', $this->admin);

        $summary = SalesStats::summary([$this->merchant->id], '2026-11-02', '2026-11-02');

        expect($summary)->toBe(['revenue' => 15000, 'orders' => 2, 'commission' => 300, 'net' => 14700, 'margin' => 6000, 'margin_complete' => true, 'cash' => 0, 'discount' => 0, 'returns' => 0])
            ->and(SalesStats::byProduct([$this->merchant->id], '2026-11-02', '2026-11-02')->first()->name)->toBe('Roti')
            ->and((int) SalesStats::byProduct([$this->merchant->id], '2026-11-02', '2026-11-02')->first()->qty)->toBe(3);

        expect($this->merchant->fresh()->balance())->toBe($summary['net']);
    });

    it('margin ditandai belum lengkap bila ada produk tanpa harga modal', function () {
        $this->product->update(['cost_price' => null]);
        martBuy($this, [$this->product->id => 1]);

        expect(SalesStats::summary([$this->merchant->id], '2026-11-02', '2026-11-02')['margin_complete'])->toBeFalse();
    });

    it('rentang dan jam ramai mengikuti waktu pesantren', function () {
        $this->travelTo('2026-11-02 18:30:00');
        martBuy($this, [$this->product->id => 1]);

        expect(SalesStats::summary([$this->merchant->id], '2026-11-03', '2026-11-03')['orders'])->toBe(1)
            ->and(SalesStats::summary([$this->merchant->id], '2026-11-02', '2026-11-02')['orders'])->toBe(0)
            ->and(SalesStats::byHour([$this->merchant->id], '2026-11-03', '2026-11-03')->first()->hour)->toBe(1);
    });

    it('layar laporan dan ekspor; tanggal rusak tidak membuat galat; gabungan mengikuti hak akses', function () {
        martBuy($this, [$this->product->id => 1]);

        $this->actingAs($this->pengelola)->get(route('staff.merchants.report', $this->merchant))->assertOk()->assertSee('Rp 5.000');
        $this->get(route('staff.merchants.report', ['merchant' => $this->merchant, 'dari' => 'rusak', 'sampai' => '2026-13-45']))->assertOk();
        $this->get(route('staff.merchants.report.export', ['merchant' => $this->merchant->uuid, 'dari' => '2026-11-01', 'sampai' => '2026-11-30']))->assertOk();
        $this->get(route('staff.merchants.report.export', ['merchant' => $this->other->uuid]))->assertForbidden();
        $this->get(route('staff.merchants.report.export', ['merchant' => 'rusak']))->assertForbidden();

        $this->actingAs($this->bendahara)->get(route('staff.merchants.report.all'))->assertOk()->assertSee('Warung A');
    });

    it('beranda: hero penjualan hari ini dan stok menipis per merchant', function () {
        setStock($this->product, 3, 2);
        martBuy($this, [$this->product->id => 1]);

        $this->actingAs($this->pengelola);
        Livewire::test(MerchantAdmin::class)->assertSee('Rp 5.000')->assertSee('Warung A')->assertDontSee('Warung Lain')->assertDontSee('Tambah Merchant');

        $this->actingAs($this->kasir)->get(route('staff.merchants.index'))->assertForbidden();
    });

    it('rekonsiliasi: belanja dompet dan penjualan merchant harus sama', function () {
        martBuy($this, [$this->product->id => 2]);
        expect(MerchantLedgerEntry::salesMismatchedMerchants())->toBe([]);

        DB::table('merchant_ledger')->insert(['uuid' => (string) Str::uuid(), 'merchant_id' => $this->merchant->id, 'type' => 'sale', 'amount' => 1, 'balance_after' => 9801, 'created_at' => now()]);

        expect(MerchantLedgerEntry::salesMismatchedMerchants())->toBe([$this->merchant->id]);
        app(ReconcileWallets::class)->handle();
        expect(InAppNotification::where('title', 'Selisih saldo terdeteksi')->exists())->toBeTrue();
    });
});

function PosOrder()
{
    return PosOrder::query()->firstOrFail();
}
