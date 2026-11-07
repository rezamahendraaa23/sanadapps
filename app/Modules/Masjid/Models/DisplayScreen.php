<?php

namespace App\Modules\Masjid\Models;

use App\Modules\Core\Support\Audited;
use App\Modules\Wallet\Models\Device;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisplayScreen extends Model
{
    use Audited, HasPublicUuid;

    public const PROFILES = ['masjid' => 'Masjid', 'canteen' => 'Kantin', 'lobby' => 'Lobi atau asrama', 'announcement_only' => 'Hanya pengumuman'];

    public const OFFLINE_AFTER_MINUTES = 5;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return ['brightness_schedule' => 'array', 'is_active' => 'boolean', 'arabic_digits' => 'boolean', 'last_seen_at' => 'immutable_datetime'];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at !== null && $this->last_seen_at->greaterThan(now()->subMinutes(self::OFFLINE_AFTER_MINUTES));
    }
}
