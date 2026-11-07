<?php

namespace App\Modules\Wallet\Models;

use App\Modules\Core\Models\Student;
use App\Modules\Core\Support\Audited;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    use Audited, HasPublicUuid;

    /** @var list<string> */
    protected array $auditExcept = ['balance'];

    protected $guarded = ['id', 'uuid', 'balance'];

    protected $attributes = ['balance' => 0, 'status' => 'active'];

    protected function casts(): array
    {
        return ['balance' => 'integer', 'daily_limit' => 'integer', 'per_transaction_limit' => 'integer'];
    }

    public static function forStudent(Student $student): self
    {
        return static::query()->createOrFirst(['student_id' => $student->id])->refresh();
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /**
     * Belanja bersih hari ini menurut zona pesantren; belanja yang dibatalkan mengembalikan limit.
     */
    public function spentToday(): int
    {
        $start = now(config('sanad.display_timezone'))->startOfDay()->utc();

        $spent = (int) $this->transactions()->where('type', 'spend')->where('created_at', '>=', $start)->sum('amount');
        $refunded = (int) $this->transactions()->where('type', 'refund')->where('direction', 'credit')->whereNotNull('merchant_id')->where('created_at', '>=', $start)->sum('amount');

        return max(0, $spent - $refunded);
    }
}
