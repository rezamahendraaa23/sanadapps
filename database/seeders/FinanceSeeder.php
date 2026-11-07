<?php

namespace Database\Seeders;

use App\Modules\Finance\Models\CashCategory;
use Illuminate\Database\Seeder;

class FinanceSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            [CashCategory::INVOICE_PAYMENT, 'in'],
            [CashCategory::UNIQUE_CODE, 'in'],
            ['Pemasukan lain', 'in'],
            [CashCategory::REFUND, 'out'],
            [CashCategory::WALLET_TOPUP, 'in'],
            [CashCategory::WALLET_WITHDRAW, 'out'],
            ['Penyaluran merchant', 'out'],
            ['Operasional', 'out'],
            ['Gaji dan honor', 'out'],
            ['Pengeluaran lain', 'out'],
        ] as [$name, $type]) {
            CashCategory::named($name, $type);
        }
    }
}
