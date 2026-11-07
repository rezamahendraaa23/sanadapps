<?php

namespace App\Modules\Exam\Models;

use App\Modules\Core\Support\Audited;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Question extends Model
{
    use Audited, HasPublicUuid, SoftDeletes;

    public const TYPES = ['mcq' => 'Pilihan ganda', 'multi' => 'Pilihan ganda kompleks', 'true_false' => 'Benar atau salah', 'short' => 'Isian singkat', 'essay' => 'Esai'];

    protected $guarded = ['id', 'uuid'];

    protected $hidden = ['answer_key'];

    protected $attributes = ['direction' => 'ltr', 'tags' => '[]'];

    protected function casts(): array
    {
        return ['options' => 'array', 'answer_key' => 'array', 'tags' => 'array', 'points' => 'float'];
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class, 'bank_id');
    }

    public function hasOptions(): bool
    {
        return in_array($this->type, ['mcq', 'multi', 'true_false'], true);
    }

    /**
     * @return list<array{key: string, text: string}>
     */
    public function optionList(): array
    {
        return $this->type === 'true_false'
            ? [['key' => 'true', 'text' => 'Benar'], ['key' => 'false', 'text' => 'Salah']]
            : ($this->options ?? []);
    }
}
