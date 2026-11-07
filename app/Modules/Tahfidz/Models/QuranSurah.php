<?php

namespace App\Modules\Tahfidz\Models;

use Illuminate\Database\Eloquent\Model;

class QuranSurah extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'number';

    protected $guarded = [];

    public function label(): string
    {
        return $this->number.'. '.$this->name_latin;
    }
}
