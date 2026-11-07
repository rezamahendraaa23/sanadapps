<?php

namespace App\Modules\Academic\Face;

final readonly class FaceMatch
{
    /**
     * @param  bool  $ambiguous  Ada santri lain yang hampir sama miripnya, jadi tidak boleh hadir otomatis.
     */
    public function __construct(public int $studentId, public float $score, public bool $ambiguous = false) {}
}
