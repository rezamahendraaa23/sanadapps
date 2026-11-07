<?php

namespace App\Modules\Exam\Support;

use RuntimeException;

class AttemptGone extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
