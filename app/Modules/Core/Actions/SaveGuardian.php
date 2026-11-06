<?php

namespace App\Modules\Core\Actions;

use App\Modules\Core\Models\Guardian;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\DB;

class SaveGuardian
{
    /**
     * Wali masuk dengan OTP, jadi akunnya dibuat tanpa sandi. Satu nomor WA mewakili satu wali
     * yang boleh punya banyak anak (COR-08).
     *
     * @param  array<string, mixed>  $data  phone_wa sudah ternormalisasi.
     */
    public function handle(array $data, ?Guardian $guardian = null): Guardian
    {
        return DB::transaction(function () use ($data, $guardian): Guardian {
            $guardian ??= Guardian::withTrashed()->where('phone_wa', $data['phone_wa'])->first();

            if ($guardian === null) {
                $user = User::query()->where('type', 'guardian')->where('phone', $data['phone_wa'])->first()
                    ?? User::create(['name' => $data['name'], 'phone' => $data['phone_wa'], 'type' => 'guardian']);
                $user->assignRole('wali');

                return Guardian::create([...$data, 'user_id' => $user->id]);
            }

            if ($guardian->trashed()) {
                $guardian->restore();
            }

            $guardian->update($data);
            $guardian->user?->update(['name' => $guardian->name, 'phone' => $guardian->phone_wa]);

            return $guardian;
        });
    }
}
