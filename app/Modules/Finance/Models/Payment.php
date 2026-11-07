<?php

namespace App\Modules\Finance\Models;

use App\Modules\Core\Models\Guardian;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\Audited;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use Audited, HasPublicUuid;

    /** @var list<string> */
    protected array $auditExcept = ['gateway_payload'];

    protected $guarded = ['id', 'uuid'];

    protected $attributes = ['status' => 'pending', 'purpose' => 'invoice', 'admin_fee' => 0];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'admin_fee' => 'integer',
            'unique_code' => 'integer',
            'gross_amount' => 'integer',
            'gateway_payload' => 'array',
            'verified_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(Invoice::class, 'payment_allocations')->withPivot('amount');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class, 'payer_guardian_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Nominal yang harus ditransfer wali: tagihan, biaya admin, dan kode unik bila ada.
     */
    public function transferAmount(): int
    {
        return $this->gross_amount + (int) $this->unique_code;
    }

    /**
     * @param  Builder<Payment>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('finance.view')) {
            return $query;
        }

        return $query->whereHas('invoices', fn (Builder $invoices) => $invoices
            ->whereIn('student_id', Student::query()->visibleTo($user)->select('students.id')));
    }
}
