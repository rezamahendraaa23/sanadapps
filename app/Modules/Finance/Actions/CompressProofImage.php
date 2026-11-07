<?php

namespace App\Modules\Finance\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CompressProofImage
{
    private const MAX_SIDE = 1600;

    private const JPEG_QUALITY = 80;

    /**
     * Menyimpan bukti; foto HP diperkecil agar hemat ruang. PDF dan gambar yang gagal dibaca
     * disimpan apa adanya, bukti tidak boleh hilang gara-gara kompresi.
     */
    public function store(UploadedFile $file, string $directory): string
    {
        $content = (string) file_get_contents($file->getRealPath());
        $image = in_array($file->getMimeType(), ['image/jpeg', 'image/png'], true) ? @imagecreatefromstring($content) : false;

        if ($image === false) {
            return $file->store($directory, 'local');
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, self::MAX_SIDE / max($width, $height));

        if ($scale < 1) {
            $image = imagescale($image, (int) round($width * $scale), (int) round($height * $scale));
        }

        $flattened = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefill($flattened, 0, 0, imagecolorallocate($flattened, 255, 255, 255));
        imagecopy($flattened, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

        ob_start();
        imagejpeg($flattened, null, self::JPEG_QUALITY);
        $compressed = (string) ob_get_clean();

        $path = $directory.'/'.pathinfo($file->hashName(), PATHINFO_FILENAME).'.jpg';
        Storage::disk('local')->put($path, $compressed);

        return $path;
    }
}
