<?php

use App\Http\Middleware\EnsurePreviewAllowed;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

Route::middleware(EnsurePreviewAllowed::class)->prefix('_pratinjau')->name('preview.')->group(function (): void {
    Route::view('/', 'preview.gallery')->name('gallery');
    Route::view('/staf', 'preview.staff')->name('staff');
    Route::get('/portal/{role}', fn (string $role) => view('preview.portal', ['role' => $role]))
        ->whereIn('role', ['wali', 'santri', 'ustadz', 'musyrif'])
        ->name('portal');
});
