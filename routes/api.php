<?php

// =========================================================================
// FILE DEFINISI ROUTE REST API (UNTUK APLIKASI MOBILE FLUTTER / CLIENT)
// =========================================================================
// Semua route di dalam file ini otomatis memiliki awalan (prefix) '/api'.
// Contoh: Route::post('/login', ...) akan diakses melalui URL '/api/login'.
// Jika ingin mengubah atau mengatur endpoint API baru, daftarkan di sini.

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\ComplaintController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\SessionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API Routes (No Authentication Required)
|--------------------------------------------------------------------------
| - Tampilan Kalender Sebelum Login
| - Detail Agenda Kalender
| - Autentikasi (Login & Lupa Password / Reset Password via OTP)
| - Informasi Ruangan & Sesi Waktu
| - Cek Ketersediaan Ruangan
*/
// Endpoint kalender publik: mengambil daftar agenda yang sudah disetujui & detailnya
Route::prefix('calendar')->group(function () {
    Route::get('/', [CalendarController::class, 'index'])->name('api.calendar.index');
    Route::get('/events/{event}', [CalendarController::class, 'show'])->name('api.calendar.show');
});

// Endpoint autentikasi publik: Login serta fitur Lupa Password dengan verifikasi kode OTP
Route::post('/login', [AuthController::class, 'login'])->name('api.login');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('api.password.forgot');
Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])->name('api.password.verify-otp');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('api.password.reset');

// Endpoint data master ruangan, sesi waktu reguler, dan pengecekan bentrok jadwal ruangan
Route::get('/rooms', [RoomController::class, 'index'])->name('api.rooms.index');
Route::get('/rooms/{room}', [RoomController::class, 'show'])->name('api.rooms.show');
Route::get('/sessions', [SessionController::class, 'index'])->name('api.sessions.index');
Route::get('/events/check-availability', [EventController::class, 'checkAvailability'])->name('api.events.check-availability');

/*
|--------------------------------------------------------------------------
| Authenticated API Routes (Sanctum Token Required)
|--------------------------------------------------------------------------
| Digunakan oleh Mobile Flutter untuk Role PIC, Guru, Organisasi (& Admin)
*/
// Middleware 'auth:sanctum' mewajibkan pengiriman header "Authorization: Bearer <token>" pada setiap request
Route::middleware('auth:sanctum')->group(function () {
    // Profil & Auth
    // Mengambil data user yang sedang login, memperbarui profil/password, dan mencabut token (logout)
    Route::get('/me', [AuthController::class, 'me'])->name('api.me');
    Route::put('/me', [AuthController::class, 'updateProfile'])->name('api.profile.update');
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');

    // Dashboard Ringkasan Sesuai Role
    // Mengembalikan jumlah statistik dan data terbaru yang disesuaikan dengan role user (PIC / Guru / Organisasi / Admin)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('api.dashboard');

    // Agenda / Peminjaman Ruangan
    // Mengambil daftar agenda, membuat pengajuan baru, melihat detail, dan menyetujui/menolak agenda (khusus PIC/Admin)
    Route::get('/events', [EventController::class, 'index'])->name('api.events.index');
    Route::post('/events', [EventController::class, 'store'])->name('api.events.store');
    Route::get('/events/{event}', [EventController::class, 'show'])->name('api.events.show');
    Route::patch('/events/{event}/decide', [EventController::class, 'decide'])->name('api.events.decide');
    Route::post('/events/{event}/cancellation-request', [EventController::class, 'requestCancellation'])->name('api.events.cancellation.request');
    Route::patch('/events/{event}/cancellation-decision', [EventController::class, 'decideCancellation'])->name('api.events.cancellation.decide');

    // Pengaduan Sarana Prasarana
    // Mengelola data pengaduan kerusakan fasilitas ruangan beserta unggah/tampil foto bukti kerusakan
    Route::get('/complaints', [ComplaintController::class, 'index'])->name('api.complaints.index');
    Route::post('/complaints', [ComplaintController::class, 'store'])->name('api.complaints.store');
    Route::get('/complaints/{complaint}', [ComplaintController::class, 'show'])->name('api.complaints.show');
    Route::match(['patch', 'post'], '/complaints/{complaint}/status', [ComplaintController::class, 'updateStatus'])->name('api.complaints.status');
    Route::post('/complaints/{complaint}/photo', [ComplaintController::class, 'updatePhoto'])->name('api.complaints.photo');
    Route::delete('/complaints/{complaint}', [ComplaintController::class, 'destroy'])->name('api.complaints.destroy');
    Route::get('/complaints/{complaint}/photo', [ComplaintController::class, 'photo'])->name('api.complaints.photo.stream');
    Route::get('/complaints/{complaint}/repair-photo', [ComplaintController::class, 'repairPhoto'])->name('api.complaints.repair-photo.stream');

    // Laporan Pengaduan Khusus PIC
    // Rekap laporan pengaduan berdasarkan periode (harian, mingguan, bulanan) untuk ruangan yang dipegang PIC
    Route::get('/pic/complaint-reports', [ComplaintController::class, 'picReports'])->name('api.pic.complaint-reports');

    // Notifikasi
    // Mengambil daftar notifikasi user, menandai satu notifikasi telah dibaca, atau menandai semua sekaligus
    Route::get('/notifications', [NotificationController::class, 'index'])->name('api.notifications.index');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('api.notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('api.notifications.read-all');
});
