<?php

namespace App\Modules\Academic\Face;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Satu pengambilan wajah. Driver di server memakai gambar; driver di browser mengirim 128 angka dan gerakannya.
 */
final readonly class FaceInput
{
    /**
     * @param  list<float>|null  $descriptor
     * @param  list<array{ear: float, yaw: float}>  $motion
     */
    public function __construct(public string $image = '', public ?array $descriptor = null, public array $motion = [], public string $secondFrame = '') {}

    public static function wrap(self|string $input): self
    {
        return $input instanceof self ? $input : new self($input);
    }

    public static function fromFiles(UploadedFile $photo, UploadedFile $frame): self
    {
        return new self((string) file_get_contents($photo->getRealPath()), secondFrame: (string) file_get_contents($frame->getRealPath()));
    }

    /**
     * @return array{0: self, 1: UploadedFile|null} Masukan dan foto untuk bukti bila perlu ditinjau.
     */
    public static function fromRequest(Request $request, FaceMatcher $matcher): array
    {
        if ($matcher->runsInBrowser()) {
            $data = $request->validate(['descriptor' => 'required|json', 'motion' => 'required|json', 'photo' => 'nullable|image|max:8192', 'liveness_token' => 'required|string']);

            return [new self(descriptor: (array) json_decode($data['descriptor'], true), motion: (array) json_decode($data['motion'], true)), $data['photo'] ?? null];
        }

        $data = $request->validate(['photo' => 'required|image|max:8192', 'liveness_frame' => 'required|image|max:8192', 'liveness_token' => 'required|string']);

        return [self::fromFiles($data['photo'], $data['liveness_frame']), $data['photo']];
    }
}
