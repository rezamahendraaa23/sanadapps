<?php

namespace App\Modules\Academic\Face;

final readonly class FaceTemplate
{
    /**
     * @param  array<int|string, mixed>  $data  Embedding, atau rujukan ke perangkat pada adapter Jalur A.
     */
    public function __construct(public array $data, public float $quality) {}
}
