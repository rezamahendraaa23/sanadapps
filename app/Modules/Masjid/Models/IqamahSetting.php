<?php

namespace App\Modules\Masjid\Models;

use App\Modules\Core\Support\Audited;
use Illuminate\Database\Eloquent\Model;

class IqamahSetting extends Model
{
    use Audited;

    protected $guarded = ['id'];
}
