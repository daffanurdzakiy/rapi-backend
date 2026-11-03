<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Notifications\PasswordResetOtp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

// =========================================================================
// API CONTROLLER: AUTH CONTROLLER (AUTENTIKASI, PROFIL, & RESET PASSWORD OTP)
// =========================================================================
// Menangani proses Login (penerbitan token Sanctum), lihat/ubah profil user,
// Logout (pencabutan token), serta Lupa Password menggunakan kode OTP 6 angka via email.
class AuthController extends ApiController
{
    /**
     * Authenticate user and return Sanctum Bearer token.
     */
    // Endpoint: POST /api/login
    // Memvalidasi email & password, mengecek role yang diizinkan, lalu membuat Sanctum Bearer Token
    public function login(Request $request): JsonResponse
    {
        // Validasi input dari request body JSON
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        // Jika input tidak lengkap / format email salah, kembalikan HTTP 422 Unprocessable Entity
        if ($validator->fails()) {
            return $this->sendError('Validasi gagal.', $validator->errors(), 422);
        }

        // Cari user berdasarkan email sekaligus memuat relasi `role`
        $user = User::with('role')->where('email', $request->email)->first();

        // Cek apakah user ditemukan dan apakah password input cocok dengan hash password di database
        if (! $user || ! Hash::check($request->password, $user->password)) {
            return $this->sendError('Email atau password tidak sesuai.', [
                'email' => ['Email atau password tidak valid.'],
            ], 401);
        }

        // Periksa apakah role user termasuk dalam daftar role yang diizinkan mengakses sistem
        // Jika ingin mengubah atau mengatur membatasi login mobile hanya untuk role tertentu (misal tanpa Admin),
        // ubah daftar array `$allowedRoles` di bawah ini.
        $roleName = $user->role?->role_name;
        $allowedRoles = ['Admin', 'Guru', 'Organisasi', 'PIC'];
        if (! in_array($roleName, $allowedRoles, true)) {
            return $this->sendError('Akun Anda tidak memiliki akses ke sistem.', null, 403);
        }

        // Buat token Sanctum baru atas nama perangkat (default: 'mobile_app')
        $deviceName = $request->input('device_name', 'mobile_app');
        $token = $user->createToken($deviceName)->plainTextToken;

        // Kembalikan token beserta data user yang telah diformat dengan UserResource
        return $this->sendResponse([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ], 'Login berhasil.');
    }

    /**
     * Get profile of the current authenticated user.
     */
    // Endpoint: GET /api/me
    // Mengambil data profil user yang sedang login berdasarkan Bearer Token yang dikirim
    public function me(Request $request): JsonResponse
    {
        // Muat relasi `role` dan daftar ruangan yang ditugaskan (`assignedRooms`)
        $user = $request->user()->load(['role', 'assignedRooms']);

        return $this->sendResponse(
            new UserResource($user),
            'Profil pengguna berhasil dimuat.',
        );
    }

    /**
     * Update current authenticated user profile.
     */
    // Endpoint: PUT /api/me
    // Memperbarui nama, nomor WhatsApp, dan password (opsional) milik user yang sedang login
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        // Validasi input pembaruan profil
        // Aturan `'confirmed'` pada password mengharuskan kiriman field `password_confirmation` yang nilainya sama.
        // Ubah `'min:8'` Jika ingin mengubah atau mengatur batas panjang minimal password yang berbeda.
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validasi gagal.', $validator->errors(), 422);
        }

        $data = $validator->validated();
        $user->name = $data['name'];
        $user->whatsapp = $data['whatsapp'] ?? null;

        // Jika user juga mengisi field password baru, enkripsi (hash) password tersebut sebelum disimpan
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        return $this->sendResponse(
            new UserResource($user->load(['role', 'assignedRooms'])),
            'Profil berhasil diperbarui.',
        );
    }

    /**
     * Logout and revoke the active token.
     */
    // Endpoint: POST /api/logout
    // Menghapus token Sanctum yang sedang dipakai saat ini sehingga tidak bisa digunakan lagi
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->sendResponse(null, 'Berhasil logout.');
    }

    /**
     * Send password reset OTP code to the given email.
     */
    // Endpoint: POST /api/forgot-password
    // Menghasilkan kode OTP 6 angka acak, mengirimkannya ke email user, dan menyimpan hash OTP ke tabel `password_reset_tokens`
    public function forgotPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validasi gagal.', $validator->errors(), 422);
        }

        // Cek apakah email terdaftar di tabel `users`
        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return $this->sendError('Email tidak terdaftar di database.', [
                'email' => ['Email tidak ditemukan.'],
            ], 404);
        }

        // Bangkitkan angka acak 6 digit antara 100000 sampai 999999 sebagai kode OTP
        // Jika ingin mengubah jumlah digit OTP (misal 4 digit), ubah rentang angka di sini dan aturan 'digits:6' di verifyOtp/resetPassword.
        $otp = (string) random_int(100000, 999999);

        // Kirim email OTP menggunakan kelas notifikasi PasswordResetOtp
        try {
            $user->notify(new PasswordResetOtp($otp));
        } catch (TransportExceptionInterface $exception) {
            Log::error('API: Password reset OTP email failed to send', [
                'exception' => $exception::class,
            ]);

            return $this->sendError('Gagal mengirim email OTP. Silakan periksa konfigurasi server email atau coba lagi nanti.', null, 500);
        } catch (\Throwable $e) {
            Log::warning('API: Password reset OTP fallback', ['message' => $e->getMessage()]);
        }

        // Simpan atau perbarui token OTP (dalam bentuk di-hash) beserta waktu pembuatannya (`created_at`) di tabel `password_reset_tokens`
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($otp), 'created_at' => now()],
        );

        return $this->sendResponse([
            'email' => $user->email,
        ], 'Kode OTP reset password berhasil dikirim ke email Anda.');
    }

    /**
     * Verify OTP code before proceeding to new password screen.
     */
    // Endpoint: POST /api/verify-otp
    // Memeriksa apakah kode OTP 6 digit yang dimasukkan user cocok dan belum kedaluwarsa (maksimal 10 menit)
    public function verifyOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'otp' => ['required', 'digits:6'],
        ], [
            'otp.required' => 'Masukkan kode OTP yang dikirim ke email.',
            'otp.digits' => 'Kode OTP harus terdiri dari 6 angka.',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validasi gagal.', $validator->errors(), 422);
        }

        $data = $validator->validated();

        // Ambil data token reset password berdasarkan email
        $tokenRecord = DB::table('password_reset_tokens')
            ->where('email', $data['email'])
            ->first();

        // Validasi: pastikan record ada, belum lewat dari 10 menit sejak dibuat, dan kode OTP cocok dengan hash di database
        // Ubah `addMinutes(10)` Jika ingin mengubah atau mengatur masa berlaku OTP diubah (misal 5 menit -> `addMinutes(5)`).
        if (
            ! $tokenRecord
            || ! $tokenRecord->created_at
            || now()->greaterThan(Carbon::parse($tokenRecord->created_at)->addMinutes(10))
            || ! Hash::check($data['otp'], $tokenRecord->token)
        ) {
            return $this->sendError('Kode OTP tidak valid atau sudah kedaluwarsa. Silakan minta kode baru.', [
                'otp' => ['Kode OTP tidak valid atau sudah melewati batas waktu 10 menit.'],
            ], 422);
        }

        // Perbarui `created_at` agar user punya waktu 10 menit penuh saat mengisi form password baru di layar berikutnya
        DB::table('password_reset_tokens')
            ->where('email', $data['email'])
            ->update(['created_at' => now()]);

        return $this->sendResponse([
            'email' => $data['email'],
            'verified' => true,
        ], 'Kode OTP berhasil diverifikasi. Silakan buat password baru.');
    }

    /**
     * Reset password using OTP code.
     */
    // Endpoint: POST /api/reset-password
    // Memvalidasi ulang OTP dan menyimpan password baru user ke database
    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'otp' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'otp.required' => 'Masukkan kode OTP yang dikirim ke email.',
            'otp.digits' => 'Kode OTP harus terdiri dari 6 angka.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'password.min' => 'Password minimal 8 karakter.',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validasi gagal.', $validator->errors(), 422);
        }

        $data = $validator->validated();

        // Ambil kembali record token dari tabel `password_reset_tokens`
        $tokenRecord = DB::table('password_reset_tokens')
            ->where('email', $data['email'])
            ->first();

        // Pastikan OTP masih valid dan belum lewat 10 menit
        if (
            ! $tokenRecord
            || ! $tokenRecord->created_at
            || now()->greaterThan(Carbon::parse($tokenRecord->created_at)->addMinutes(10))
            || ! Hash::check($data['otp'], $tokenRecord->token)
        ) {
            return $this->sendError('Kode OTP tidak valid atau sudah kedaluwarsa.', [
                'otp' => ['Kode OTP tidak valid atau sudah melewati batas waktu 10 menit.'],
            ], 422);
        }

        $user = User::where('email', $data['email'])->first();
        if (! $user) {
            return $this->sendError('Pengguna tidak ditemukan.', null, 404);
        }

        // Simpan password baru yang sudah di-hash ke tabel `users`
        $user->password = Hash::make($data['password']);
        $user->save();

        // Hapus token OTP dari database agar kode OTP hanya bisa dipakai satu kali (One-Time Password)
        DB::table('password_reset_tokens')->where('email', $data['email'])->delete();

        return $this->sendResponse(null, 'Password berhasil diubah. Silakan masuk menggunakan password baru.');
    }
}

