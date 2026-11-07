<?php

namespace App\Modules\Finance\Models;

use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class CashCategory extends Model
{
    use HasPublicUuid;

    public const INVOICE_PAYMENT = 'Pembayaran tagihan';

    public const UNIQUE_CODE = 'Pembulatan/kode unik';

    public const REFUND = 'Pengembalian dana';

    protected $guarded = ['id', 'uuid'];

    public static function named(string $name, string $type): self
    {
        return static::query()->firstOrCreate(['name' => $name, 'type' => $type]);
    }
}
