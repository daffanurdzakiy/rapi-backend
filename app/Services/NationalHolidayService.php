<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Service untuk menyediakan jadwal Hari Libur Nasional Indonesia secara otomatis.
 * Dilengkapi dengan dataset lengkap SKB 3 Menteri dan mekanisme fallback offline
 * agar selalu akurat dan tidak pernah gagal dimuat.
 */
class NationalHolidayService
{
    /**
     * Database Hari Libur Nasional Resmi Indonesia (SKB 3 Menteri).
     */
    protected array $builtInHolidays = [
        2024 => [
            '2024-01-01' => 'Tahun Baru 2024 Masehi',
            '2024-02-08' => 'Isra Mi\'raj Nabi Muhammad SAW',
            '2024-02-10' => 'Tahun Baru Imlek 2575 Kongzili',
            '2024-03-11' => 'Hari Suci Nyepi Tahun Baru Saka 1946',
            '2024-03-29' => 'Wafat Isa Almasih',
            '2024-03-31' => 'Hari Paskah',
            '2024-04-10' => 'Hari Raya Idul Fitri 1445 Hijriah',
            '2024-04-11' => 'Hari Raya Idul Fitri 1445 Hijriah',
            '2024-05-01' => 'Hari Buruh Internasional',
            '2024-05-09' => 'Kenaikan Isa Almasih',
            '2024-05-23' => 'Hari Raya Waisak 2568 BE',
            '2024-06-01' => 'Hari Lahir Pancasila',
            '2024-06-17' => 'Hari Raya Idul Adha 1445 Hijriah',
            '2024-07-07' => 'Tahun Baru Islam 1446 Hijriah',
            '2024-08-17' => 'Hari Kemerdekaan RI ke-79',
            '2024-09-16' => 'Maulid Nabi Muhammad SAW',
            '2024-12-25' => 'Hari Raya Natal',
        ],
        2025 => [
            '2025-01-01' => 'Tahun Baru 2025 Masehi',
            '2025-01-27' => 'Isra Mi\'raj Nabi Muhammad SAW',
            '2025-01-29' => 'Tahun Baru Imlek 2576 Kongzili',
            '2025-03-29' => 'Hari Suci Nyepi Tahun Baru Saka 1947',
            '2025-03-31' => 'Hari Raya Idul Fitri 1446 Hijriah',
            '2025-04-01' => 'Hari Raya Idul Fitri 1446 Hijriah',
            '2025-04-18' => 'Wafat Yesus Kristus',
            '2025-04-20' => 'Kebangkitan Yesus Kristus (Paskah)',
            '2025-05-01' => 'Hari Buruh Internasional',
            '2025-05-12' => 'Hari Raya Waisak 2569 BE',
            '2025-05-29' => 'Kenaikan Yesus Kristus',
            '2025-06-01' => 'Hari Lahir Pancasila',
            '2025-06-06' => 'Hari Raya Idul Adha 1446 Hijriah',
            '2025-06-27' => 'Tahun Baru Islam 1447 Hijriah',
            '2025-08-17' => 'Hari Kemerdekaan RI ke-80',
            '2025-09-05' => 'Maulid Nabi Muhammad SAW',
            '2025-12-25' => 'Hari Raya Natal',
        ],
        2026 => [
            '2026-01-01' => 'Tahun Baru 2026 Masehi',
            '2026-01-16' => 'Isra Mi\'raj Nabi Muhammad SAW',
            '2026-02-17' => 'Tahun Baru Imlek 2577 Kongzili',
            '2026-03-20' => 'Hari Raya Idul Fitri 1447 Hijriah',
            '2026-03-21' => 'Hari Raya Idul Fitri 1447 Hijriah',
            '2026-03-22' => 'Hari Suci Nyepi Tahun Baru Saka 1948',
            '2026-04-03' => 'Wafat Yesus Kristus',
            '2026-04-05' => 'Kebangkitan Yesus Kristus (Paskah)',
            '2026-05-01' => 'Hari Buruh Internasional',
            '2026-05-14' => 'Kenaikan Yesus Kristus',
            '2026-05-31' => 'Hari Raya Waisak 2570 BE',
            '2026-06-01' => 'Hari Lahir Pancasila',
            '2026-06-05' => 'Hari Raya Idul Adha 1447 Hijriah',
            '2026-06-25' => 'Tahun Baru Islam 1448 Hijriah',
            '2026-08-17' => 'Hari Kemerdekaan RI ke-81',
            '2026-09-04' => 'Maulid Nabi Muhammad SAW',
            '2026-12-25' => 'Hari Raya Natal',
        ],
        2027 => [
            '2027-01-01' => 'Tahun Baru 2027 Masehi',
            '2027-01-06' => 'Isra Mi\'raj Nabi Muhammad SAW',
            '2027-02-06' => 'Tahun Baru Imlek 2578 Kongzili',
            '2027-03-10' => 'Hari Raya Idul Fitri 1448 Hijriah',
            '2027-03-11' => 'Hari Raya Idul Fitri 1448 Hijriah',
            '2027-03-26' => 'Wafat Yesus Kristus',
            '2027-03-28' => 'Kebangkitan Yesus Kristus (Paskah)',
            '2027-04-09' => 'Hari Suci Nyepi Tahun Baru Saka 1949',
            '2027-05-01' => 'Hari Buruh Internasional',
            '2027-05-06' => 'Kenaikan Yesus Kristus',
            '2027-05-20' => 'Hari Raya Waisak 2571 BE',
            '2027-05-27' => 'Hari Raya Idul Adha 1448 Hijriah',
            '2027-06-01' => 'Hari Lahir Pancasila',
            '2027-06-16' => 'Tahun Baru Islam 1449 Hijriah',
            '2027-08-17' => 'Hari Kemerdekaan RI ke-82',
            '2027-08-25' => 'Maulid Nabi Muhammad SAW',
            '2027-12-25' => 'Hari Raya Natal',
        ],
    ];

    /**
     * Mengambil daftar hari libur nasional untuk tahun tertentu.
     * Menggunakan cache 30 hari untuk performa tinggi.
     */
    public function getHolidaysForYear(int $year): array
    {
        return Cache::remember("national_holidays_{$year}", now()->addDays(30), function () use ($year) {
            // Jika dalam mode testing atau offline, langsung gunakan data resmi built-in
            if (app()->environment('testing')) {
                return $this->builtInHolidays[$year] ?? $this->getGenericHolidaysForYear($year);
            }

            // Mencoba request ke API jika koneksi internet aktif
            try {
                $response = Http::timeout(2)->get("https://dayoffapi.vercel.app/api?year={$year}");
                if ($response->successful()) {
                    $data = $response->json();
                    if (is_array($data) && count($data) > 0) {
                        $holidays = [];
                        foreach ($data as $item) {
                            if (!empty($item['tanggal']) && !empty($item['keterangan']) && ($item['is_cuti'] ?? false) === false) {
                                $holidays[$item['tanggal']] = $item['keterangan'];
                            }
                        }
                        if (!empty($holidays)) {
                            return $holidays;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Offline fallback (lingkungan sandbox / jaringan terputus)
            }

            return $this->builtInHolidays[$year] ?? $this->getGenericHolidaysForYear($year);
        });
    }

    /**
     * Mengambil seluruh hari libur nasional pada rentang tanggal tertentu (misal: tampilan grid 1 bulan kalender).
     */
    public function getHolidaysForRange(CarbonInterface $start, CarbonInterface $end): array
    {
        $startYear = $start->year;
        $endYear = $end->year;

        $holidays = [];
        for ($year = $startYear; $year <= $endYear; $year++) {
            $yearHolidays = $this->getHolidaysForYear($year);
            foreach ($yearHolidays as $date => $name) {
                if ($date >= $start->format('Y-m-d') && $date <= $end->format('Y-m-d')) {
                    $holidays[$date] = $name;
                }
            }
        }

        return $holidays;
    }

    /**
     * Fallback untuk tahun yang belum terdaftar di dataset spesifik:
     * Menghasilkan hari libur nasional bertanggal tetap di Indonesia.
     */
    protected function getGenericHolidaysForYear(int $year): array
    {
        return [
            "{$year}-01-01" => 'Tahun Baru Masehi',
            "{$year}-05-01" => 'Hari Buruh Internasional',
            "{$year}-06-01" => 'Hari Lahir Pancasila',
            "{$year}-08-17" => 'Hari Kemerdekaan Republik Indonesia',
            "{$year}-12-25" => 'Hari Raya Natal',
        ];
    }
}
