<?php

namespace App\Modules\Wallet\Models;

use App\Modules\Core\Support\Audited;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    use Audited, HasPublicUuid;

    public const SCOPES = [
        'attendance' => 'Absensi',
        'gate' => 'Gerbang',
        'canteen' => 'Belanja',
        'library' => 'Perpustakaan',
        'meal' => 'Makan',
    ];

    /** @var list<string> */
    protected array $auditExcept = ['api_key_hash', 'last_seen_at'];

    protected $guarded = ['id', 'uuid'];

    protected $hidden = ['api_key_hash'];

    protected function casts(): array
    {
        return ['scopes' => 'array', 'is_active' => 'boolean', 'last_seen_at' => 'immutable_datetime'];
    }
}
