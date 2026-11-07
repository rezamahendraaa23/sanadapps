<?php

namespace App\Modules\Finance\Support;

final class Rupiah
{
    public static function format(int $amount): string
    {
        return ($amount < 0 ? '-' : '').'Rp '.number_format(abs($amount), 0, ',', '.');
    }
}
