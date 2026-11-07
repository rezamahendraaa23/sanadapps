<?php

namespace App\Modules\Pos\Actions;

use App\Modules\Finance\Support\DocumentNumber;
use App\Modules\Finance\Support\Rupiah;
use App\Modules\Pos\Models\Merchant;
use App\Modules\Pos\Models\MerchantLedgerEntry;
use App\Modules\Pos\Models\MerchantProduct;
use App\Modules\Pos\Models\PosOrder;
use App\Modules\Pos\Models\PosOrderItem;
use App\Modules\Wallet\Actions\RecordScan;
use App\Modules\Wallet\Actions\WalletLedger;
use App\Modules\Wallet\Events\WalletSpent;
use App\Modules\Wallet\Models\StudentCard;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Support\CardResolver;
use App\Modules\Wallet\Support\ChargeResult;
use App\Modules\Wallet\Support\QrToken;
use App\Modules\Wallet\Support\WalletSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CheckoutOrder
{
    private const PIN_ATTEMPTS = 3;

    private const LOCK_MINUTES = 15;

    public function __construct(private WalletLedger $ledger, private RecordScan $scan) {}

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

            [$items, $total] = $priced;

            if (($denial = $this->evaluate($card, $wallet, $total, $pin, $merchant)) !== null) {
                if ($denial->code !== 'pin_required') {
                    $this->scan->handle($card, $deviceId, 'canteen', in_array($denial->code, ['card_blocked', 'wallet_blocked'], true) ? 'blocked' : 'denied', $denial->message);
                }

                return $denial;
            }

            if (! QrToken::consume($credential)) {
                $this->scan->handle($card, $deviceId, 'canteen', 'denied', 'QR dipakai dua kali.');

                return ChargeResult::denied('qr_invalid', 'QR ini sudah dipakai. Tunggu QR berikutnya.');
            }

            return $this->record($merchant, $items, $total, $card, $wallet, $resolved['method'], $key, $cashierId, $deviceId);
        });
    }

    /**
     * Urutan pemeriksaan mengikuti PRD Sistem 8.7.
     */
    private function evaluate(StudentCard $card, Wallet $wallet, int $total, ?string $pin, Merchant $merchant): ?ChargeResult
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

        if ($wallet->balance < $total) {
            return ChargeResult::denied('insufficient', 'Saldo kurang (sisa '.Rupiah::format($wallet->balance).').');
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
     * Harga selalu dari server, bukan dari layar kasir.
     *
     * @param  array<int, int>  $lines
     * @return array{0: list<array<string, mixed>>, 1: int}|null
     */
    private function price(Merchant $merchant, array $lines, ?int $amount): ?array
    {
        if ($lines === []) {
            return $amount !== null && $amount > 0
                ? [[['product_id' => null, 'name' => 'Belanja', 'qty' => 1, 'unit_price' => $amount, 'amount' => $amount]], $amount]
                : null;
        }

        $products = MerchantProduct::query()->where('merchant_id', $merchant->id)->where('is_active', true)->whereIn('id', array_keys($lines))->orderBy('id')->get()->keyBy('id');
        $items = [];

        foreach ($lines as $productId => $qty) {
            $product = $products[$productId] ?? null;

            if ($product === null || $qty < 1) {
                return null;
            }

            $items[] = ['product_id' => $product->id, 'name' => $product->name, 'qty' => $qty, 'unit_price' => $product->price, 'amount' => $product->price * $qty];
        }

        $total = array_sum(array_column($items, 'amount'));

        return $total > 0 ? [$items, $total] : null;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function record(Merchant $merchant, array $items, int $total, StudentCard $card, Wallet $wallet, string $method, string $key, ?int $cashierId, ?int $deviceId): ChargeResult
    {
        $lockedMerchant = Merchant::query()->whereKey($merchant->id)->lockForUpdate()->firstOrFail();
        $commission = $lockedMerchant->commissionFor($total);

        $order = PosOrder::create([
            'merchant_id' => $lockedMerchant->id,
            'receipt_no' => DocumentNumber::next('ST'),
            'cashier_user_id' => $cashierId,
            'device_id' => $deviceId,
            'student_id' => $card->student_id,
            'total' => $total,
            'commission' => $commission,
            'scan_method' => $method,
            'idempotency_key' => $key,
        ]);

        foreach ($items as $item) {
            PosOrderItem::create([...$item, 'pos_order_id' => $order->id]);
        }

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
