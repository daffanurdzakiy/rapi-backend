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

        $this->syncToPublicStorageDir($path, $compressedContent);

        return $path;
    }

    /**
     * Sinkronisasi file ke public/storage jika di hosting (cPanel) folder public/storage
     * berupa direktori biasa dan bukan symlink.
     */
    protected function syncToPublicStorageDir(string $path, string $content): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        try {
            $publicStorageRoot = public_path('storage');
            if (is_dir($publicStorageRoot) && ! is_link($publicStorageRoot)) {
                $targetFile = $publicStorageRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($path, '/'));
                $targetDir = dirname($targetFile);
                if (! is_dir($targetDir)) {
                    @mkdir($targetDir, 0755, true);
                }
                @file_put_contents($targetFile, $content);
            }
        } catch (\Throwable $e) {
            // Abaikan jika gagal menyalin ke public/storage
        }
    }

    /**
     * Membaca isi file foto dari disk 'public' dan mengenkodenya sebagai base64
     * untuk cadangan pemulihan otomatis di database.
     */
    public function encodePhotoFromDisk(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        try {
            $disk = Storage::disk('public');
            if ($disk->exists($path)) {
                $bytes = $disk->get($path);
                if (is_string($bytes) && $bytes !== '') {
                    return base64_encode($bytes);
                }
            }
        } catch (\Throwable $e) {
            // Abaikan jika gagal membaca
        }

        return null;
    }

    /**
     * Menemukan atau memulihkan file foto pengaduan di disk 'public' sehingga
     * tidak terjadi error 404 ketika file belum tersinkronisasi di server.
     */
    public function resolveComplaintPhotoPath(\App\Models\Complaint $complaint): ?string
    {
        $rawPath = trim((string) $complaint->photo_path);
        if ($rawPath === '') {
            return null;
        }

        $cleanPath = str_replace('\\', '/', $rawPath);
        $cleanPath = preg_replace('#^(public/|storage/|app/public/)+#i', '', $cleanPath);
        $cleanPath = ltrim((string) $cleanPath, '/');

        if ($cleanPath === '') {
            return null;
        }

        $disk = Storage::disk('public');

        // 1. Jika sudah ada di disk 'public', langsung kembalikan path-nya
        if ($disk->exists($cleanPath)) {
            return $cleanPath;
        }

        // 2. Cek berbagai kemungkinan lokasi path fisik di server
        $basename = basename($cleanPath);
        $candidates = [
            $disk->path($rawPath),
            storage_path('app/public/' . $cleanPath),
            storage_path('app/public/complaints/' . $basename),
            public_path('storage/' . $cleanPath),
            public_path('storage/complaints/' . $basename),
            public_path($cleanPath),
            storage_path('app/' . $cleanPath),
            base_path('storage/app/public/' . $cleanPath),
            base_path('storage/app/public/complaints/' . $basename),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && file_exists($candidate) && is_file($candidate)) {
                try {
                    $bytes = @file_get_contents($candidate);
                    if (is_string($bytes) && $bytes !== '') {
                        $disk->put($cleanPath, $bytes);
                        return $cleanPath;
                    }
                } catch (\Throwable $e) {
                    // Lanjut ke kandidat berikutnya
                }
            }
        }

        // 3. Pulihkan dari cadangan base64 di kolom photo_data jika tersedia
        if (! empty($complaint->photo_data)) {
            $decoded = base64_decode((string) $complaint->photo_data, true);
            if (is_string($decoded) && $decoded !== '') {
                try {
                    $disk->put($cleanPath, $decoded);
                    return $cleanPath;
                } catch (\Throwable $e) {
                    // Abaikan jika gagal menulis
                }
            }
        }

        return null;
    }

    /**
     * Menemukan atau memulihkan file foto bukti perbaikan pengaduan di disk 'public'.
     */
    public function resolveComplaintRepairPhotoPath(\App\Models\Complaint $complaint): ?string
    {
        $rawPath = trim((string) $complaint->repair_photo_path);
        if ($rawPath === '') {
            return null;
        }

        $cleanPath = str_replace('\\', '/', $rawPath);
        $cleanPath = preg_replace('#^(public/|storage/|app/public/)+#i', '', $cleanPath);
        $cleanPath = ltrim((string) $cleanPath, '/');

        if ($cleanPath === '') {
            return null;
        }

        $disk = Storage::disk('public');

        // 1. Jika sudah ada di disk 'public', langsung kembalikan path-nya
        if ($disk->exists($cleanPath)) {
            return $cleanPath;
        }

        // 2. Cek berbagai kemungkinan lokasi path fisik di server
        $basename = basename($cleanPath);
        $candidates = [
            $disk->path($rawPath),
            storage_path('app/public/' . $cleanPath),
            storage_path('app/public/complaints/' . $basename),
            public_path('storage/' . $cleanPath),
            public_path('storage/complaints/' . $basename),
            public_path($cleanPath),
            storage_path('app/' . $cleanPath),
            base_path('storage/app/public/' . $cleanPath),
            base_path('storage/app/public/complaints/' . $basename),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && file_exists($candidate) && is_file($candidate)) {
                try {
                    $bytes = @file_get_contents($candidate);
                    if (is_string($bytes) && $bytes !== '') {
                        $disk->put($cleanPath, $bytes);
                        return $cleanPath;
                    }
                } catch (\Throwable $e) {
                    // Lanjut ke kandidat berikutnya
                }
            }
        }

        // 3. Fallback terakhir: jika file fisik hilang, pulihkan dari kolom base64 'repair_photo_data'
        if (! empty($complaint->repair_photo_data)) {
            $decoded = base64_decode((string) $complaint->repair_photo_data, true);
            if (is_string($decoded) && $decoded !== '') {
                try {
                    $disk->put($cleanPath, $decoded);
                    return $cleanPath;
                } catch (\Throwable $e) {
                    // Abaikan jika gagal menulis
                }
            }
        }

        return null;
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

