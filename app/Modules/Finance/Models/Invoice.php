<?php

namespace App\Modules\Finance\Models;

use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\Audited;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use Audited, HasPublicUuid, SoftDeletes;

    protected $guarded = ['id', 'uuid'];

    protected $attributes = ['status' => 'unpaid', 'source' => 'auto', 'paid_total' => 0, 'subtotal' => 0, 'discount_total' => 0, 'late_fee_total' => 0, 'total' => 0];

    protected function casts(): array
    {
        return [
            'period' => 'date',
            'due_date' => 'date',
            'subtotal' => 'integer',
            'discount_total' => 'integer',
            'late_fee_total' => 'integer',
            'total' => 'integer',
            'paid_total' => 'integer',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('id');
    }

    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(Payment::class, 'payment_allocations')->withPivot('amount');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function outstanding(): int
    {
        return max(0, $this->total - $this->paid_total);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['unpaid', 'partial', 'overdue'], true);
    }

    /**
     * @param  Builder<Invoice>  $query
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['unpaid', 'partial', 'overdue']);
    }

    /**
     * Kepemilikan dicek di query agar wali tidak bisa menebak tagihan anak lain.
     *
     * @param  Builder<Invoice>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('finance.view')) {
            return $query;
        }

        return $query->whereIn('student_id', Student::query()->visibleTo($user)->select('students.id'));
    }

    /**
     * Menghitung ulang total dari item lalu menurunkan status (PRD Sistem 8.4).
     */
    public function refreshTotals(): void
    {
        $items = $this->items()->get();

        $this->subtotal = $items->where('is_late_fee', false)->sum(fn (InvoiceItem $item) => $item->qty * $item->unit_price);
        $this->discount_total = $items->sum('discount');
        $this->late_fee_total = $items->where('is_late_fee', true)->sum('amount');
        $this->total = $items->sum('amount');
        $this->syncStatus();
    }

    public function syncStatus(): void
    {
        if ($this->status === 'void') {
            return;
        }

        $today = now(config('sanad.display_timezone'))->toDateString();

        $this->status = match (true) {
            $this->paid_total >= $this->total => 'paid',
            $this->due_date->toDateString() < $today => 'overdue',
            $this->paid_total > 0 => 'partial',
            default => 'unpaid',
        };
    }
}
