<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CompressExistingComplaintPhotos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'complaints:compress-photos {--force : Kompres ulang semua foto meskipun ukurannya sudah kecil}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kompres otomatis file foto bukti pengaduan fasilitas yang sudah tersimpan untuk menghemat ruang penyimpanan';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $disk = Storage::disk('public');
        $directory = 'complaints';

        if (! $disk->exists($directory)) {
            $this->info("Direktori '{$directory}' tidak ditemukan di storage publik.");
            return self::SUCCESS;
        }

        $files = $disk->files($directory);
        $totalFiles = count($files);

        if ($totalFiles === 0) {
            $this->info("Tidak ada file foto di dalam folder '{$directory}'.");
            return self::SUCCESS;
        }

        $this->info("Menemukan {$totalFiles} file foto. Memulai proses kompresi otomatis...");

        $force = $this->option('force');
        $processed = 0;
        $compressed = 0;
        $skipped = 0;
        $failed = 0;
        $bytesSaved = 0;

        $bar = $this->output->createProgressBar($totalFiles);
        $bar->start();

        foreach ($files as $filePath) {
            $processed++;
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

            if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'])) {
                $skipped++;
                $bar->advance();
                continue;
            }

            $originalSize = $disk->size($filePath);

            // Jika ukuran sudah di bawah 180 KB dan tidak dipaksa (--force), lewati
            if (! $force && $originalSize <= 180 * 1024) {
                $skipped++;
                $bar->advance();
                continue;
            }

            $fullPath = $disk->path($filePath);
            $newContent = $this->compressImageFile($fullPath);

            if ($newContent !== null) {
                $newSize = strlen($newContent);
                if ($newSize < $originalSize) {
                    $disk->put($filePath, $newContent);
                    $bytesSaved += ($originalSize - $newSize);
                    $compressed++;
                } else {
                    $skipped++;
                }
            } else {
                $failed++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $savedMb = number_format($bytesSaved / (1024 * 1024), 2);
        $this->table(
            ['Metrik', 'Jumlah'],
            [
                ['Total file diperiksa', $processed],
                ['Berhasil dikompresi', $compressed],
                ['Dilewati (sudah optimal / bukan gambar)', $skipped],
                ['Gagal diproses', $failed],
                ['Total ruang penyimpanan dihemat', "{$savedMb} MB (" . number_format($bytesSaved) . ' bytes)'],
            ]
        );

        $this->info('Proses kompresi foto bukti pengaduan selesai!');

        return self::SUCCESS;
    }

    /**
     * Kompresi file gambar ke format JPEG dengan resolusi maksimal 1200px dan kualitas 75.
     */
    protected function compressImageFile(string $fullPath): ?string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
            return null;
        }

        if (! file_exists($fullPath)) {
            return null;
        }

        @ini_set('memory_limit', '256M');

        $raw = @file_get_contents($fullPath);
        if (! is_string($raw) || empty($raw)) {
            return null;
        }

        $image = @imagecreatefromstring($raw);
        if ($image === false) {
            return null;
        }

        // Koreksi orientasi EXIF jika tersedia
        if (function_exists('exif_read_data')) {
            try {
                $exif = @exif_read_data($fullPath);
                $orientation = $exif['Orientation'] ?? null;
                if ($orientation) {
                    switch ($orientation) {
                        case 3:
                            $rotated = imagerotate($image, 180, 0);
                            if ($rotated !== false) {
                                imagedestroy($image);
                                $image = $rotated;
                            }
                            break;
                        case 6:
                            $rotated = imagerotate($image, -90, 0);
                            if ($rotated !== false) {
                                imagedestroy($image);
                                $image = $rotated;
                            }
                            break;
                        case 8:
                            $rotated = imagerotate($image, 90, 0);
                            if ($rotated !== false) {
                                imagedestroy($image);
                                $image = $rotated;
                            }
                            break;
                    }
                }
            } catch (\Throwable $e) {
                // Abaikan jika pembacaan EXIF gagal
            }
        }

        $maxDimension = 1200;
        $origWidth = imagesx($image);
        $origHeight = imagesy($image);

        if ($origWidth > $maxDimension || $origHeight > $maxDimension) {
            $scale = min($maxDimension / $origWidth, $maxDimension / $origHeight);
            $newWidth = (int) max(1, round($origWidth * $scale));
            $newHeight = (int) max(1, round($origHeight * $scale));

            $resized = imagecreatetruecolor($newWidth, $newHeight);
            if ($resized !== false) {
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

        ob_start();
        $encoded = imagejpeg($image, null, 75);
        $content = ob_get_clean();
        imagedestroy($image);

        return $encoded && is_string($content) ? $content : null;
    }
}

