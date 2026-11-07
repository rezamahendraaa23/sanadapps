<?php

namespace App\Modules\Letters\Models;

use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Letterhead extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public static function default(): ?self
    {
        return static::query()->where('is_default', true)->first() ?? static::query()->oldest('id')->first();
    }

    /**
     * Gambar disematkan ke HTML agar PDF tidak bergantung pada akses jaringan.
     */
    public function dataUri(string $which): ?string
    {
        $path = $which === 'footer' ? $this->footer_path : $this->header_path;

        if (! $path || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        return 'data:'.Storage::disk('local')->mimeType($path).';base64,'.base64_encode(Storage::disk('local')->get($path));
    }
}
