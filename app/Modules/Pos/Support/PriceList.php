<?php

namespace App\Modules\Pos\Support;

use App\Modules\Core\Models\SchoolProfile;
use App\Modules\Pos\Actions\CheckoutOrder;
use App\Modules\Pos\Models\Merchant;
use App\Modules\Pos\Models\MerchantProduct;
use App\Modules\Pos\Models\PosPromotion;
use App\Support\SchoolIdentity;
use Illuminate\Support\Facades\Storage;

/**
 * Data daftar harga PDF: produk aktif per kategori tanpa foto (katalog bisa ratusan barang), dan harga promo yang berlaku hari ini.
 */
final class PriceList
{
    private const PAYMENT_LABELS = ['card' => 'Kartu santri', 'qr' => 'QR', 'cash' => 'Tunai'];

    /**
     * @return array<string, mixed>
     */
    public static function data(Merchant $merchant): array
    {
        $today = now(config('sanad.display_timezone'));
        $products = $merchant->products()->with('category')->where('is_active', true)->orderBy('name')->get();
        $promotions = PosPromotion::query()->where('merchant_id', $merchant->id)->runningOn($today->toDateString())->get();

        $rows = $products->map(function (MerchantProduct $product) use ($promotions) {
            [$discount, $promotionId] = CheckoutOrder::bestPromotion($promotions, $product, 1);
            // Promo dengan syarat jumlah tidak menurunkan harga satuan, jadi cukup dicatat sebagai keterangan.
            $note = $promotions->first(fn (PosPromotion $promotion) => $promotion->min_qty > 1 && $promotion->appliesTo($product));

            return [
                'name' => $product->name,
                'barcode' => $product->barcode,
                'price' => $product->price,
                'promo_price' => $discount > 0 ? $product->price - $discount : null,
                'promo_label' => $discount > 0 ? $promotions->firstWhere('id', $promotionId)?->name : null,
                'bundle_note' => $note ? 'Beli '.$note->min_qty.', '.($note->kind === 'percent' ? 'hemat '.$note->value.'%' : 'hemat per barang') : null,
                'sold_out' => $product->isOutOfStock(),
                'category' => $product->category?->name ?? 'Lainnya',
            ];
        });

        $groups = $rows->groupBy('category')->sortKeys()->all();
        if (isset($groups['Lainnya'])) {
            $groups = [...array_diff_key($groups, ['Lainnya' => true]), 'Lainnya' => $groups['Lainnya']];
        }

        return [
            'merchant' => $merchant,
            'school' => app(SchoolIdentity::class)->displayName(),
            'logo' => self::logo(),
            'date' => $today->translatedFormat('j F Y'),
            'groups' => $groups,
            'count' => $rows->count(),
            'promoCount' => $rows->whereNotNull('promo_price')->count(),
            'payments' => collect($merchant->payment_modes ?? [])->map(fn (string $mode) => self::PAYMENT_LABELS[$mode] ?? $mode)->values()->all(),
        ];
    }

    private static function logo(): ?string
    {
        $path = SchoolProfile::query()->value('logo_path');

        return $path && Storage::disk('public')->exists($path) ? 'data:'.Storage::disk('public')->mimeType($path).';base64,'.base64_encode(Storage::disk('public')->get($path)) : null;
    }
}
