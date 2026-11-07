<?php

namespace App\Modules\Wallet\Models;

use App\Modules\Core\Support\Audited;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class WalletMerchantLimit extends Model
{
    use Audited, HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return ['daily_limit' => 'integer', 'is_blocked' => 'boolean'];
    }
}
