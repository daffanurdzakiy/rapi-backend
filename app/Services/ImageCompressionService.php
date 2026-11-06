<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Service untuk mengompresi foto bukti kerusakan secara otomatis.
 * Mengurangi ukuran file foto kamera beresolusi tinggi hingga 90-95%
 * tanpa mengurangi kejelasan bukti visual kerusakan fasilitas.
 */
class ImageCompressionService
{
    /**
     * Kompres dan simpan file foto yang diunggah ke disk 'public'.
     *
     * @param UploadedFile $photo File foto dari form / request
     * @param string $directory Subdirektori penyimpanan di storage (default: 'complaints')
     * @param int $maxDimension Batas maksimal resolusi lebar / tinggi dalam pixel (default: 1200)
     * @param int $quality Kualitas kompresi JPEG 1-100 (default: 75)
     * @return string Path relatif file yang disimpan
     */
    public function compressAndStore(
        UploadedFile $photo,
        string $directory = 'complaints',
        int $maxDimension = 1200,
        int $quality = 75
    ): string {
        // Fallback jika ekstensi GD tidak aktif
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
            return $this->storeFallback($photo, $directory);
        }

        $realPath = $photo->getRealPath();
        if (! $realPath || ! file_exists($realPath)) {
            return $this->storeFallback($photo, $directory);
        }

        // Naikkan batas memory limit sementara untuk memproses foto kamera HP beresolusi tinggi (12-48MP)
        $this->ensureSufficientMemory();

        $dimensions = @getimagesize($realPath);
        if ($dimensions === false) {
            return $this->storeFallback($photo, $directory);
        }

        $rawContent = @file_get_contents($realPath);
        if (! is_string($rawContent) || empty($rawContent)) {
            return $this->storeFallback($photo, $directory);
        }

        $image = @imagecreatefromstring($rawContent);
        if ($image === false) {
            return $this->storeFallback($photo, $directory);
        }

        // 1. Koreksi orientasi EXIF (mencegah foto HP miring / terbalik)
        $image = $this->fixExifOrientation($image, $realPath);

        // 2. Resize proporsional jika melebihi batas resolusi maksimal (1200px)
        $origWidth = imagesx($image);
        $origHeight = imagesy($image);

        if ($origWidth > $maxDimension || $origHeight > $maxDimension) {
            $scale = min($maxDimension / $origWidth, $maxDimension / $origHeight);
            $newWidth = (int) max(1, round($origWidth * $scale));
            $newHeight = (int) max(1, round($origHeight * $scale));

            $resized = imagecreatetruecolor($newWidth, $newHeight);
            if ($resized !== false) {
                // Isi latar belakang dengan warna putih untuk menangani gambar berlatar transparan (PNG/WebP)
                $white = imagecolorallocate($resized, 255, 255, 255);
                imagefill($resized, 0, 0, $white);

                imagecopyresampled(
                    $resized,
                    $image,
                    0, 0, 0, 0,
                    $newWidth,
                    $newHeight,
                    $origWidth,
                    $origHeight
                );
                imagedestroy($image);
                $image = $resized;
            }
        }

        // 3. Kompresi gambar menjadi format JPEG teroptimasi
        ob_start();
        $encoded = imagejpeg($image, null, $quality);
        $compressedContent = ob_get_clean();
        imagedestroy($image);

        if (! $encoded || ! is_string($compressedContent)) {
            return $this->storeFallback($photo, $directory);
        }

        // 4. Simpan ke storage publik dengan nama UUID unik
        $filename = Str::uuid()->toString() . '.jpg';
        $path = trim($directory, '/') . '/' . $filename;

        if (! Storage::disk('public')->put($path, $compressedContent)) {
            throw new RuntimeException('Foto gagal disimpan ke penyimpanan disk.');
        }

        return $path;
    }

    /**
     * Memperbaiki orientasi foto berdasarkan metadata EXIF dari kamera HP.
     */
    protected function fixExifOrientation($image, string $filePath)
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        try {
            $exif = @exif_read_data($filePath);
            $orientation = $exif['Orientation'] ?? null;

            if ($orientation) {
                switch ($orientation) {
                    case 3:
                        $rotated = imagerotate($image, 180, 0);
                        if ($rotated !== false) {
                            imagedestroy($image);
                            return $rotated;
                        }
                        break;
                    case 6:
                        $rotated = imagerotate($image, -90, 0);
                        if ($rotated !== false) {
                            imagedestroy($image);
                            return $rotated;
                        }
                        break;
                    case 8:
                        $rotated = imagerotate($image, 90, 0);
                        if ($rotated !== false) {
                            imagedestroy($image);
                            return $rotated;
                        }
                        break;
                }
            }
        } catch (\Throwable $e) {
            // Lanjutkan jika pembacaan EXIF gagal
        }

        return $image;
    }

    /**
     * Memastikan memori PHP memadai selama proses kompresi.
     */
    protected function ensureSufficientMemory(): void
    {
        $currentLimit = ini_get('memory_limit');
        if ($currentLimit === '-1') {
            return;
        }

        @ini_set('memory_limit', '256M');
    }

    /**
     * Simpan file asli tanpa kompresi jika modul pengolah gambar tidak tersedia.
     */
    protected function storeFallback(UploadedFile $photo, string $directory): string
    {
        $path = $photo->store($directory, 'public');
        if (! is_string($path)) {
            throw new RuntimeException('Foto gagal disimpan.');
        }

        return $path;
    }
}

