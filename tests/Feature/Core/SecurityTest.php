<?php

use App\Modules\Core\Models\User;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    (new ModuleSeeder)->run();
    (new RoleSeeder)->run();
});

it('setiap respons membawa header keamanan dan CSP yang membatasi sumber ke domain sendiri', function () {
    $response = $this->get(route('login'))->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    $csp = $response->headers->get('Content-Security-Policy');
    expect($csp)->toContain("default-src 'self'")
        ->toContain("object-src 'none'")
        ->toContain("frame-ancestors 'self'")
        ->toContain("base-uri 'self'");
    expect($response->headers->has('Strict-Transport-Security'))->toBeFalse();

    $this->get('https://localhost'.route('login', absolute: false))->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('API dan layar TV ikut membawa header keamanan', function () {
    $this->postJson('/api/v1/display/pair/start')->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('Horizon hanya untuk pengelola pengaturan', function () {
    $guardian = tap(User::create(['name' => 'Wali', 'phone' => '6281200000009', 'type' => 'guardian']))->assignRole('wali');
    $this->actingAs($guardian)->get('/horizon')->assertForbidden();

    $teacher = tap(User::create(['name' => 'Ustadz', 'email' => 'ust@uji.test', 'type' => 'staff']))->assignRole('ustadz');
    $this->actingAs($teacher)->get('/horizon')->assertForbidden();
});
