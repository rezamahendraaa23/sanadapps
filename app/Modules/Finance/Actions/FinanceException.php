<?php

namespace App\Modules\Finance\Actions;

use RuntimeException;

/**
 * Penolakan aturan bisnis keuangan; pesannya aman ditampilkan apa adanya ke pengguna.
 */
class FinanceException extends RuntimeException {}
