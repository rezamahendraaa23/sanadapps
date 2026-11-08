<?php

namespace App\Modules\Core\Models;

use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrgPosition extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function chart(): BelongsTo
    {
        return $this->belongsTo(OrgChart::class, 'chart_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function initials(): string
    {
        $source = $this->person_name ?: $this->title;
        $words = preg_split('/\s+/u', trim((string) preg_replace('/^(KH|K\.H|Ust|Ustadz|Ustadzah|H|Hj|Dr|Drs|Ir)\.?\s+/iu', '', $source))) ?: [$source];

        return mb_strtoupper(collect($words)->filter()->take(2)->map(fn (string $word) => mb_substr($word, 0, 1))->implode(''));
    }
}
