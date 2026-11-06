<?php

namespace App\Support;

use App\Modules\Core\Models\SchoolProfile;
use Illuminate\Support\Facades\Storage;

/**
 * Sumber tunggal identitas pesantren untuk tampilan. Isian di Pengaturan selalu menang atas nilai awal di config.
 */
class SchoolIdentity
{
    public const APP_NAME = 'SANAD';

    public const TAGLINE = 'Pesantren Digital Ecosystem';

    private ?SchoolProfile $profile = null;

    private bool $loaded = false;

    public function name(): ?string
    {
        $name = trim((string) ($this->profile()?->name ?: config('sanad.school.name')));

        return $name === '' ? null : $name;
    }

    public function displayName(): string
    {
        return $this->name() ?? self::APP_NAME;
    }

    public function logoUrl(): ?string
    {
        $path = $this->profile()?->logo_path;

        return $path ? Storage::disk('public')->url($path) : (config('sanad.school.logo_url') ?: null);
    }

    public function brandCss(): ?string
    {
        return BrandColor::cssOverrides($this->profile()?->brand_color ?? config('sanad.school.brand_color'));
    }

    public function timezone(): string
    {
        return $this->profile()?->timezone ?? config('sanad.display_timezone');
    }

    public function forget(): void
    {
        $this->profile = null;
        $this->loaded = false;
    }

    private function profile(): ?SchoolProfile
    {
        if (! $this->loaded) {
            $this->profile = SchoolProfile::query()->first();
            $this->loaded = true;
        }

        return $this->profile;
    }
}
