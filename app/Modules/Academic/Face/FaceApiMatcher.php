<?php

namespace App\Modules\Academic\Face;

/**
 * Pengenalan wajah di browser dengan @vladmandic/face-api: wajah menjadi 128 angka, server hanya menghitung jarak.
 * Skor = 1 - jarak. Ambang bawah 0,52 setara jarak 0,48 yang dipakai sistem absen DW; hadir otomatis lebih ketat
 * (jarak 0,40) karena di sini dicocokkan ke banyak santri, bukan ke satu akun. Wajib dikalibrasi di lokasi.
 */
class FaceApiMatcher implements FaceMatcher
{
    private const LENGTH = 128;

    /** Beda jarak minimum antara kandidat terbaik dan kedua; di bawah ini wajib dikonfirmasi musyrif. */
    private const MIN_MARGIN = 0.06;

    /** Foto pendaftaran yang berjarak lebih dari ini dianggap bukan orang yang sama. */
    private const MAX_ENROLL_SPREAD = 0.6;

    private const MIN_MOTION_FRAMES = 3;

    private const MIN_BLINK_RANGE = 0.05;

    private const MIN_TURN_RANGE = 0.12;

    public function available(): bool
    {
        return true;
    }

    public function modelVersion(): string
    {
        return 'face-api-1.7.15';
    }

    public function runsInBrowser(): bool
    {
        return true;
    }

    public function defaultThresholds(): array
    {
        return ['upper' => 0.60, 'lower' => 0.52];
    }

    public function enroll(array $inputs): FaceTemplate
    {
        $vectors = [];

        foreach (array_values($inputs) as $index => $input) {
            try {
                $vectors[] = $this->vector(FaceInput::wrap($input));
            } catch (FaceQualityException $exception) {
                throw new FaceQualityException('Foto '.($index + 1).': '.$exception->getMessage());
            }
        }

        foreach ($vectors as $index => $vector) {
            if ($index > 0 && $this->distance($vectors[0], $vector) > self::MAX_ENROLL_SPREAD) {
                throw new FaceQualityException('Foto '.($index + 1).' terlihat seperti orang lain. Ulangi dari awal.');
            }
        }

        $average = [];

        for ($position = 0; $position < self::LENGTH; $position++) {
            $average[] = round(array_sum(array_column($vectors, $position)) / count($vectors), 6);
        }

        return new FaceTemplate($average, 1.0);
    }

    public function identify(FaceInput|string $input, array $templates): ?FaceMatch
    {
        $probe = $this->vector(FaceInput::wrap($input));
        $distances = [];

        foreach ($templates as $studentId => $template) {
            $distances[(int) $studentId] = $this->distance($probe, array_values($template));
        }

        if ($distances === []) {
            return null;
        }

        asort($distances);
        $ordered = array_values($distances);

        return new FaceMatch(
            (int) array_key_first($distances),
            max(0.0, 1 - $ordered[0]),
            isset($ordered[1]) && $ordered[1] - $ordered[0] < self::MIN_MARGIN,
        );
    }

    public function isLive(string $challenge, FaceInput $input): bool
    {
        if (count($input->motion) < self::MIN_MOTION_FRAMES) {
            return false;
        }

        $values = array_map('floatval', array_column($input->motion, str_starts_with($challenge, 'Kedip') ? 'ear' : 'yaw'));

        if (count($values) < self::MIN_MOTION_FRAMES) {
            return false;
        }

        return max($values) - min($values) >= (str_starts_with($challenge, 'Kedip') ? self::MIN_BLINK_RANGE : self::MIN_TURN_RANGE);
    }

    /**
     * @return list<float>
     */
    private function vector(FaceInput $input): array
    {
        $vector = $input->descriptor;

        if (! is_array($vector) || count($vector) !== self::LENGTH || array_filter($vector, 'is_numeric') !== $vector) {
            throw new FaceQualityException('Wajah tidak terbaca.');
        }

        $vector = array_map('floatval', array_values($vector));
        $length = sqrt(array_sum(array_map(fn (float $value) => $value * $value, $vector)));

        // Hasil deteksi wajah panjangnya mendekati 1; semua nol atau angka acak jauh bukan wajah.
        if ($length < 0.5 || $length > 1.5) {
            throw new FaceQualityException('Wajah tidak terbaca.');
        }

        return $vector;
    }

    /**
     * @param  list<float>  $first
     * @param  list<float>  $second
     */
    private function distance(array $first, array $second): float
    {
        $sum = 0.0;

        foreach ($first as $index => $value) {
            $sum += ($value - (float) ($second[$index] ?? 0.0)) ** 2;
        }

        return sqrt($sum);
    }
}
