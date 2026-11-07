<?php

namespace App\Modules\Academic\Face;

/**
 * Pengganti untuk pengembangan, BUKAN pengenal wajah: hanya membandingkan pola terang-gelap gambar.
 * Foto yang mirip mendapat skor tinggi, siapa pun orangnya, karena itu tidak tersedia di produksi.
 */
class FakeFaceMatcher implements FaceMatcher
{
    private const GRID = 16;

    /** Pengganti skor untuk test. */
    public static ?float $forcedScore = null;

    /** Pengganti hasil liveness untuk test. */
    public static ?bool $forcedLive = null;

    public function available(): bool
    {
        return ! app()->isProduction();
    }

    public function modelVersion(): string
    {
        return 'fake-luma-16';
    }

    public function runsInBrowser(): bool
    {
        return false;
    }

    public function defaultThresholds(): array
    {
        return ['upper' => 0.80, 'lower' => 0.60];
    }

    public function enroll(array $inputs): FaceTemplate
    {
        $vectors = [];
        $qualities = [];

        foreach (array_values($inputs) as $index => $input) {
            try {
                [$vectors[], $qualities[]] = $this->embed(FaceInput::wrap($input)->image);
            } catch (FaceQualityException $exception) {
                throw new FaceQualityException('Foto '.($index + 1).': '.$exception->getMessage());
            }
        }

        $average = [];

        foreach (array_keys($vectors[0]) as $position) {
            $average[] = array_sum(array_column($vectors, $position)) / count($vectors);
        }

        $length = sqrt(array_sum(array_map(fn (float $value) => $value * $value, $average))) ?: 1.0;

        return new FaceTemplate(array_map(fn (float $value) => round($value / $length, 6), $average), min($qualities));
    }

    public function identify(FaceInput|string $input, array $templates): ?FaceMatch
    {
        [$probe] = $this->embed(FaceInput::wrap($input)->image);
        $best = null;

        foreach ($templates as $studentId => $template) {
            $score = $this->similarity($probe, $template);

            if ($best === null || $score > $best->score) {
                $best = new FaceMatch((int) $studentId, $score);
            }
        }

        return $best;
    }

    /**
     * @return array{0: list<float>, 1: float} Vektor dan kualitas.
     */
    public function embed(string $image): array
    {
        $source = @imagecreatefromstring($image);

        if ($source === false) {
            throw new FaceQualityException('Gambar tidak bisa dibaca.');
        }

        if (imagesx($source) < 200 || imagesy($source) < 200) {
            throw new FaceQualityException('Foto terlalu kecil. Dekatkan kamera.');
        }

        $small = imagecreatetruecolor(self::GRID, self::GRID);
        imagecopyresampled($small, $source, 0, 0, 0, 0, self::GRID, self::GRID, imagesx($source), imagesy($source));

        $values = [];

        for ($y = 0; $y < self::GRID; $y++) {
            for ($x = 0; $x < self::GRID; $x++) {
                $rgb = imagecolorat($small, $x, $y);
                $values[] = 0.299 * (($rgb >> 16) & 255) + 0.587 * (($rgb >> 8) & 255) + 0.114 * ($rgb & 255);
            }
        }

        $mean = array_sum($values) / count($values);

        if ($mean < 30) {
            throw new FaceQualityException('Terlalu gelap. Cari tempat yang lebih terang.');
        }

        if ($mean > 235) {
            throw new FaceQualityException('Terlalu terang. Hindari cahaya langsung.');
        }

        $centered = array_map(fn (float $value) => $value - $mean, $values);
        $spread = sqrt(array_sum(array_map(fn (float $value) => $value * $value, $centered)) / count($centered));

        if ($spread < 8) {
            throw new FaceQualityException('Wajah tidak terlihat jelas.');
        }

        return [array_map(fn (float $value) => round($value / ($spread * sqrt(count($centered))), 6), $centered), round(min(1, $spread / 64), 3)];
    }

    public function similarity(array $first, array $second): float
    {
        if (self::$forcedScore !== null) {
            return self::$forcedScore;
        }

        $dot = 0.0;

        foreach ($first as $index => $value) {
            $dot += $value * ($second[$index] ?? 0.0);
        }

        return max(0.0, min(1.0, ($dot + 1) / 2));
    }

    public function isLive(string $challenge, FaceInput $input): bool
    {
        return self::$forcedLive ?? ($input->image !== '' && $input->secondFrame !== '' && ! hash_equals(md5($input->image), md5($input->secondFrame)));
    }
}
