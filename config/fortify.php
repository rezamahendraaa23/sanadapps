<?php

use Laravel\Fortify\Features;

return [
    'guard' => 'web',
    'passwords' => 'users',
    'username' => 'email',
    'email' => 'email',
    'lowercase_usernames' => true,
    'home' => '/panel',
    'prefix' => '',
    'domain' => null,
    'middleware' => ['web'],
    'limiters' => [],
    'views' => false,

    // 2FA baru berlaku setelah kode pertama dikonfirmasi, agar pengguna tidak terkunci bila batal di tengah jalan.
    'features' => [
        Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => false]),
    ],
];
