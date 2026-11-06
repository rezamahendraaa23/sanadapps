<?php

namespace App\Modules\Core\Actions;

use App\Modules\Core\Models\User;
use Illuminate\Support\Str;

class CreateFirstAdmin
{
    /**
     * @return string|null Sandi awal, atau null bila akun sudah ada.
     */
    public function handle(string $email, string $name = 'Admin Pesantren'): ?string
    {
        if (User::query()->where('email', $email)->exists()) {
            return null;
        }

        $password = Str::password(16, symbols: false);

        User::create(['name' => $name, 'email' => $email, 'type' => 'staff', 'password' => $password])->assignRole('admin');

        return $password;
    }
}
