<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

// =========================================================================
// BASE API CONTROLLER: PEMBUNGKUS STANDAR RESPONSE JSON
// =========================================================================
// Semua Controller di dalam folder `Api` mewarisi (extends) kelas `ApiController` ini.
// Tujuannya agar seluruh endpoint API memiliki struktur JSON yang seragam (`success`, `message`, `data` / `errors`).
// Jika ingin mengubah atau mengatur perubahan struktur dasar JSON (misal menambah field 'status_code' atau 'timestamp'),
// cukup ubah di kedua method `sendResponse()` dan `sendError()` pada file ini.
class ApiController extends Controller
{
    /**
     * Send standard successful API response.
     */
    // Mengirimkan response JSON sukses (default HTTP 200 OK)
    protected function sendResponse(mixed $data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Send standard error API response.
     */
    // Mengirimkan response JSON gagal/error (default HTTP 400 Bad Request, atau 401/403/404/422/500 sesuai kondisi)
    protected function sendError(string $message, mixed $errors = null, int $code = 400): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        // Jika terdapat detail pesan error validasi (misal dari Validator), sertakan ke dalam key 'errors'
        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }
}

