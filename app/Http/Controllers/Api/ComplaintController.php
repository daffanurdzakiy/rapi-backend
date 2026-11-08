<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\ComplaintResource;
use App\Models\Complaint;
use App\Models\Notification;
use App\Models\Room;
use App\Services\ImageCompressionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

// =========================================================================
// API CONTROLLER: COMPLAINT CONTROLLER (PENGADUAN SARANA & PRASARANA)
// =========================================================================
// Mengelola laporan kerusakan fasilitas ruangan mulai dari daftar pengaduan,
// pembuatan laporan beserta kompresi foto bukti, pembaruan status perbaikan oleh PIC,
// penggantian foto, penghapusan laporan, hingga rekap laporan khusus PIC.
class ComplaintController extends ApiController
{
    /**
     * List complaints based on user's role and filters.
     */
    // Endpoint: GET /api/complaints
    // Menampilkan daftar pengaduan sesuai hak akses role:
    // - PIC: Hanya melihat pengaduan pada ruangan yang ditugaskan kepadanya (`pic_id = user->id`)
    // - Guru & Organisasi: Hanya melihat pengaduan yang mereka laporkan sendiri (`reporter_id = user->id`)
    // - Admin: Melihat seluruh pengaduan dari semua ruangan
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user->role?->role_name;

        $query = Complaint::with(['room.pic', 'reporter']);

        // Filter otomatis berdasarkan Role pengguna yang sedang login
        if ($role === 'PIC') {
            $query->whereHas('room', fn ($roomQuery) => $roomQuery->where('pic_id', $user->id));
        } elseif (in_array($role, ['Guru', 'Organisasi'], true)) {
            $query->where('reporter_id', $user->id);
        }

        $repairedCutoff = now()->subHours(24);

        // Filter opsional berdasarkan status perbaikan (?status_repair=Belum Diperbaiki)
        if ($request->filled('status_repair')) {
            $statusRepair = $request->input('status_repair');
            $query->where('status_repair', $statusRepair);
            if ($statusRepair === 'Sudah Diperbaiki' && ! $request->boolean('include_archived')) {
                $query->where('updated_at', '>=', $repairedCutoff);
            }
        } elseif (! $request->boolean('include_archived')) {
            // Default: tidak menampilkan pengaduan yang sudah diperbaiki lebih dari 24 jam lalu
            $query->where(function ($q) use ($repairedCutoff) {
                $q->where('status_repair', '!=', 'Sudah Diperbaiki')
                  ->orWhere('updated_at', '>=', $repairedCutoff);
            });
        }

        // Filter opsional berdasarkan ID ruangan (?room_id=1)
        if ($request->filled('room_id')) {
            $query->where('room_id', $request->input('room_id'));
        }

        // Urutkan dari yang terbaru dan bagi per halaman (pagination default 20 item per halaman)
        $complaints = $query->latest()->paginate($request->input('per_page', 20));

        return $this->sendResponse([
            'items' => ComplaintResource::collection($complaints->items()),
            'pagination' => [
                'current_page' => $complaints->currentPage(),
                'last_page' => $complaints->lastPage(),
                'per_page' => $complaints->perPage(),
                'total' => $complaints->total(),
            ],
        ], 'Daftar pengaduan sarana/prasarana berhasil dimuat.');
    }

    /**
     * Submit a facility complaint (Guru, Organisasi, Admin).
     */
    // Endpoint: POST /api/complaints (Multipart Form-Data karena mengunggah file `photo`)
    // Membuat laporan kerusakan baru dan otomatis mengirim notifikasi ke PIC ruangan terkait
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user->role?->role_name;

        // Aturan Bisnis: Role PIC tidak boleh membuat laporan pengaduan (hanya bertugas menindaklanjuti perbaikan)
        // Jika ingin agar PIC juga boleh membuat pengaduan, tambahkan 'PIC' ke array di bawah.
        if (! in_array($role, ['Admin', 'Guru', 'Organisasi'], true)) {
            return $this->sendError('PIC tidak dapat membuat laporan pengaduan ruangan.', null, 403);
        }

        // Validasi input laporan pengaduan beserta file gambar bukti kerusakan
        // `'max:5120'` berarti ukuran foto maksimal 5120 KB (5 MB). Ubah angka ini Jika ingin mengubah batas ukuran file.
        $validator = Validator::make($request->all(), [
            'room_id' => ['required', 'exists:rooms,id'],
            'description' => ['required', 'string', 'max:2000'],
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'photo.required' => 'Foto bukti kerusakan wajib diunggah.',
            'photo.max' => 'Ukuran foto maksimal 5 MB.',
            'photo.image' => 'File yang diunggah harus berupa gambar.',
            'photo.mimes' => 'Format foto harus JPG, PNG, atau WebP.',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validasi gagal.', $validator->errors(), 422);
        }

        $data = $validator->validated();

        // Kompres dan simpan foto ke disk `public` (folder `storage/app/public/complaints`)
        try {
            $data['photo_path'] = $this->storeCompressedPhoto($data['photo']);
            if (\Illuminate\Support\Facades\Schema::hasColumn('complaints', 'photo_data')) {
                $data['photo_data'] = app(ImageCompressionService::class)->encodePhotoFromDisk($data['photo_path']);
            }
        } catch (\Throwable $e) {
            return $this->sendError('Gagal memproses foto bukti: '.$e->getMessage(), null, 500);
        }

        unset($data['photo']);

        // Simpan pengaduan ke database dengan status awal 'Belum Diperbaiki'
        $complaint = Complaint::create($data + [
            'reporter_id' => $user->id,
            'status_repair' => 'Belum Diperbaiki',
        ]);

        // Cari ruangan yang dilaporkan; jika ruangan memiliki PIC, kirimkan notifikasi otomatis ke PIC tersebut
        $room = Room::with('pic')->find($complaint->room_id);

        if ($room && $room->pic_id) {
            Notification::create([
                'complaint_id' => $complaint->id,
                'user_id' => $room->pic_id,
                'message' => "Pengaduan baru di {$room->room_name} dari {$user->name}: {$complaint->description}",
            ]);
        }

        return $this->sendResponse(
            new ComplaintResource($complaint->load(['room.pic', 'reporter'])),
            'Laporan pengaduan berhasil dikirim.',
            201,
        );
    }

    /**
     * Get detail of a single complaint.
     */
    // Endpoint: GET /api/complaints/{complaint}
    // Menampilkan detail satu pengaduan setelah memeriksa otorisasi akses pengguna
    public function show(Request $request, Complaint $complaint): JsonResponse
    {
        $user = $request->user();
        $role = $user->role?->role_name;

        // Cek hak akses: Hanya Admin, PIC dari ruangan tersebut, atau pembuat laporan sendiri yang boleh melihat detailnya
        $canView = $role === 'Admin'
            || ($role === 'PIC' && $complaint->room()->where('pic_id', $user->id)->exists())
            || (in_array($role, ['Guru', 'Organisasi'], true) && $complaint->reporter_id === $user->id);

        if (! $canView) {
            return $this->sendError('Anda tidak memiliki akses ke pengaduan ini.', null, 403);
        }

        $complaint->load(['room.pic', 'reporter']);

        return $this->sendResponse(
            new ComplaintResource($complaint),
            'Detail pengaduan berhasil dimuat.',
        );
    }

    /**
     * Update repair status (PIC of room or Admin).
     */
    // Endpoint: PATCH /api/complaints/{complaint}/status
    // Mengubah status perbaikan ('Belum Diperbaiki', 'Menunggu Spare Part', 'Sudah Diperbaiki')
    public function updateStatus(Request $request, Complaint $complaint): JsonResponse
    {
        $user = $request->user();
        $role = $user->role?->role_name;

        // Pastikan hanya Admin atau PIC penanggung jawab ruangan terkait yang bisa mengubah status perbaikan
        $isRoomPic = $role === 'PIC' && $complaint->room()->where('pic_id', $user->id)->exists();
        if ($role !== 'Admin' && ! $isRoomPic) {
            return $this->sendError('Hanya PIC penanggung jawab ruangan yang dapat mengubah status perbaikan.', null, 403);
        }

        // Aturan perubahan status (one-way workflow):
        // 1. Jika sudah "Sudah Diperbaiki", status terkunci dan tidak bisa diubah kembali
        if ($complaint->status_repair === 'Sudah Diperbaiki') {
            return $this->sendError('Pengaduan yang telah diperbaiki tidak dapat diubah statusnya lagi.', null, 422);
        }

        // Validasi pilihan status perbaikan yang diizinkan, catatan keterangan, dan foto bukti perbaikan
        $validator = Validator::make($request->all(), [
            'status_repair' => ['required', 'in:Belum Diperbaiki,Menunggu Spare Part,Sudah Diperbaiki'],
            'repair_note' => ['nullable', 'string', 'max:2000'],
            'repair_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'repair_note.max' => 'Keterangan perbaikan maksimal 2000 karakter.',
            'repair_photo.image' => 'File bukti perbaikan harus berupa gambar.',
            'repair_photo.mimes' => 'Format foto bukti perbaikan harus JPG, PNG, atau WebP.',
            'repair_photo.max' => 'Ukuran foto bukti perbaikan maksimal 5 MB.',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validasi gagal.', $validator->errors(), 422);
        }

        $data = $validator->validated();

        // 2. Jika status saat ini "Menunggu Spare Part", tidak boleh mundur kembali ke "Belum Diperbaiki"
        if ($complaint->status_repair === 'Menunggu Spare Part' && $data['status_repair'] === 'Belum Diperbaiki') {
            return $this->sendError('Status yang sudah Menunggu Spare Part tidak dapat dikembalikan ke Belum Diperbaiki.', null, 422);
        }

        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('complaints')) {
                if (! \Illuminate\Support\Facades\Schema::hasColumn('complaints', 'repair_note')) {
                    \Illuminate\Support\Facades\Schema::table('complaints', function (\Illuminate\Database\Schema\Blueprint $table) {
                        $table->text('repair_note')->nullable()->after('status_repair');
                    });
                }
                if (! \Illuminate\Support\Facades\Schema::hasColumn('complaints', 'repair_photo_path')) {
                    \Illuminate\Support\Facades\Schema::table('complaints', function (\Illuminate\Database\Schema\Blueprint $table) {
                        $table->string('repair_photo_path', 2048)->nullable()->after('repair_note');
                    });
                }
                if (! \Illuminate\Support\Facades\Schema::hasColumn('complaints', 'repair_photo_data')) {
                    \Illuminate\Support\Facades\Schema::table('complaints', function (\Illuminate\Database\Schema\Blueprint $table) {
                        $table->longText('repair_photo_data')->nullable()->after('repair_photo_path');
                    });
                }
            }
        } catch (\Throwable $e) {
            // Abaikan jika alter table gagal
        }

        $updateData = ['status_repair' => $data['status_repair']];
        $hasRepairNoteColumn = false;
        try {
            $hasRepairNoteColumn = \Illuminate\Support\Facades\Schema::hasColumn('complaints', 'repair_note');
        } catch (\Throwable $e) {
            $hasRepairNoteColumn = false;
        }

        if ($hasRepairNoteColumn && ($data['status_repair'] === 'Sudah Diperbaiki' || array_key_exists('repair_note', $data))) {
            $updateData['repair_note'] = $data['repair_note'] ?? null;
        }

        // Jika status diubah menjadi "Sudah Diperbaiki" dan menyertakan foto bukti perbaikan
        if ($data['status_repair'] === 'Sudah Diperbaiki' && $request->hasFile('repair_photo')) {
            $oldRepairPhoto = $complaint->repair_photo_path;
            $newRepairPhotoPath = $this->storeCompressedPhoto($data['repair_photo']);
            $updateData['repair_photo_path'] = $newRepairPhotoPath;

            try {
                if (\Illuminate\Support\Facades\Schema::hasColumn('complaints', 'repair_photo_data')) {
                    $updateData['repair_photo_data'] = app(ImageCompressionService::class)->encodePhotoFromDisk($newRepairPhotoPath);
                }
            } catch (\Throwable $e) {
                // Abaikan jika encode gagal
            }

            if ($oldRepairPhoto && Storage::disk('public')->exists($oldRepairPhoto)) {
                Storage::disk('public')->delete($oldRepairPhoto);
            }
        }

        $complaint->update($updateData);

        // Notify reporter about updated repair status
        // Kirim notifikasi ke pelapor bahwa status perbaikan ruangannya telah diperbarui oleh PIC/Admin
        $noteInfo = ! empty($data['repair_note']) ? " Keterangan: {$data['repair_note']}" : '';
        Notification::create([
            'complaint_id' => $complaint->id,
            'user_id' => $complaint->reporter_id,
            'message' => "Status perbaikan pengaduan di {$complaint->room->room_name} diperbarui menjadi: {$data['status_repair']}.{$noteInfo}",
        ]);

        return $this->sendResponse(
            new ComplaintResource($complaint->fresh(['room.pic', 'reporter'])),
            'Status perbaikan pengaduan berhasil diperbarui.',
        );
    }

    /**
     * Update/replace photo evidence (Reporter or Admin).
     */
    // Endpoint: POST /api/complaints/{complaint}/photo
    // Mengganti foto bukti kerusakan dengan file foto baru dan menghapus file foto lama dari storage
    public function updatePhoto(Request $request, Complaint $complaint): JsonResponse
    {
        $user = $request->user();
        $role = $user->role?->role_name;

        // Hanya pelapor asli atau Admin yang berhak mengganti foto bukti pengaduan
        if ($role !== 'Admin' && (int) $complaint->reporter_id !== (int) $user->id) {
            return $this->sendError('Hanya pelapor pengaduan yang dapat memperbarui foto bukti.', null, 403);
        }

        $validator = Validator::make($request->all(), [
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validasi gagal.', $validator->errors(), 422);
        }

        $data = $validator->validated();
        $oldPhotoPath = $complaint->photo_path;

        // Simpan dan kompres foto baru
        try {
            $newPhotoPath = $this->storeCompressedPhoto($data['photo']);
        } catch (\Throwable $e) {
            return $this->sendError('Gagal memproses foto baru: '.$e->getMessage(), null, 500);
        }

        $updatePayload = ['photo_path' => $newPhotoPath];
        try {
            if (\Illuminate\Support\Facades\Schema::hasColumn('complaints', 'photo_data')) {
                $updatePayload['photo_data'] = app(ImageCompressionService::class)->encodePhotoFromDisk($newPhotoPath);
            }
        } catch (\Throwable $e) {
            // Abaikan jika gagal encode cadangan
        }

        $complaint->update($updatePayload);

        // Hapus file foto lama dari disk agar tidak memenuhi penyimpanan server
        if ($oldPhotoPath && Storage::disk('public')->exists($oldPhotoPath)) {
            Storage::disk('public')->delete($oldPhotoPath);
        }

        return $this->sendResponse(
            new ComplaintResource($complaint->fresh(['room.pic', 'reporter'])),
            'Foto bukti pengaduan berhasil diperbarui.',
        );
    }

    /**
     * Delete complaint (Reporter or Admin).
     */
    // Endpoint: DELETE /api/complaints/{complaint}
    // Menghapus data laporan pengaduan beserta file foto buktinya dari storage
    public function destroy(Request $request, Complaint $complaint): JsonResponse
    {
        $user = $request->user();
        $role = $user->role?->role_name;

        // Hanya pelapor asli atau Admin yang diizinkan menghapus laporan pengaduan
        if ($role !== 'Admin' && $complaint->reporter_id !== $user->id) {
            return $this->sendError('Hanya pelapor pengaduan yang dapat menghapus pengaduan.', null, 403);
        }

        // Hapus file fisik foto bukti jika ada di storage
        if ($complaint->photo_path && Storage::disk('public')->exists($complaint->photo_path)) {
            Storage::disk('public')->delete($complaint->photo_path);
        }

        $complaint->delete();

        return $this->sendResponse(null, 'Pengaduan berhasil dihapus.');
    }

    /**
     * Stream evidence photo for authorized users.
     */
    // Endpoint: GET /api/complaints/{complaint}/photo
    // Mengalirkan (stream) file gambar bukti kerusakan secara aman setelah memeriksa hak akses pengguna
    public function photo(Request $request, Complaint $complaint): StreamedResponse|JsonResponse
    {
        $user = $request->user();
        $role = $user->role?->role_name;

        $canView = $role === 'Admin'
            || ($role === 'PIC' && (($complaint->room && (int) $complaint->room->pic_id === (int) $user->id) || $complaint->room()->where('pic_id', $user->id)->exists()))
            || (in_array($role, ['Guru', 'Organisasi'], true) && (int) $complaint->reporter_id === (int) $user->id);

        if (! $canView) {
            return $this->sendError('Tidak memiliki izin melihat foto pengaduan ini.', null, 403);
        }

        $resolvedPath = app(ImageCompressionService::class)->resolveComplaintPhotoPath($complaint);
        if (! $resolvedPath) {
            return $this->sendError('File foto tidak ditemukan.', null, 404);
        }

        return Storage::disk('public')->response($resolvedPath, disposition: 'inline');
    }

    /**
     * Stream repair proof photo for authorized users.
     */
    // Endpoint: GET /api/complaints/{complaint}/repair-photo
    // Mengalirkan (stream) file gambar bukti perbaikan secara aman setelah memeriksa hak akses pengguna
    public function repairPhoto(Request $request, Complaint $complaint): StreamedResponse|JsonResponse
    {
        $user = $request->user();
        $role = $user->role?->role_name;

        $canView = $role === 'Admin'
            || ($role === 'PIC' && (($complaint->room && (int) $complaint->room->pic_id === (int) $user->id) || $complaint->room()->where('pic_id', $user->id)->exists()))
            || (in_array($role, ['Guru', 'Organisasi'], true) && (int) $complaint->reporter_id === (int) $user->id);

        if (! $canView) {
            return $this->sendError('Tidak memiliki izin melihat foto bukti perbaikan ini.', null, 403);
        }

        $resolvedPath = app(ImageCompressionService::class)->resolveComplaintRepairPhotoPath($complaint);
        if (! $resolvedPath) {
            return $this->sendError('File foto bukti perbaikan tidak ditemukan.', null, 404);
        }

        return Storage::disk('public')->response($resolvedPath, disposition: 'inline');
    }

    /**
     * Complaint reports for PIC filtered by period (harian, mingguan, bulanan).
     */
    // Endpoint: GET /api/pic/complaint-reports?periode=harian|mingguan|bulanan&tanggal=YYYY-MM-DD
    // Menyajikan laporan rekapitulasi pengaduan untuk ruangan yang dikelola oleh PIC berdasarkan periode waktu
    public function picReports(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user->role?->role_name;

        // Endpoint ini khusus untuk role PIC
        if ($role !== 'PIC') {
            return $this->sendError('Laporan pengaduan PIC hanya dapat diakses oleh role PIC.', null, 403);
        }

        // Validasi parameter filter `periode` (wajib) dan `tanggal` referensi (opsional, default: hari ini)
        $validator = Validator::make($request->all(), [
            'periode' => ['required', 'in:harian,mingguan,bulanan'],
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Parameter filter laporan tidak valid.', $validator->errors(), 422);
        }

        $periode = $request->input('periode');
        $tanggal = Carbon::parse($request->input('tanggal', today()->toDateString()));

        // Tentukan batas tanggal awal dan akhir sesuai periode yang dipilih (harian / mingguan Senin-Minggu / bulanan)
        if ($periode === 'harian') {
            $awal = $tanggal->copy()->startOfDay();
            $akhir = $tanggal->copy()->endOfDay();
        } elseif ($periode === 'mingguan') {
            $awal = $tanggal->copy()->startOfWeek(Carbon::MONDAY);
            $akhir = $tanggal->copy()->endOfWeek(Carbon::SUNDAY);
        } else {
            $awal = $tanggal->copy()->startOfMonth();
            $akhir = $tanggal->copy()->endOfMonth();
        }

        // Ambil semua pengaduan pada ruangan milik PIC dalam rentang tanggal tersebut
        $complaints = Complaint::with(['room', 'reporter'])
            ->whereHas('room', fn ($query) => $query->where('pic_id', $user->id))
            ->whereDate('created_at', '>=', $awal->toDateString())
            ->whereDate('created_at', '<=', $akhir->toDateString())
            ->orderBy('created_at')
            ->get();

        // Hitung ringkasan jumlah berdasarkan masing-masing status perbaikan
        $stats = [
            'total' => $complaints->count(),
            'belum_diperbaiki' => $complaints->where('status_repair', 'Belum Diperbaiki')->count(),
            'menunggu_spare_part' => $complaints->where('status_repair', 'Menunggu Spare Part')->count(),
            'sudah_diperbaiki' => $complaints->where('status_repair', 'Sudah Diperbaiki')->count(),
        ];

        return $this->sendResponse([
            'periode' => $periode,
            'tanggal_referensi' => $tanggal->toDateString(),
            'rentang_awal' => $awal->toDateString(),
            'rentang_akhir' => $akhir->toDateString(),
            'stats' => $stats,
            'items' => ComplaintResource::collection($complaints),
        ], 'Laporan pengaduan PIC berhasil dimuat.');
    }

    /**
     * Compress image evidence to JPEG when GD is available.
     */
    // Fungsi helper privat untuk memperkecil resolusi dan mengompres kualitas gambar ke JPEG secara otomatis
    // Menggunakan ImageCompressionService agar ukuran file hemat penyimpanan dan cepat dimuat di aplikasi mobile
    private function storeCompressedPhoto(UploadedFile $photo): string
    {
        return app(ImageCompressionService::class)->compressAndStore($photo, 'complaints');
    }

    // Fungsi fallback untuk menyimpan file gambar asli apabila ekstensi GD tidak tersedia
    private function storeOriginalPhoto(UploadedFile $photo): string
    {
        $path = $photo->store('complaints', 'public');
        if (! is_string($path)) {
            throw new RuntimeException('Foto gagal disimpan.');
        }

        return $path;
    }
}

