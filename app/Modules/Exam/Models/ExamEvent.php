<?php

namespace App\Modules\Exam\Models;

use Illuminate\Database\Eloquent\Model;

class ExamEvent extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['occurred_at' => 'immutable_datetime', 'meta' => 'array'];
    }
}
