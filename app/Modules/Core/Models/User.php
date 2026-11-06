<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Support\Audited;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use Audited, HasPublicUuid, HasRoles, TwoFactorAuthenticatable;

    protected $guarded = ['id', 'uuid'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'immutable_datetime',
            'two_factor_confirmed_at' => 'immutable_datetime',
        ];
    }

    public function isStaff(): bool
    {
        return $this->type === 'staff';
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function guardian(): HasOne
    {
        return $this->hasOne(Guardian::class);
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    public function inAppNotifications(): HasMany
    {
        return $this->hasMany(InAppNotification::class);
    }
}
