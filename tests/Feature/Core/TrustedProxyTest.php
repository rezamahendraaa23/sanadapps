<?php

use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/_uji-proxy', fn () => response()->json(['secure' => request()->isSecure(), 'url' => url('/masuk')]));
});

it('memakai https dan alamat dari proxy di mesin yang sama (Cloudflare Tunnel)', function () {
    $this->get('/_uji-proxy', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'contoh.trycloudflare.com'])
        ->assertJson(['secure' => true, 'url' => 'https://contoh.trycloudflare.com/masuk']);
});

it('mengabaikan header proxy palsu dari alamat luar', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
        ->get('/_uji-proxy', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'jahat.example'])
        ->assertJson(['secure' => false])->assertJsonMissing(['url' => 'https://jahat.example/masuk']);
});
