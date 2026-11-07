<?php

namespace App\Modules\Academic\Face;

final readonly class FaceMatch
{
    public function __construct(public int $studentId, public float $score) {}
}
