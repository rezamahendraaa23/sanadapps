<?php

namespace App\Modules\Pos\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Angka laporan penjualan dari transaksi berstatus lunas; rentang tanggal menurut zona pesantren.
 */
final class SalesStats
{
    /**
     * @param  list<int>  $merchantIds
     * @return array{revenue: int, orders: int, commission: int, net: int, margin: int, margin_complete: bool}
     */
    public static function summary(array $merchantIds, string $from, string $to): array
    {
        $row = self::orders($merchantIds, $from, $to)->selectRaw('COALESCE(SUM(total), 0) AS revenue, COUNT(*) AS orders, COALESCE(SUM(commission), 0) AS commission')->first();

        $margin = self::items($merchantIds, $from, $to)
            ->selectRaw('COALESCE(SUM(CASE WHEN pos_order_items.cost_price IS NOT NULL THEN (pos_order_items.unit_price - pos_order_items.cost_price) * pos_order_items.qty ELSE 0 END), 0) AS margin, COUNT(*) FILTER (WHERE pos_order_items.cost_price IS NULL) AS unknown')
            ->first();

        return [
            'revenue' => (int) $row->revenue,
            'orders' => (int) $row->orders,
            'commission' => (int) $row->commission,
            'net' => (int) $row->revenue - (int) $row->commission,
            'margin' => (int) $margin->margin,
            'margin_complete' => (int) $margin->unknown === 0,
        ];
    }

    /**
     * @param  list<int>  $merchantIds
     * @return Collection<int, object>
     */
    public static function byProduct(array $merchantIds, string $from, string $to, int $limit = 50): Collection
    {
        return self::items($merchantIds, $from, $to)
            ->selectRaw('pos_order_items.name, SUM(pos_order_items.qty) AS qty, SUM(pos_order_items.amount) AS amount')
            ->groupBy('pos_order_items.name')->orderByDesc('amount')->limit($limit)->get();
    }

    /**
     * @param  list<int>  $merchantIds
     * @return Collection<int, object>
     */
    public static function byCategory(array $merchantIds, string $from, string $to): Collection
    {
        return self::items($merchantIds, $from, $to)
            ->leftJoin('product_categories', 'product_categories.id', '=', 'pos_order_items.category_id')
            ->selectRaw("COALESCE(product_categories.name, 'Tanpa kategori') AS name, SUM(pos_order_items.qty) AS qty, SUM(pos_order_items.amount) AS amount")
            ->groupByRaw("COALESCE(product_categories.name, 'Tanpa kategori')")->orderByDesc('amount')->get();
    }

    /**
     * @param  list<int>  $merchantIds
     * @return Collection<int, object> Jam ramai menurut waktu pesantren.
     */
    public static function byHour(array $merchantIds, string $from, string $to): Collection
    {
        $zone = DB::getPdo()->quote(config('sanad.display_timezone'));

        return self::orders($merchantIds, $from, $to)
            ->selectRaw("EXTRACT(HOUR FROM pos_orders.created_at AT TIME ZONE {$zone})::int AS hour, COUNT(*) AS orders, SUM(total) AS amount")
            ->groupBy('hour')->orderBy('hour')->get();
    }

    /**
     * @param  list<int>  $merchantIds
     * @return Collection<int, object>
     */
    public static function byMerchant(array $merchantIds, string $from, string $to): Collection
    {
        return self::orders($merchantIds, $from, $to)
            ->join('merchants', 'merchants.id', '=', 'pos_orders.merchant_id')
            ->selectRaw('merchants.name, COUNT(*) AS orders, SUM(total) AS amount, SUM(commission) AS commission')
            ->groupBy('merchants.name')->orderByDesc('amount')->get();
    }

    /**
     * @param  list<int>  $merchantIds
     */
    private static function orders(array $merchantIds, string $from, string $to)
    {
        [$start, $end] = self::range($from, $to);

        return DB::table('pos_orders')->whereIn('pos_orders.merchant_id', $merchantIds)->where('pos_orders.status', 'paid')
            ->where('pos_orders.created_at', '>=', $start)->where('pos_orders.created_at', '<', $end);
    }

    /**
     * @param  list<int>  $merchantIds
     */
    private static function items(array $merchantIds, string $from, string $to)
    {
        return self::orders($merchantIds, $from, $to)->join('pos_order_items', 'pos_order_items.pos_order_id', '=', 'pos_orders.id');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function range(string $from, string $to): array
    {
        $zone = config('sanad.display_timezone');

        return [
            CarbonImmutable::parse($from, $zone)->startOfDay()->utc()->format('Y-m-d H:i:s.uP'),
            CarbonImmutable::parse($to, $zone)->addDay()->startOfDay()->utc()->format('Y-m-d H:i:s.uP'),
        ];
    }
}
