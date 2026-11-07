<?php

namespace App\Modules\Pos\Actions;

use App\Modules\Finance\Support\DocumentNumber;
use App\Modules\Finance\Support\Rupiah;
use App\Modules\Pos\Models\Merchant;
use App\Modules\Pos\Models\MerchantLedgerEntry;
use App\Modules\Pos\Models\MerchantProduct;
use App\Modules\Pos\Models\PosOrder;
use App\Modules\Pos\Models\PosOrderItem;
use App\Modules\Pos\Models\PosPromotion;
use App\Modules\Pos\Models\PosShift;
use App\Modules\Wallet\Actions\RecordScan;
use App\Modules\Wallet\Actions\WalletLedger;
use App\Modules\Wallet\Events\WalletSpent;
use App\Modules\Wallet\Models\StudentCard;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\WalletMerchantLimit;
use App\Modules\Wallet\Support\CardResolver;
use App\Modules\Wallet\Support\ChargeResult;
use App\Modules\Wallet\Support\QrToken;
use App\Modules\Wallet\Support\WalletException;
use App\Modules\Wallet\Support\WalletSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CheckoutOrder
{
    private const PIN_ATTEMPTS = 3;

    private const LOCK_MINUTES = 15;

    public function __construct(private WalletLedger $ledger, private RecordScan $scan, private StockLedger $stock) {}

    /**
     * Satu transaksi: potong saldo, buat struk, kredit merchant (PRD Sistem 8.7b). Penolakan dikembalikan
     * sebagai hasil, bukan pengecualian, supaya hitungan PIN salah ikut tersimpan.
     *
     * @param  array<int, int>  $lines  Id produk ke jumlah. Kosong bila perangkat hanya mengirim nominal.
     */
    public function handle(Merchant $merchant, array $lines, ?int $amount, string $credential, ?string $pin, string $key, ?int $cashierId, ?int $deviceId = null): ChargeResult
    {
        if (($previous = $this->previous($key)) !== null) {
            return $previous;
        }

        $resolved = CardResolver::resolve($credential, true);
        $card = $resolved['card'];

        if ($card === null) {
            $this->scan->handle(null, $deviceId, 'canteen', 'not_found', $resolved['problem']);

            return ChargeResult::denied('card_not_found', (string) $resolved['problem']);
        }

        if ($resolved['problem'] !== null) {
            $this->scan->handle($card, $deviceId, 'canteen', 'denied', $resolved['problem']);

            return ChargeResult::denied('qr_invalid', $resolved['problem']);
        }

        if (! $merchant->is_active) {
            return ChargeResult::denied('merchant_inactive', 'Merchant ini sedang tidak aktif.');
        }

        if (! in_array($resolved['method'], $merchant->payment_modes ?? ['card', 'qr'], true)) {
            return ChargeResult::denied('mode_not_allowed', 'Cara bayar ini tidak dipakai di merchant ini.');
        }

        $wallet = Wallet::forStudent($card->student);

        return DB::transaction(function () use ($merchant, $lines, $amount, $credential, $pin, $key, $cashierId, $deviceId, $resolved, $card, $wallet): ChargeResult {
            $card = StudentCard::query()->whereKey($card->id)->lockForUpdate()->firstOrFail();
            $wallet = $this->ledger->lock($wallet->id);

            // Tap ganda yang menunggu kunci sampai di sini setelah transaksi pertama selesai.
            if (($previous = $this->previous($key)) !== null) {
                return $previous;
            }

            $priced = $this->price($merchant, $lines, $amount);

            if ($priced === null) {
                return ChargeResult::denied('invalid_cart', 'Keranjang tidak valid. Muat ulang daftar produk.');
            }

            if (is_string($priced)) {
                $this->scan->handle($card, $deviceId, 'canteen', 'denied', $priced);

                return ChargeResult::denied('out_of_stock', $priced);
            }

            [$items, $total, $products] = $priced;

            if (($denial = $this->evaluate($card, $wallet, $total, $pin, $merchant, $items, $products)) !== null) {
                if ($denial->code !== 'pin_required') {
                    $this->scan->handle($card, $deviceId, 'canteen', in_array($denial->code, ['card_blocked', 'wallet_blocked'], true) ? 'blocked' : 'denied', $denial->message);
                }

                return $denial;
            }

            if (! QrToken::consume($credential)) {
                $this->scan->handle($card, $deviceId, 'canteen', 'denied', 'QR dipakai dua kali.');

                return ChargeResult::denied('qr_invalid', 'QR ini sudah dipakai. Tunggu QR berikutnya.');
            }

            return $this->record($merchant, $items, $total, $products, $card, $wallet, $resolved['method'], $key, $cashierId, $deviceId);
        });
    }

    /**
     * Penjualan tunai untuk ustadz, tamu, atau wali yang berkunjung. Uang dipegang merchant, jadi
     * tidak ada potongan dompet, kredit buku besar merchant, maupun komisi.
     *
     * @param  array<int, int>  $lines
     */
    public function cash(Merchant $merchant, array $lines, int $paid, ?string $customerName, string $key, int $cashierId): PosOrder
    {
        if (($previous = PosOrder::query()->where('idempotency_key', $key)->first()) !== null) {
            return $previous;
        }

        if (! in_array('cash', $merchant->payment_modes ?? [], true)) {
            throw new WalletException('Merchant ini belum menerima pembayaran tunai.');
        }

        return DB::transaction(function () use ($merchant, $lines, $paid, $customerName, $key, $cashierId): PosOrder {
            $priced = $this->price($merchant, $lines, null);

            if ($priced === null) {
                throw new WalletException('Keranjang tidak valid. Muat ulang daftar produk.');
            }

            if (is_string($priced)) {
                throw new WalletException($priced);
            }

            [$items, $total, $products] = $priced;

            if ($paid < $total) {
                throw new WalletException('Uang yang diterima kurang dari total ('.Rupiah::format($total).').');
            }

            $order = PosOrder::create([
                'merchant_id' => $merchant->id,
                'receipt_no' => DocumentNumber::next('ST'),
                'cashier_user_id' => $cashierId,
                'shift_id' => $this->openShift($merchant->id, $cashierId),
                'customer_name' => filled($customerName) ? trim((string) $customerName) : null,
                'total' => $total,
                'discount' => array_sum(array_column($items, 'discount')),
                'commission' => 0,
                'scan_method' => 'cash',
                'paid_amount' => $paid,
                'change_amount' => $paid - $total,
                'idempotency_key' => $key,
            ]);

            $this->storeItems($order, $items, $products, $cashierId);

            return $order;
        });
    }

    private function openShift(int $merchantId, ?int $cashierId): ?int
    {
        return $cashierId === null ? null : PosShift::query()->where('merchant_id', $merchantId)->where('cashier_user_id', $cashierId)->whereNull('closed_at')->value('id');
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  Collection<int, MerchantProduct>  $products
     */
    private function storeItems(PosOrder $order, array $items, Collection $products, ?int $cashierId): void
    {
        foreach ($items as $item) {
            PosOrderItem::create([...$item, 'pos_order_id' => $order->id]);

            $product = $products[$item['product_id']] ?? null;

            if ($product?->stock !== null) {
                $this->stock->move($product, 'sale', -$item['qty'], null, $cashierId, 'pos_order', $order->id);
            }
        }
    }

    /**
     * Urutan pemeriksaan mengikuti PRD Sistem 8.7.
     */
    private function evaluate(StudentCard $card, Wallet $wallet, int $total, ?string $pin, Merchant $merchant, array $items, Collection $products): ?ChargeResult
    {
        if ($card->status !== 'active') {
            return ChargeResult::denied('card_blocked', 'Kartu diblokir.');
        }

        if ($card->isLocked()) {
            return ChargeResult::denied('card_locked', 'Kartu terkunci karena PIN salah. Coba lagi pukul '.$card->locked_until->setTimezone(config('sanad.display_timezone'))->format('H.i').'.');
        }

        if ($wallet->status !== 'active') {
            return ChargeResult::denied('wallet_blocked', 'Dompet sedang diblokir wali.');
        }

        if ($total > ($merchant->pin_threshold ?? WalletSettings::get('pin_threshold'))) {
            if ($card->pin_hash === null) {
                return ChargeResult::denied('pin_unset', 'Belanja ini butuh PIN, tetapi PIN belum dibuat. Minta wali membuatnya.');
            }

            if ($pin === null || $pin === '') {
                return ChargeResult::denied('pin_required', 'Masukkan PIN.');
            }

            if (! Hash::check($pin, $card->pin_hash)) {
                return $this->wrongPin($card);
            }
        }

        if ($wallet->per_transaction_limit !== null && $total > $wallet->per_transaction_limit) {
            return ChargeResult::denied('per_transaction_limit', 'Melebihi limit per transaksi ('.Rupiah::format($wallet->per_transaction_limit).').');
        }

        if ($wallet->daily_limit !== null) {
            $remaining = max(0, $wallet->daily_limit - $wallet->spentToday());

            if ($total > $remaining) {
                return ChargeResult::denied('daily_limit', 'Limit harian terlampaui (sisa '.Rupiah::format($remaining).').');
            }
        }

        if (($denial = $this->guardianRules($wallet, $total, $merchant, $items, $products)) !== null) {
            return $denial;
        }

        if ($wallet->balance < $total) {
            return ChargeResult::denied('insufficient', 'Saldo kurang (sisa '.Rupiah::format($wallet->balance).').');
        }

        return null;
    }

    /**
     * Aturan wali per merchant dan kategori (POS-08). Pesannya netral: nama kategori tidak disebut,
     * kasir hanya melihat item penyebabnya.
     *
     * @param  list<array<string, mixed>>  $items
     * @param  Collection<int, MerchantProduct>  $products
     */
    private function guardianRules(Wallet $wallet, int $total, Merchant $merchant, array $items, Collection $products): ?ChargeResult
    {
        $rules = WalletMerchantLimit::query()->where('wallet_id', $wallet->id)->get();

        if ($rules->isEmpty()) {
            return null;
        }

        $start = now(config('sanad.display_timezone'))->startOfDay()->utc();
        $merchantRule = $rules->firstWhere('merchant_id', $merchant->id);

        if ($merchantRule?->is_blocked) {
            return ChargeResult::denied('merchant_blocked', 'Dompet santri ini tidak bisa dipakai di merchant ini.');
        }

        if ($merchantRule?->daily_limit !== null) {
            $spent = (int) $wallet->transactions()->where('merchant_id', $merchant->id)->where('type', 'spend')->where('created_at', '>=', $start)->sum('amount')
                - (int) $wallet->transactions()->where('merchant_id', $merchant->id)->where('type', 'refund')->where('direction', 'credit')->where('created_at', '>=', $start)->sum('amount');
            $remaining = max(0, $merchantRule->daily_limit - $spent);

            if ($total > $remaining) {
                return ChargeResult::denied('merchant_limit', "Limit {$merchant->type} ini terlampaui (sisa ".Rupiah::format($remaining).').');
            }
        }

        foreach ($rules->whereNotNull('category_id')->groupBy('category_id') as $categoryId => $categoryRules) {
            $rule = $categoryRules->first();
            $matching = array_filter($items, fn (array $item) => $item['category_id'] === (int) $categoryId);

            if ($matching === []) {
                continue;
            }

            if ($rule->is_blocked) {
                return ChargeResult::denied('category_blocked', 'Produk ini tidak bisa dibeli dengan dompet ini: '.implode(', ', array_column($matching, 'name')).'.');
            }

            if ($rule->daily_limit !== null) {
                $spent = (int) DB::table('pos_order_items')
                    ->join('pos_orders', 'pos_orders.id', '=', 'pos_order_items.pos_order_id')
                    ->where('pos_orders.student_id', $wallet->student_id)->where('pos_orders.status', 'paid')->where('pos_orders.created_at', '>=', $start)
                    ->where('pos_order_items.category_id', (int) $categoryId)->sum('pos_order_items.amount');
                $remaining = max(0, $rule->daily_limit - $spent);

                if (array_sum(array_column($matching, 'amount')) > $remaining) {
                    return ChargeResult::denied('category_limit', 'Batas harian untuk '.implode(', ', array_column($matching, 'name')).' terlampaui (sisa '.Rupiah::format($remaining).').');
                }
            }
        }

        return null;
    }

    private function wrongPin(StudentCard $card): ChargeResult
    {
        $failed = $card->pin_failed_count + 1;

        if ($failed >= self::PIN_ATTEMPTS) {
            $card->update(['pin_failed_count' => 0, 'locked_until' => now()->addMinutes(self::LOCK_MINUTES)]);

            return ChargeResult::denied('card_locked', 'PIN salah 3 kali. Kartu terkunci '.self::LOCK_MINUTES.' menit.');
        }

        $card->update(['pin_failed_count' => $failed]);

        return ChargeResult::denied('pin_wrong', 'PIN salah. Sisa '.(self::PIN_ATTEMPTS - $failed).' percobaan.');
    }

    /**
     * Harga dan stok selalu dari server. Baris produk dikunci agar dua kasir tidak menjual stok terakhir yang sama.
     *
     * @param  array<int, int>  $lines
     * @return array{0: list<array<string, mixed>>, 1: int, 2: Collection<int, MerchantProduct>}|string|null
     */
    private function price(Merchant $merchant, array $lines, ?int $amount): array|string|null
    {
        if ($lines === []) {
            return $amount !== null && $amount > 0
                ? [[['product_id' => null, 'category_id' => null, 'cost_price' => null, 'name' => 'Belanja', 'qty' => 1, 'unit_price' => $amount, 'discount' => 0, 'promotion_id' => null, 'amount' => $amount]], $amount, collect()]
                : null;
        }

        $products = MerchantProduct::query()->where('merchant_id', $merchant->id)->where('is_active', true)->whereIn('id', array_keys($lines))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $promotions = PosPromotion::query()->where('merchant_id', $merchant->id)->runningOn(now(config('sanad.display_timezone'))->toDateString())->get();
        $items = [];

        foreach ($lines as $productId => $qty) {
            $product = $products[$productId] ?? null;

            if ($product === null || $qty < 1) {
                return null;
            }

            if ($product->stock !== null && $product->stock < $qty) {
                return "Stok {$product->name} tinggal {$product->stock}.";
            }

            [$discount, $promotionId] = self::bestPromotion($promotions, $product, $qty);

            $items[] = [
                'product_id' => $product->id,
                'category_id' => $product->category_id,
                'cost_price' => $product->cost_price,
                'name' => $product->name,
                'qty' => $qty,
                'unit_price' => $product->price,
                'discount' => $discount,
                'promotion_id' => $promotionId,
                'amount' => $product->price * $qty - $discount,
            ];
        }

        $total = array_sum(array_column($items, 'amount'));

        return $total > 0 ? [$items, $total, $products] : null;
    }

    /**
     * Potongan terbesar dari promo yang berlaku; promo tidak ditumpuk.
     *
     * @param  Collection<int, PosPromotion>  $promotions
     * @return array{0: int, 1: int|null}
     */
    public static function bestPromotion(Collection $promotions, MerchantProduct $product, int $qty): array
    {
        $best = [0, null];

        foreach ($promotions as $promotion) {
            if ($promotion->appliesTo($product) && ($discount = $promotion->discountFor($product->price, $qty)) > $best[0]) {
                $best = [$discount, $promotion->id];
            }
        }

        return $best;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  Collection<int, MerchantProduct>  $products
     */
    private function record(Merchant $merchant, array $items, int $total, Collection $products, StudentCard $card, Wallet $wallet, string $method, string $key, ?int $cashierId, ?int $deviceId): ChargeResult
    {
        $lockedMerchant = Merchant::query()->whereKey($merchant->id)->lockForUpdate()->firstOrFail();
        $commission = $lockedMerchant->commissionFor($total);

        $order = PosOrder::create([
            'merchant_id' => $lockedMerchant->id,
            'receipt_no' => DocumentNumber::next('ST'),
            'cashier_user_id' => $cashierId,
            'shift_id' => $this->openShift($lockedMerchant->id, $cashierId),
            'device_id' => $deviceId,
            'student_id' => $card->student_id,
            'total' => $total,
            'discount' => array_sum(array_column($items, 'discount')),
            'commission' => $commission,
            'scan_method' => $method,
            'idempotency_key' => $key,
        ]);

        $this->storeItems($order, $items, $products, $cashierId);

        $scan = $this->scan->handle($card, $deviceId, 'canteen', 'ok', null, 'pos_order', $order->id);

        $transaction = $this->ledger->post($wallet, 'spend', 'debit', $total, [
            'ref_type' => 'pos_order',
            'ref_id' => $order->id,
            'merchant_id' => $lockedMerchant->id,
            'card_scan_id' => $scan->id,
            'idempotency_key' => 'pos:'.$key,
            'created_by' => $cashierId,
        ]);

        $order->update(['wallet_transaction_id' => $transaction->id]);

        MerchantLedgerEntry::post($lockedMerchant, 'sale', $total, 'pos_order', $order->id);

        if ($commission > 0) {
            MerchantLedgerEntry::post($lockedMerchant, 'commission', -$commission, 'pos_order', $order->id);
        }

        if ($card->pin_failed_count > 0) {
            $card->update(['pin_failed_count' => 0]);
        }

        WalletSpent::dispatch($order, $wallet->balance);

        return ChargeResult::paid($order, $wallet->balance);
    }

    private function previous(string $key): ?ChargeResult
    {
        $order = PosOrder::query()->where('idempotency_key', $key)->with('walletTransaction')->first();

        return $order === null ? null : ChargeResult::paid($order, (int) $order->walletTransaction?->balance_after);
    }
}
