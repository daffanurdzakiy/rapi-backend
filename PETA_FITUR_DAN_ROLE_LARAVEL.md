# Peta Struktur Folder, Fitur, dan Hak Akses Role — RAPI-Sekolah (Laravel)

Dokumen ini adalah panduan cepat untuk memahami letak setiap fitur, struktur folder, dan pembagian hak akses *role* pada aplikasi **RAPI-Sekolah** (`c:/laragon/www/rapi/rapi`).

---

## 1. Struktur Folder Utama Laravel (`rapi/`)

```text
rapi/
├── app/
│   ├── Http/
│   │   ├── Controllers/                # Logika Fitur Web (Blade)
│   │   │   ├── AdminUserController.php         # [Admin] CRUD User & Import CSV/Excel
│   │   │   ├── AuthController.php              # [Publik/Semua] Login, Logout, Lupa Password & OTP
│   │   │   ├── CalendarController.php          # [Publik/Semua] Kalender Jadwal & Hari Libur Nasional
│   │   │   ├── ComplaintController.php         # [Semua Role] Pengaduan Fasilitas & Bukti Perbaikan
│   │   │   ├── DashboardController.php         # [Semua Role] Ringkasan Statistik & Notifikasi
│   │   │   ├── EventController.php             # [Guru/Org/PIC/Admin] Peminjaman Ruangan & Verifikasi
│   │   │   ├── PicComplaintReportController.php# [PIC & Admin] Rekap & Cetak Laporan Pengaduan
│   │   │   ├── ProfileController.php           # [Semua Role] Halaman Profil Akun
│   │   │   ├── ReportController.php            # [Admin] Rekap & Cetak Laporan Agenda
│   │   │   ├── RoomController.php              # [Admin] Manajemen Data Ruangan & Penugasan PIC
│   │   │   └── Api/                            # Logika REST API untuk Aplikasi Mobile (Flutter)
│   │   │       ├── ApiController.php
│   │   │       ├── AuthController.php
│   │   │       ├── CalendarController.php
│   │   │       ├── ComplaintController.php
│   │   │       ├── DashboardController.php
│   │   │       ├── EventController.php
│   │   │       ├── NotificationController.php
│   │   │       ├── RoomController.php
│   │   │       └── SessionController.php
│   │   └── Middleware/
│   │       └── EnsureUserIsAdmin.php   # Proteksi route khusus role Admin
│   ├── Mail/
│   │   └── PasswordResetOtpMail.php    # Template email pengiriman kode OTP Lupa Password
│   ├── Models/                         # Representasi Tabel Database (Eloquent ORM)
│   │   ├── Complaint.php               # Tabel complaints (Pengaduan Fasilitas)
│   │   ├── Event.php                   # Tabel events (Agenda Peminjaman Ruangan)
│   │   ├── Notification.php            # Tabel notifications (Notifikasi Sistem)
│   │   ├── Role.php                    # Tabel roles (Admin, PIC, Guru, Organisasi, Siswa)
│   │   ├── Room.php                    # Tabel rooms (Data Ruangan & relasi PIC)
│   │   └── User.php                    # Tabel users (Data Akun Pengguna)
│   └── Services/                       # Layanan Pendukung (Helper/Business Logic)
│       ├── ImageCompressionService.php # Kompresi & resize foto bukti kerusakan/perbaikan otomatis
│       └── NationalHolidayService.php  # Penarikan & cache daftar Hari Libur Nasional Indonesia
│
├── config/
│   └── school.php                      # Konfigurasi jam sesi pelajaran sekolah (Jam Ke-1 s/d Ke-11)
│
├── resources/views/                    # Seluruh Tampilan Antarmuka Web (100% Terorganisir per Fitur)
│   ├── admin/                          # Tampilan Khusus Admin
│   │   ├── reports/index.blade.php     # Rekap & Cetak Laporan Agenda
│   │   └── users.blade.php             # Manajemen Pengguna & Import Data
│   ├── auth/                           # Tampilan Autentikasi (Belum Login)
│   │   ├── login.blade.php             # Form Login
│   │   ├── forgot-password.blade.php   # Form Permintaan OTP / Link Reset Password
│   │   └── reset-password.blade.php    # Form Input OTP & Password Baru
│   ├── calendar/                       # Tampilan Kalender Ruangan
│   │   ├── index.blade.php             # Halaman Kalender Internal (Setelah Login)
│   │   ├── public.blade.php            # Halaman Kalender Publik (Tanpa Login / Landing Page)
│   │   ├── month.blade.php             # Komponen Grid Kalender Bulanan
│   │   ├── dialogs.blade.php           # Modal Detail Agenda saat Tanggal Diklik
│   │   └── styles.blade.php            # CSS Khusus Kalender
│   ├── complaints/                     # Tampilan Pengaduan Sarana & Prasarana
│   │   ├── index.blade.php             # Daftar Pengaduan, Filter, & Modal Update Status/Bukti
│   │   └── form.blade.php              # Form Buat Laporan Kerusakan Fasilitas Baru
│   ├── dashboard/                      # Tampilan Dashboard Utama
│   │   └── index.blade.php             # Ringkasan Statistik, Jadwal Hari Ini, & Agenda/Pengaduan Terkini
│   ├── events/                         # Tampilan Agenda Peminjaman Ruangan
│   │   ├── index.blade.php             # Daftar Agenda, Filter, & Tombol Setujui/Tolak (PIC & Admin)
│   │   ├── form.blade.php              # Form Pengajuan Peminjaman (1 Hari, Rentang, Multi-Ruang)
│   │   └── edit.blade.php              # Form Edit & Pindah Ruangan Agenda (Khusus Admin)
│   ├── layouts/                        # Kerangka Utama Halaman (Master Layout)
│   │   ├── app.blade.php               # Layout Utama (Sidebar Role-Aware, Header, Lonceng Notifikasi)
│   │   └── auth.blade.php              # Layout Halaman Login / Reset Password
│   ├── pic/                            # Tampilan Khusus PIC
│   │   └── complaint-reports/
│   │       └── index.blade.php         # Rekap & Cetak Laporan Pengaduan Ruangan PIC
│   ├── profile/                        # Tampilan Profil Akun
│   │   └── index.blade.php             # Detail Nama, Email, Role, dan Nomor WhatsApp
│   └── rooms/                          # Tampilan Manajemen Ruangan (Khusus Admin)
│       ├── index.blade.php             # Daftar Ruangan, Kapasitas, & Nama PIC
│       └── form.blade.php              # Form Tambah / Edit Ruangan
│
├── routes/
│   ├── web.php                         # Definisi URL Website (Browser)
│   └── api.php                         # Definisi URL API untuk Aplikasi Mobile Flutter
└── tests/Feature/                      # 100 Pengujian Otomatis (Automated Feature Tests)
```

---

## 2. Peta Hak Akses Berdasarkan Role

Sistem memiliki **5 Role Terdaftar** (`Admin`, `PIC`, `Guru`, `Organisasi`, `Siswa`) ditambah **Tamu/Publik (Tanpa Login)**:

| Role | Hak Akses Utama di Sistem | File Terkait yang Paling Sering Digunakan |
| :--- | :--- | :--- |
| **Tamu (Publik)** | Melihat Kalender Agenda yang sudah `Disetujui` beserta Hari Libur Nasional di halaman depan (`/`). | `CalendarController@index`<br>`resources/views/calendar/public.blade.php` |
| **Siswa** | Login, melihat Kalender, membuat **Laporan Pengaduan Kerusakan Fasilitas** beserta foto bukti, dan memantau progres perbaikannya. | `ComplaintController@index`, `create`, `store`, `updatePhoto`<br>`resources/views/complaints/index.blade.php`<br>`resources/views/complaints/form.blade.php` |
| **Guru & Organisasi** | Semua akses Siswa **+** mengajukan **Peminjaman Ruangan (Agenda)** minimal H-1 pada jam sesi sekolah (sebelum 17:00), cek bentrok otomatis, dan menerima notifikasi persetujuan/penolakan. | `EventController@index`, `create`, `store`, `checkAvailability`<br>`resources/views/events/index.blade.php`<br>`resources/views/events/form.blade.php` |
| **PIC (Penanggung Jawab Ruangan)** | Memverifikasi (**Setujui / Tolak**) pengajuan agenda di ruangan tanggung jawabnya, memperbarui status perbaikan pengaduan (`Belum Diperbaiki` $\rightarrow$ `Diproses` $\rightarrow$ `Selesai` + foto perbaikan), serta mencetak **Laporan Pengaduan PIC**. | `EventController@decide`<br>`ComplaintController@updateStatus`<br>`PicComplaintReportController@index`<br>`resources/views/pic/complaint-reports/index.blade.php` |
| **Admin** | Akses penuh ke seluruh fitur: pengajuan agenda langsung `Disetujui` (bisa hari H & di atas jam 17:00), edit/pindah ruangan agenda, verifikasi semua agenda & pengaduan, **Kelola Ruangan**, **Kelola Pengguna (+ Import CSV/Excel)**, dan **Cetak Laporan Agenda & Pengaduan**. | `AdminUserController`, `RoomController`, `ReportController`, `EventController@edit/update/destroy`<br>`resources/views/admin/*`, `resources/views/rooms/*`, `resources/views/events/edit.blade.php` |

---

## 3. Peta Detail Fitur & Method Controller

### A. Autentikasi & Lupa Password (`AuthController.php`)
- **Route**: `/login`, `/logout`, `/lupa-password`, `/reset-password` (`routes/web.php` baris 27–41 & 77)
- **Controller**: `app/Http/Controllers/AuthController.php`
  - `create()` & `store()`: Menampilkan form login dan memvalidasi kredensial.
  - `requestPasswordReset()` & `sendPasswordResetLink()`: Membuat token & kode OTP 6 digit lalu mengirim email melalui `PasswordResetOtpMail`.
  - `resetPasswordWithOtp()` & `resetPassword()`: Memvalidasi kode OTP / token dan menyimpan password baru.
- **View**: `resources/views/auth/login.blade.php`, `forgot-password.blade.php`, `reset-password.blade.php`.

### B. Kalender Jadwal & Hari Libur (`CalendarController.php`)
- **Route**: `/` (`calendar.public`) dan `/kalender` (`calendar`)
- **Controller**: `app/Http/Controllers/CalendarController.php`
  - `index()`: Mengambil agenda berstatus `Disetujui` pada bulan terpilih dan menggabungkannya dengan data hari libur nasional dari `NationalHolidayService`.
- **View**: `resources/views/calendar/public.blade.php` (tamu) dan `resources/views/calendar/index.blade.php` (sudah login).

### C. Dashboard Role-Aware (`DashboardController.php`)
- **Route**: `/dashboard` (`dashboard`)
- **Controller**: `app/Http/Controllers/DashboardController.php`
  - `index()`: Menghitung jumlah ruangan, agenda hari ini, pengaduan belum diperbaiki, 5 notifikasi terbaru, 5 agenda terdekat, 5 pengaduan terbaru, dan jadwal ruang unggulan.
- **View**: `resources/views/dashboard/index.blade.php`.

### D. Agenda Peminjaman Ruangan (`EventController.php`)
- **Route**: `/agenda`, `/agenda/ajukan`, `/agenda/cek-ketersediaan`, `/agenda/{event}/keputusan`, `/agenda/{event}/edit`
- **Controller**: `app/Http/Controllers/EventController.php`
  - `index()`: Daftar agenda dengan filter status (`Pending`, `Disetujui`, `Ditolak`), ruangan, dan pencarian.
  - `create()` & `store()`: Form dan proses simpan pengajuan agenda (mendukung 1 hari, rentang tanggal, tanggal pilih manual, dan multi-ruangan sekaligus) beserta deteksi anti-bentrok (`findConflict`).
  - `checkAvailability()`: Endpoint JSON untuk mengecek bentrok jadwal secara *real-time* di form.
  - `decide()`: Aksi PIC atau Admin menyetujui (`Disetujui`) atau menolak (`Ditolak` wajib alasan) pengajuan agenda serta mengirim notifikasi ke pemohon.
  - `edit()`, `update()`, `destroy()`: Khusus Admin untuk mengubah waktu/memindahkan ruangan agenda (wajib alasan pindah ruangan) atau membatalkan agenda.
- **View**: `resources/views/events/index.blade.php`, `form.blade.php`, `edit.blade.php`.

### E. Pengaduan Sarana & Prasarana (`ComplaintController.php`)
- **Route**: `/pengaduan`, `/pengaduan/buat`, `/pengaduan/{complaint}/status`, `/pengaduan/{complaint}/foto`
- **Controller**: `app/Http/Controllers/ComplaintController.php`
  - `index()`: Daftar laporan pengaduan dengan filter ruangan dan status perbaikan.
  - `create()` & `store()`: Menyimpan pengaduan baru beserta kompresi foto bukti kerusakan via `ImageCompressionService`.
  - `updatePhoto()`: Mengganti foto bukti kerusakan oleh pelapor/Admin selama status masih `Belum Diperbaiki`.
  - `updateStatus()`: Mengubah status (`Belum Diperbaiki`, `Diproses`, `Selesai`) oleh PIC/Admin, mengunggah foto bukti perbaikan (`photo_after`), dan mengirim notifikasi ke pelapor.
- **View**: `resources/views/complaints/index.blade.php`, `form.blade.php`.

### F. Laporan PIC & Laporan Admin (`PicComplaintReportController.php` & `ReportController.php`)
- **Laporan Pengaduan (PIC & Admin)**:
  - Route: `/pic/laporan-pengaduan`
  - Controller: `app/Http/Controllers/PicComplaintReportController.php` (`index()`, `showPhoto()`)
  - View: `resources/views/pic/complaint-reports/index.blade.php`
- **Laporan Agenda (Khusus Admin)**:
  - Route: `/admin/laporan`
  - Controller: `app/Http/Controllers/ReportController.php` (`index()`)
  - View: `resources/views/admin/reports/index.blade.php`

### G. Manajemen Ruangan & Pengguna (`RoomController.php` & `AdminUserController.php`)
- **Manajemen Ruangan (Khusus Admin)**:
  - Route: `/ruangan`, `/ruangan/tambah`, `/ruangan/{room}/edit`
  - Controller: `app/Http/Controllers/RoomController.php`
  - View: `resources/views/rooms/index.blade.php`, `form.blade.php`
- **Manajemen Pengguna & Import Akun (Khusus Admin)**:
  - Route: `/admin/users`, `/admin/users/import`, `/admin/users/template-import`
  - Controller: `app/Http/Controllers/AdminUserController.php`
  - View: `resources/views/admin/users.blade.php`

