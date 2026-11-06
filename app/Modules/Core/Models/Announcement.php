<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Support\Audited;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use Audited, HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    /**
     * Pengumuman yang sudah tayang untuk kelas dan asrama para santri ini; null berarti hanya yang umum.
     *
     * @param  iterable<Student>  $students
     */
    public function scopePublishedFor(Builder $query, iterable $students): Builder
    {
        $students = collect($students);

        return $query
            ->whereNotNull('publish_at')
            ->where('publish_at', '<=', now())
            ->where(fn (Builder $audience) => $audience
                ->where('audience', 'all')
                ->orWhere(fn (Builder $scoped) => $scoped->where('audience', 'classroom')->whereIn('audience_id', $students->pluck('classroom_id')->filter()))
                ->orWhere(fn (Builder $scoped) => $scoped->where('audience', 'dormitory')->whereIn('audience_id', $students->pluck('dormitory_id')->filter())))
            ->latest('publish_at');
    }

    protected function casts(): array
    {
        return ['publish_at' => 'immutable_datetime', 'send_wa' => 'boolean'];
    }
}
