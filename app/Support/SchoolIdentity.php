<?php

namespace App\Support;

/**
 * Sumber tunggal identitas pesantren untuk tampilan. Tahap 1 mengganti sumbernya ke school_profile.
 */
final class SchoolIdentity
{
    public const APP_NAME = 'SANAD';

    public const TAGLINE = 'Pesantren Digital Ecosystem';

    public function name(): ?string
    {
        $name = trim((string) config('sanad.school.name'));

        return $name === '' ? null : $name;
    }

    public function displayName(): string
    {
        return $this->name() ?? self::APP_NAME;
    }

    public function logoUrl(): ?string
    {
        return config('sanad.school.logo_url') ?: null;
    }

    public function brandCss(): ?string
    {
        return BrandColor::cssOverrides(config('sanad.school.brand_color'));
    }
}
