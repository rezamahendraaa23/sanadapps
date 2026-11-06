<?php

use App\Modules\Core\Actions\CreateFirstAdmin;
use App\Modules\Core\Actions\OtpResult;
use App\Modules\Core\Actions\RequestLoginOtp;
use App\Modules\Core\Actions\VerifyLoginOtp;
use App\Modules\Core\Contracts\OtpSender;
use App\Modules\Core\Livewire\Auth\Login;
use App\Modules\Core\Livewire\Auth\TwoFactorChallenge;
use App\Modules\Core\Livewire\Auth\VerifyOtp;
use App\Modules\Core\Models\OtpCode;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\PhoneNumber;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    (new RoleSeeder)->run();
    RateLimiter::clear('otp-request:6281234567890');

    $this->sentOtp = new class implements OtpSender
    {
        public array $codes = [];

        public function send(string $phone, string $code): void
        {
            $this->codes[] = $code;
        }
    };
    $this->app->instance(OtpSender::class, $this->sentOtp);

    $this->guardian = User::create(['name' => 'Wali Uji', 'phone' => '6281234567890', 'type' => 'guardian']);
    $this->guardian->assignRole('wali');
    $this->staff = User::create(['name' => 'Admin', 'email' => 'admin@uji.test', 'type' => 'staff', 'password' => 'sandi-rahasia']);
    $this->staff->assignRole('admin');
    $this->student = User::create(['name' => 'Santri', 'username' => '2026001', 'type' => 'student', 'password' => 'sandi-santri']);
    $this->student->assignRole('santri');
});

it('menormalkan nomor telepon ke format 62', function (string $input, ?string $expected) {
    expect(PhoneNumber::normalize($input))->toBe($expected);
})->with([
    ['0812-3456-7890', '6281234567890'],
    ['+62 812 3456 7890', '6281234567890'],
    ['81234567890', '6281234567890'],
    ['021555', null],
    ['abc', null],
]);

it('membuat OTP 6 digit berlaku 5 menit tanpa menyimpan kode asli', function () {
    app(RequestLoginOtp::class)->handle('6281234567890');

    $otp = OtpCode::first();
    expect($this->sentOtp->codes[0])->toMatch('/^\d{6}$/')
        ->and($otp->code_hash)->not->toBe($this->sentOtp->codes[0])
        ->and(Hash::check($this->sentOtp->codes[0], $otp->code_hash))->toBeTrue()
        ->and(abs($otp->expires_at->diffInSeconds(now()->addMinutes(5))))->toBeLessThan(5);
});

it('membatasi permintaan OTP 3 kali per 10 menit', function () {
    $action = app(RequestLoginOtp::class);

    expect([$action->handle('6281234567890'), $action->handle('6281234567890'), $action->handle('6281234567890'), $action->handle('6281234567890')])
        ->toBe([true, true, true, false]);
});

it('tidak mengirim kode ke nomor tak terdaftar tetapi jawabannya sama', function () {
    RateLimiter::clear('otp-request:6289999999999');

    Livewire::test(Login::class)->set('phone', '089999999999')->call('requestOtp')->assertRedirect(route('login.otp'));

    expect($this->sentOtp->codes)->toBe([]);
});

it('memasukkan wali dengan OTP benar dan kode tidak bisa dipakai ulang', function () {
    app(RequestLoginOtp::class)->handle('6281234567890');
    $code = $this->sentOtp->codes[0];

    Livewire::withQueryParams([])->test(VerifyOtp::class)->assertRedirect(route('login'));

    session(['otp_phone' => '6281234567890']);
    Livewire::test(VerifyOtp::class)->set('code', $code)->call('verify')->assertRedirect(route('wali.home'));

    expect(Auth::id())->toBe($this->guardian->id)
        ->and(app(VerifyLoginOtp::class)->handle('6281234567890', $code)[0])->toBe(OtpResult::Expired);
});

it('memberi tahu sisa percobaan lalu mengunci setelah 3 kali salah', function () {
    app(RequestLoginOtp::class)->handle('6281234567890');
    $right = $this->sentOtp->codes[0];
    $wrong = $right === '000000' ? '000001' : '000000';

    session(['otp_phone' => '6281234567890']);
    $component = Livewire::test(VerifyOtp::class);

    $component->set('code', $wrong)->call('verify')->assertSee('OTP salah, sisa 2 percobaan');
    $component->set('code', $wrong)->call('verify')->assertSee('OTP salah, sisa 1 percobaan');
    $component->set('code', $wrong)->call('verify')->assertSee('Percobaan habis');
    $component->set('code', $right)->call('verify')->assertSee('Percobaan habis');

    expect(Auth::check())->toBeFalse();
});

it('menolak OTP kedaluwarsa', function () {
    app(RequestLoginOtp::class)->handle('6281234567890');
    OtpCode::query()->update(['expires_at' => now()->subSecond()]);

    expect(app(VerifyLoginOtp::class)->handle('6281234567890', $this->sentOtp->codes[0])[0])->toBe(OtpResult::Expired);
});

it('memasukkan staf dengan email dan santri dengan NIS', function () {
    Livewire::test(Login::class)->call('selectTab', 'staf')->set('identifier', 'admin@uji.test')->set('password', 'sandi-rahasia')
        ->call('loginWithPassword')->assertRedirect(route('staff.dashboard'));
    expect(Auth::id())->toBe($this->staff->id);
    Auth::logout();

    Livewire::test(Login::class)->call('selectTab', 'santri')->set('identifier', '2026001')->set('password', 'sandi-santri')
        ->call('loginWithPassword')->assertRedirect(route('santri.home'));
    expect(Auth::id())->toBe($this->student->id);
});

it('tidak membedakan email salah dan sandi salah, lalu mengunci sementara', function () {
    RateLimiter::clear('login:staff:admin@uji.test|127.0.0.1');
    $component = Livewire::test(Login::class)->call('selectTab', 'staf')->set('identifier', 'admin@uji.test')->set('password', 'keliru');

    foreach (range(1, 5) as $attempt) {
        $component->call('loginWithPassword')->assertSee('Email atau sandi salah.');
    }

    $component->set('password', 'sandi-rahasia')->call('loginWithPassword')->assertSee('Terlalu banyak percobaan');
    expect(Auth::check())->toBeFalse();
    RateLimiter::clear('login:staff:admin@uji.test|127.0.0.1');
});

it('menolak staf masuk lewat tab santri dan akun nonaktif', function () {
    Livewire::test(Login::class)->call('selectTab', 'santri')->set('identifier', 'admin@uji.test')->set('password', 'sandi-rahasia')
        ->call('loginWithPassword')->assertSee('NIS atau sandi salah.');

    $this->staff->update(['is_active' => false]);
    Livewire::test(Login::class)->call('selectTab', 'staf')->set('identifier', 'admin@uji.test')->set('password', 'sandi-rahasia')
        ->call('loginWithPassword')->assertSee('sedang dinonaktifkan');

    expect(Auth::check())->toBeFalse();
});

it('mengeluarkan akun yang dinonaktifkan saat sesi masih berjalan', function () {
    $this->actingAs($this->staff);
    $this->staff->update(['is_active' => false]);

    $this->get(route('staff.dashboard'))->assertRedirect(route('login'));
    expect(Auth::check())->toBeFalse();
});

it('meminta tamu masuk dan mengarahkan pengguna ke portal miliknya', function () {
    $this->get(route('staff.dashboard'))->assertRedirect(route('login'));

    $this->actingAs($this->guardian)->get(route('staff.dashboard'))->assertRedirect(route('wali.home'));
    $this->actingAs($this->student)->get(route('wali.home'))->assertRedirect(route('santri.home'));
    $this->actingAs($this->staff)->get(route('santri.home'))->assertRedirect(route('staff.dashboard'));
});

it('meminta kode kedua bila 2FA aktif dan baru membuka sesi setelah kode benar', function () {
    app(EnableTwoFactorAuthentication::class)($this->staff);
    $google2fa = new Google2FA;
    $secret = decrypt($this->staff->fresh()->two_factor_secret);
    app(ConfirmTwoFactorAuthentication::class)($this->staff->fresh(), $google2fa->getCurrentOtp($secret));

    Livewire::test(Login::class)->call('selectTab', 'staf')->set('identifier', 'admin@uji.test')->set('password', 'sandi-rahasia')
        ->call('loginWithPassword')->assertRedirect(route('login.two-factor'));
    expect(Auth::check())->toBeFalse();

    Livewire::test(TwoFactorChallenge::class)->set('code', '000000')->call('verify')->assertSee('Kode salah');
    expect(Auth::check())->toBeFalse();

    // Kode yang sudah dipakai saat konfirmasi ditolak Fortify di jendela waktu yang sama.
    cache()->flush();

    Livewire::test(TwoFactorChallenge::class)->set('code', $google2fa->getCurrentOtp($secret))->call('verify')->assertRedirect(route('staff.dashboard'));
    expect(Auth::id())->toBe($this->staff->id);
});

it('menyimpan waktu dalam UTC apa pun zona waktu server database', function () {
    expect(collect(DB::select('SHOW TIME ZONE'))->first()->TimeZone)->toBe('UTC');

    app(RequestLoginOtp::class)->handle('6281234567890');

    expect(abs(OtpCode::first()->created_at->diffInSeconds(now())))->toBeLessThan(5);
});

it('membuat admin pertama sekali saja dengan sandi acak', function () {
    $password = app(CreateFirstAdmin::class)->handle('pertama@uji.test');

    expect($password)->toHaveLength(16)
        ->and(User::where('email', 'pertama@uji.test')->first()->isAdmin())->toBeTrue()
        ->and(app(CreateFirstAdmin::class)->handle('pertama@uji.test'))->toBeNull();
});
