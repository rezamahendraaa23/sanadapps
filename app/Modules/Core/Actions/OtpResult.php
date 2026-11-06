<?php

namespace App\Modules\Core\Actions;

enum OtpResult
{
    case Valid;
    case Wrong;
    case Expired;
    case Locked;
}
