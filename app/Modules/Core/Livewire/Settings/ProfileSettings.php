<?php

namespace App\Modules\Core\Livewire\Settings;

use App\Modules\Core\Models\SchoolProfile;
use App\Support\BrandColor;
use DateTimeZone;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.staff', ['active' => 'profile'])]
#[Title('Profil & Branding')]
class ProfileSettings extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $address = '';

    public string $phone = '';

    public string $email = '';

    public string $brand_color = BrandColor::DEFAULT;

    public string $timezone = 'Asia/Jakarta';

    public string $nis_pattern = '{tahun}{urut:4}';

    public $logo = null;

    public function mount(): void
    {
        $profile = SchoolProfile::current();

        $this->fill([
            'name' => (string) $profile->name,
            'address' => (string) $profile->address,
            'phone' => (string) $profile->phone,
            'email' => (string) $profile->email,
            'brand_color' => $profile->brand_color,
            'timezone' => $profile->timezone,
            'nis_pattern' => $profile->nis_pattern,
        ]);
    }

    public function save(): void
    {
        $this->brand_color = strtoupper(trim($this->brand_color));

        $data = $this->validate([
            'name' => 'required|string|max:150',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:150',
            'brand_color' => ['required', 'regex:/^#[0-9A-F]{6}$/'],
            'timezone' => ['required', 'in:'.implode(',', DateTimeZone::listIdentifiers(DateTimeZone::ASIA))],
            'nis_pattern' => ['required', 'string', 'max:50', 'regex:/\{urut:\d\}/'],
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
        ], [
            'nis_pattern.regex' => 'Pola NIS harus memuat nomor urut, contoh {tahun}{urut:4}.',
            'brand_color.regex' => 'Warna ditulis dalam format #RRGGBB, contoh #0B5D45.',
            'logo.max' => 'Logo maksimal 2 MB.',
        ]);

        if (! BrandColor::isAccessible($data['brand_color'])) {
            $suggestion = BrandColor::suggestAccessible($data['brand_color']);
            $this->addError('brand_color', "Warna ini terlalu terang untuk teks putih. Coba {$suggestion}.");

            return;
        }

        $profile = SchoolProfile::current();
        unset($data['logo']);

        if ($this->logo) {
            if ($profile->logo_path) {
                Storage::disk('public')->delete($profile->logo_path);
            }

            $data['logo_path'] = $this->logo->store('branding', 'public');
            $this->reset('logo');
        }

        $profile->update($data);

        $this->dispatch('toast', message: 'Profil pesantren tersimpan.', tone: 'success');
    }

    public function removeLogo(): void
    {
        $profile = SchoolProfile::current();

        if ($profile->logo_path) {
            Storage::disk('public')->delete($profile->logo_path);
            $profile->update(['logo_path' => null]);
        }

        $this->dispatch('toast', message: 'Logo dihapus.', tone: 'success');
    }

    public function render(): View
    {
        $valid = BrandColor::isValid($this->brand_color);

        return view('core.settings.profile', [
            'profile' => SchoolProfile::current(),
            'colorIsValid' => $valid,
            'contrast' => $valid ? BrandColor::contrastWithWhite($this->brand_color) : null,
            'colorPasses' => $valid && BrandColor::isAccessible($this->brand_color),
        ]);
    }
}
