<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Support\Audited;
use App\Support\BrandColor;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class SchoolProfile extends Model
{
    use Audited, HasPublicUuid;

    protected $table = 'school_profile';

    protected $guarded = ['id', 'uuid'];

    protected $attributes = ['brand_color' => BrandColor::DEFAULT, 'timezone' => 'Asia/Jakarta', 'nis_pattern' => '{tahun}{urut:4}'];

    protected function casts(): array
    {
        return ['setup_dismissed_at' => 'immutable_datetime'];
    }

    public static function current(): self
    {
        return static::query()->first() ?? static::create([]);
    }
}
