<?php

namespace App\Modules\Core\Models;

use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrgChart extends Model
{
    use HasPublicUuid;

    /** Dua bagan bawaan: yayasan dari pendiri, kesantrian dari rois. */
    public const DEFAULTS = [
        'yayasan' => ['name' => 'Struktur Yayasan', 'root' => 'Pendiri', 'tv' => false],
        'kesantrian' => ['name' => 'Struktur Kesantrian', 'root' => 'Rois', 'tv' => true],
    ];

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return ['show_on_tv' => 'boolean'];
    }

    public function positions(): HasMany
    {
        return $this->hasMany(OrgPosition::class, 'chart_id');
    }

    public static function ensureDefaults(): void
    {
        foreach (self::DEFAULTS as $key => $default) {
            $chart = static::query()->firstOrCreate(['key' => $key], ['name' => $default['name'], 'show_on_tv' => $default['tv']]);

            if ($chart->wasRecentlyCreated) {
                $chart->positions()->create(['title' => $default['root']]);
            }
        }
    }

    /**
     * Pohon jabatan: tiap simpul membawa anak-anaknya, urut sesuai urutan yang diatur.
     *
     * @return list<array{position: OrgPosition, children: list<mixed>}>
     */
    public function tree(): array
    {
        $byParent = $this->positions()->orderBy('sort_order')->orderBy('id')->get()->groupBy(fn (OrgPosition $position) => (int) $position->parent_id);

        $build = function (int $parentId) use (&$build, $byParent): array {
            return ($byParent[$parentId] ?? collect())->map(fn (OrgPosition $position) => ['position' => $position, 'children' => $build($position->id)])->values()->all();
        };

        return $build(0);
    }
}
