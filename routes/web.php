<?php

// =========================================================================
// FILE DEFINISI ROUTE WEB (APLIKASI UTAMA BERBASIS BROWSER / BLADE)
// =========================================================================
// File ini mengatur semua alamat URL (route) untuk antarmuka website RAPI.
// Jika ingin mengubah atau mengatur penambahan halaman baru atau mengubah
// URL halaman web, tambahkan atau ubah definisi Route di file ini.

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\PicComplaintReportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoomController;
use Illuminate\Support\Facades\Route;

// Halaman utama (Landing Page) menampilkan kalender agenda publik tanpa perlu login
Route::get('/', [CalendarController::class, 'index'])->name('calendar.public');

// Group route dengan middleware 'guest': Hanya bisa diakses oleh pengguna yang BELUM login.
// Jika sudah login, pengguna otomatis diarahkan ke halaman dashboard.
Route::middleware('guest')->group(function () {
    // Menampilkan halaman form login dan memproses autentikasi login
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');

    // Alur Lupa Password & Reset Password (menggunakan tautan atau kode OTP email)
    // Middleware 'throttle:6,1' membatasi maksimal 6 percobaan per 1 menit untuk mencegah spam/brute-force
    // Ubah angka pada 'throttle:6,1' Jika ingin mengubah batas percobaan kirim OTP/reset.
    Route::get('/lupa-password', [AuthController::class, 'requestPasswordReset'])->name('password.request');
    Route::get('/lupa-password/ganti-email', [AuthController::class, 'changePasswordResetEmail'])->name('password.change-email');
    Route::post('/lupa-password', [AuthController::class, 'sendPasswordResetLink'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'resetPasswordForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:6,1')->name('password.update');
    Route::post('/reset-password/otp', [AuthController::class, 'resetPasswordWithOtp'])->middleware('throttle:6,1')->name('password.otp');
});

// Group route dengan middleware 'auth': Wajib login terlebih dahulu untuk mengakses URL di dalamnya
// Berlaku untuk semua role yang sudah terautentikasi (Admin, PIC, Guru, Organisasi)
Route::middleware('auth')->group(function () {
    // Halaman Dashboard, Profil Pengguna, dan Kalender Interaktif
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profil', [ProfileController::class, 'show'])->name('profile');
    Route::get('/kalender', [CalendarController::class, 'index'])->name('calendar');

    // Fitur Agenda / Peminjaman Ruangan (Lihat daftar, form pengajuan, dan simpan pengajuan baru)
    Route::get('/agenda', [EventController::class, 'index'])->name('events.index');
    Route::get('/agenda/ajukan', [EventController::class, 'create'])->name('events.create');
    Route::post('/agenda', [EventController::class, 'store'])->name('events.store');

    // Fitur Pengaduan Sarana & Prasarana (Lihat daftar dan tampilkan foto bukti kerusakan)
    Route::get('/pengaduan', [ComplaintController::class, 'index'])->name('complaints.index');
    Route::get('/pengaduan/{complaint}/foto', [ComplaintController::class, 'showPhoto'])->name('complaints.show-photo');

    // Fitur Laporan Pengaduan khusus untuk Role PIC (Person In Charge)
    Route::get('/pic/laporan-pengaduan', [PicComplaintReportController::class, 'index'])->name('pic.complaint-reports.index');
    Route::get('/pic/laporan-pengaduan/{complaint}/foto', [PicComplaintReportController::class, 'showPhoto'])->name('pic.complaint-reports.photo');

    // Operasi CRUD Pengaduan (Form buat, simpan baru, ganti foto bukti, ubah status perbaikan, dan hapus)
    Route::get('/pengaduan/buat', [ComplaintController::class, 'create'])->name('complaints.create');
    Route::post('/pengaduan', [ComplaintController::class, 'store'])->name('complaints.store');
    Route::patch('/pengaduan/{complaint}/foto', [ComplaintController::class, 'updatePhoto'])->name('complaints.photo');
    Route::patch('/pengaduan/{complaint}/status', [ComplaintController::class, 'updateStatus'])->name('complaints.status');
    Route::delete('/pengaduan/{complaint}', [ComplaintController::class, 'destroy'])->name('complaints.destroy');

    // Aksi Persetujuan / Penolakan Agenda Peminjaman Ruangan oleh PIC atau Admin
    Route::patch('/agenda/{event}/keputusan', [EventController::class, 'decide'])->name('events.decide');

    // Proses keluar (Logout) dan menghapus sesi login pengguna
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
});

// Group route dengan middleware ['auth', 'admin']:
// Hanya dapat diakses oleh pengguna yang sudah login DAN memiliki role 'Admin' (dicek oleh EnsureUserIsAdmin)
// Jika ingin mengubah atau mengatur fitur tertentu dibuka untuk role selain Admin, pindahkan route-nya ke grup 'auth' di atas.
Route::middleware(['auth', 'admin'])->group(function () {
    // Halaman Cetak / Rekap Laporan Admin
    Route::get('/admin/laporan', [ReportController::class, 'index'])->name('admin.reports.index');

    // Manajemen Data Pengguna (CRUD User oleh Admin)
    Route::get('/admin/users', [AdminUserController::class, 'index'])->name('admin.users.index');
    Route::post('/admin/users', [AdminUserController::class, 'store'])->name('admin.users.store');
    Route::put('/admin/users/{user}', [AdminUserController::class, 'update'])->name('admin.users.update');
    Route::delete('/admin/users/{user}', [AdminUserController::class, 'destroy'])->name('admin.users.destroy');

    // Manajemen Data Ruangan (CRUD Ruangan oleh Admin)
    Route::get('/ruangan', [RoomController::class, 'index'])->name('rooms.index');
    Route::get('/ruangan/tambah', [RoomController::class, 'form'])->name('rooms.create');
    Route::post('/ruangan', [RoomController::class, 'store'])->name('rooms.store');
    Route::get('/ruangan/{room}/edit', [RoomController::class, 'form'])->name('rooms.edit');
    Route::put('/ruangan/{room}', [RoomController::class, 'update'])->name('rooms.update');
    Route::delete('/ruangan/{room}', [RoomController::class, 'destroy'])->name('rooms.destroy');

    // Manajemen Edit & Hapus Agenda secara langsung oleh Admin
    Route::get('/agenda/{event}/edit', [EventController::class, 'edit'])->name('events.edit');
    Route::put('/agenda/{event}', [EventController::class, 'update'])->name('events.update');
    Route::delete('/agenda/{event}', [EventController::class, 'destroy'])->name('events.destroy');
});

// Route bantuan untuk membuat symlink storage (akses foto laporan/pengaduan) di cPanel
Route::get('/init-storage', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('storage:link');
        return 'Storage link berhasil dibuat! Foto laporan dan pengaduan sekarang siap diakses.';
    } catch (\Exception $e) {
        return 'Info: ' . $e->getMessage();
    }
});

// Route bantuan untuk menjalankan migrasi database otomatis di hosting cPanel
Route::get('/init-migrate', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        return 'Migrasi database berhasil dijalankan di server hosting: <pre>' . \Illuminate\Support\Facades\Artisan::output() . '</pre>';
    } catch (\Exception $e) {
        return 'Info migrasi: ' . $e->getMessage();
    }
});
