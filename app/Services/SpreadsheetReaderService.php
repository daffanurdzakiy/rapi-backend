<?php

namespace App\Services;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Service mandiri untuk membaca file Spreadsheet (CSV dan Excel .xlsx)
 * secara native tanpa memerlukan library eksternal pihak ketiga (seperti PhpSpreadsheet).
 * Sangat cepat, hemat memori, dan kompatibel 100% di shared hosting (cPanel).
 */
class SpreadsheetReaderService
{
    /**
     * Membaca file spreadsheet (.csv atau .xlsx) dan mengembalikan array data baris asosiatif.
     *
     * @param string $filePath Path file fisik di disk
     * @param string|null $originalFilename Nama asli file untuk menentukan ekstensi
     * @return array<int, array<string, string>>
     */
    public function read(string $filePath, ?string $originalFilename = null): array
    {
        $extension = strtolower(pathinfo($originalFilename ?? $filePath, PATHINFO_EXTENSION));

        $rawRows = match ($extension) {
            'xlsx' => $this->readXlsx($filePath),
            default => $this->readCsv($filePath),
        };

        if (empty($rawRows)) {
            return [];
        }

        // Ambil baris pertama sebagai header kolom
        $headerRow = array_shift($rawRows);
        $headerMap = $this->mapHeaders($headerRow);

        $parsedData = [];
        foreach ($rawRows as $row) {
            // Abaikan baris kosong
            if ($this->isRowEmpty($row)) {
                continue;
            }

            $entry = [
                'name' => '',
                'email' => '',
                'role' => '',
                'whatsapp' => '',
                'password' => '',
            ];

            foreach ($headerMap as $field => $colIndex) {
                if (isset($row[$colIndex])) {
                    $entry[$field] = trim((string) $row[$colIndex]);
                }
            }

            // Minimal harus ada nama atau email terisi
            if ($entry['name'] !== '' || $entry['email'] !== '') {
                $parsedData[] = $entry;
            }
        }

        return $parsedData;
    }

    /**
     * Membaca file CSV dengan deteksi otomatis pemisah (koma, titik koma, atau tab).
     */
    protected function readCsv(string $filePath): array
    {
        $content = @file_get_contents($filePath);
        if ($content === false) {
            // Coba buka dengan fopen jika file_get_contents gagal
            $handle = @fopen($filePath, 'r');
            if ($handle !== false) {
                $content = stream_get_contents($handle);
                fclose($handle);
            }
        }

        if (! is_string($content) || trim($content) === '') {
            return [];
        }

        // Hapus BOM UTF-8 jika ada (biasa dihasilkan oleh ekspor Excel)
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        // Deteksi pemisah (delimiter) terbaik
        $firstLine = strtok($content, "\r\n") ?: '';
        $delimiter = ',';
        $semicolonCount = substr_count($firstLine, ';');
        $commaCount = substr_count($firstLine, ',');
        $tabCount = substr_count($firstLine, "\t");

        if ($semicolonCount > $commaCount && $semicolonCount > $tabCount) {
            $delimiter = ';';
        } elseif ($tabCount > $commaCount && $tabCount > $semicolonCount) {
            $delimiter = "\t";
        }

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $rows = [];
        while (($data = fgetcsv($stream, 0, $delimiter)) !== false) {
            $rows[] = array_map(fn ($val) => trim((string) $val), $data);
        }
        fclose($stream);

        return $rows;
    }

    /**
     * Membaca file Excel .xlsx secara native menggunakan ZipArchive dan XML parser.
     */
    protected function readXlsx(string $filePath): array
    {
        if (! class_exists('ZipArchive')) {
            throw new RuntimeException('Ekstensi PHP ZipArchive diperlukan untuk membaca file .xlsx.');
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new RuntimeException('Format file .xlsx tidak valid atau rusak.');
        }

        // 1. Baca shared strings (tabel teks bersama)
        $sharedStrings = [];
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedStringsXml !== false) {
            $xml = @simplexml_load_string($sharedStringsXml);
            if ($xml instanceof SimpleXMLElement) {
                foreach ($xml->si as $si) {
                    // Dalam XLSX, teks bisa ada di <t> langsung atau di <r><t> (formatted text)
                    if (isset($si->t)) {
                        $sharedStrings[] = (string) $si->t;
                    } elseif (isset($si->r)) {
                        $parts = [];
                        foreach ($si->r as $r) {
                            $parts[] = (string) $r->t;
                        }
                        $sharedStrings[] = implode('', $parts);
                    } else {
                        $sharedStrings[] = '';
                    }
                }
            }
        }

        // 2. Baca sheet pertama
        $sheetXmlContent = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXmlContent === false) {
            // Coba temukan sheet apapun jika sheet1 tidak ada
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $filename)) {
                    $sheetXmlContent = $zip->getFromName($filename);
                    break;
                }
            }
        }
        $zip->close();

        if ($sheetXmlContent === false) {
            return [];
        }

        $sheetXml = @simplexml_load_string($sheetXmlContent);
        if (! $sheetXml instanceof SimpleXMLElement || ! isset($sheetXml->sheetData->row)) {
            return [];
        }

        $rows = [];
        foreach ($sheetXml->sheetData->row as $rowNode) {
            $rowValues = [];
            foreach ($rowNode->c as $cell) {
                $ref = (string) $cell['r'];
                $colIndex = $this->columnLetterToIndex($ref);
                $type = (string) $cell['t'];
                $val = '';

                if ($type === 's') {
                    // Referensi ke shared strings
                    $idx = (int) $cell->v;
                    $val = $sharedStrings[$idx] ?? '';
                } elseif ($type === 'inlineStr' && isset($cell->is->t)) {
                    $val = (string) $cell->is->t;
                } elseif (isset($cell->v)) {
                    $val = (string) $cell->v;
                }

                $rowValues[$colIndex] = trim($val);
            }

            // Normalisasi baris menjadi array berurutan tanpa celah kosong
            if (! empty($rowValues)) {
                $maxCol = max(array_keys($rowValues));
                $denseRow = [];
                for ($c = 0; $c <= $maxCol; $c++) {
                    $denseRow[$c] = $rowValues[$c] ?? '';
                }
                $rows[] = $denseRow;
            }
        }

        return $rows;
    }

    /**
     * Konversi koordinat kolom Excel (misal: "A1" -> 0, "B5" -> 1, "AA2" -> 26).
     */
    protected function columnLetterToIndex(string $cellRef): int
    {
        $letters = preg_replace('/[^A-Z]/i', '', strtoupper($cellRef));
        $len = strlen($letters);
        $index = 0;
        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }

        return max(0, $index - 1);
    }

    /**
     * Memetakan nama header ke field aplikasi secara fleksibel.
     */
    protected function mapHeaders(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $index => $header) {
            $clean = strtolower(trim((string) $header));
            $clean = preg_replace('/[^a-z0-9]/', '', $clean);

            if (in_array($clean, ['nama', 'namalengkap', 'name', 'fullname'], true) && ! isset($map['name'])) {
                $map['name'] = $index;
            } elseif (in_array($clean, ['email', 'surel', 'alamatemail', 'mail'], true) && ! isset($map['email'])) {
                $map['email'] = $index;
            } elseif (in_array($clean, ['role', 'peran', 'jabatan', 'level'], true) && ! isset($map['role'])) {
                $map['role'] = $index;
            } elseif (in_array($clean, ['whatsapp', 'wa', 'nowa', 'telepon', 'nohp', 'phone', 'hp'], true) && ! isset($map['whatsapp'])) {
                $map['whatsapp'] = $index;
            } elseif (in_array($clean, ['password', 'sandi', 'katasandi', 'pass'], true) && ! isset($map['password'])) {
                $map['password'] = $index;
            }
        }

        // Jika tidak ada header yang cocok, asumsikan urutan standar: Nama, Email, Peran, WhatsApp, Password
        if (empty($map)) {
            $fields = ['name', 'email', 'role', 'whatsapp', 'password'];
            foreach ($fields as $idx => $f) {
                if (isset($headerRow[$idx])) {
                    $map[$f] = $idx;
                }
            }
        }

        return $map;
    }

    /**
     * Mengecek apakah semua kolom dalam baris bernilai kosong.
     */
    protected function isRowEmpty(array $row): bool
    {
        foreach ($row as $val) {
            if (trim((string) $val) !== '') {
                return false;
            }
        }

        return true;
    }
}
