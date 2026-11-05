<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\Notification;
use App\Models\Room;
use App\Services\ImageCompressionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Controller untuk mengelola Pengaduan Kerusakan Fasilitas Ruangan di Web.
class ComplaintController extends Controller
{
    /** Pastikan kolom repair_note tersedia pada tabel complaints (otomatis memperbaiki jika belum migrasi di hosting). */
    protected function ensureRepairNoteColumnExists(): void
    {
        try {
            if (Schema::hasTable('complaints') && ! Schema::hasColumn('complaints', 'repair_note')) {
                Schema::table('complaints', function (Blueprint $table) {
                    $table->text('repair_note')->nullable()->after('status_repair');
                });
            }
        } catch (\Throwable $e) {
            // Abaikan jika tidak memiliki hak akses alter table atau sedang dalam proses
        }
    }

    /** Show complaints according to the viewer's role. */
    // Menampilkan daftar pengaduan sesuai Role:
    // - Admin: melihat semua pengaduan + tombol peringatan WhatsApp ke PIC.
    // - PIC: hanya melihat pengaduan di ruangan yang ditugaskan kepadanya.
    // - Guru & Organisasi: hanya melihat pengaduan yang mereka laporkan sendiri.
    public function index(Request $request): View
    {
        $this->ensureRepairNoteColumnExists();

        $baseQuery = Complaint::with(['room.pic', 'reporter']);
        $role = $request->user()->role?->role_name;
        if ($role === 'PIC') {
            $baseQuery->whereHas('room', fn ($roomQuery) => $roomQuery->where('pic_id', $request->user()->id));
        } elseif (in_array($role, ['Guru', 'Organisasi'])) {
            $baseQuery->where('reporter_id', $request->user()->id);
        }

        // Batas waktu 24 jam untuk laporan yang 'Sudah Diperbaiki' agar tetap tampil di halaman pengaduan fasilitas.
        // Setelah melewati kurun waktu 24 jam sejak diperbaiki, kartu laporan otomatis tidak ditampilkan di menu ini
        // (namun data di database tetap utuh dan tetap muncul pada menu laporan/rekap).
        $repairedCutoff = now()->subHours(24);

        // Menghitung jumlah per status untuk badge filter: semua, belum diperbaiki, menunggu spare part, dan sudah diperbaiki
        $counts = [
            'semua' => (clone $baseQuery)->where(function ($q) use ($repairedCutoff) {
                $q->where('status_repair', '!=', 'Sudah Diperbaiki')
                  ->orWhere('updated_at', '>=', $repairedCutoff);
            })->count(),
            'belum_diperbaiki' => (clone $baseQuery)->where('status_repair', 'Belum Diperbaiki')->count(),
            'menunggu_spare_part' => (clone $baseQuery)->where('status_repair', 'Menunggu Spare Part')->count(),
            'sudah_diperbaiki' => (clone $baseQuery)->where('status_repair', 'Sudah Diperbaiki')
                ->where('updated_at', '>=', $repairedCutoff)
                ->count(),
        ];

        // Memfilter query pengaduan sesuai filter yang dipilih
        $filter = $request->query('status', 'semua');
        $statusMap = [
            'belum_diperbaiki' => 'Belum Diperbaiki',
            'Belum Diperbaiki' => 'Belum Diperbaiki',
            'menunggu_spare_part' => 'Menunggu Spare Part',
            'Menunggu Spare Part' => 'Menunggu Spare Part',
            'sudah_diperbaiki' => 'Sudah Diperbaiki',
            'Sudah Diperbaiki' => 'Sudah Diperbaiki',
        ];

        $currentStatus = 'semua';
        if (isset($statusMap[$filter])) {
            $mapped = $statusMap[$filter];
            $baseQuery->where('status_repair', $mapped);
            if ($mapped === 'Sudah Diperbaiki') {
                $baseQuery->where('updated_at', '>=', $repairedCutoff);
            }
            $currentStatus = array_search($mapped, [
                'belum_diperbaiki' => 'Belum Diperbaiki',
                'menunggu_spare_part' => 'Menunggu Spare Part',
                'sudah_diperbaiki' => 'Sudah Diperbaiki',
            ], true) ?: 'semua';
        } else {
            // Default filter 'semua': tidak menyertakan yang sudah diperbaiki lebih dari 24 jam lalu
            $baseQuery->where(function ($q) use ($repairedCutoff) {
                $q->where('status_repair', '!=', 'Sudah Diperbaiki')
                  ->orWhere('updated_at', '>=', $repairedCutoff);
            });
        }

        $complaints = $baseQuery->latest()->get();
        $whatsappWarnings = [];

        // Pembuatan link otomatis WhatsApp (wa.me) untuk Admin menegur PIC jika status masih 'Belum Diperbaiki'.
        if ($role === 'Admin') {
            foreach ($complaints as $complaint) {
                $phone = $complaint->room->pic?->whatsapp;
                if ($complaint->status_repair !== 'Belum Diperbaiki' || blank($phone)) {
                    continue;
                }

                // Mengubah awalan nomor 08... menjadi kode negara 628...
                $number = preg_replace('/\D+/', '', $phone);
                if (str_starts_with($number, '0')) {
                    $number = '62'.substr($number, 1);
                } elseif (str_starts_with($number, '8')) {
                    $number = '62'.$number;
                }

                if ($number === '') {
                    continue;
                }

                // Isi pesan teks WhatsApp otomatis yang dikirim Admin ke PIC.
                $message = implode("\n", [
                    'Peringatan Pengaduan Fasilitas RAPI-Sekolah',
                    'Ruangan: '.$complaint->room->room_name,
                    'Pengaduan: '.$complaint->description,
                    'Pelapor: '.$complaint->reporter->name,
                    'Status: '.$complaint->status_repair,
                    'Mohon segera ditindaklanjuti.',
                ]);

                $whatsappWarnings[$complaint->id] = 'https://wa.me/'.$number.'?text='.rawurlencode($message);
            }
        }

        return view('complaints.index', [
            'complaints' => $complaints,
            'canReport' => in_array($role, ['Guru', 'Organisasi']),
            'whatsappWarnings' => $whatsappWarnings,
            'counts' => $counts,
            'currentFilter' => $currentStatus,
        ]);
    }

    /** Stream an evidence photo to a user allowed to view its complaint. */
    // Menampilkan file foto bukti pengaduan secara aman (hanya bisa diakses oleh yang berhak).
    public function showPhoto(Request $request, Complaint $complaint): StreamedResponse
    {
        $user = $request->user();
        $role = $user->role?->role_name;
        $canView = $role === 'Admin'
            || ($role === 'PIC' && $complaint->room()->where('pic_id', $user->id)->exists())
            || (in_array($role, ['Guru', 'Organisasi']) && $complaint->reporter_id === $user->id);

        abort_unless($canView, 403);
        abort_unless($complaint->photo_path && Storage::disk('public')->exists($complaint->photo_path), 404);

        $disk = Storage::disk('public');

        return $disk->response(
            $complaint->photo_path,
            headers: ['Content-Type' => $disk->mimeType($complaint->photo_path)],
            disposition: 'inline',
        );
    }

    /** Display complaint form with camera-capable image input. */
    // Menampilkan form pembuatan laporan pengaduan fasilitas baru.
    public function create(Request $request): View
    {
        abort_unless(in_array($request->user()->role?->role_name, ['Admin', 'Guru', 'Organisasi']), 403);
        $data = $request->validate([
            'room_id' => ['sometimes', 'required', 'integer', 'exists:rooms,id'],
        ]);

        return view('complaints.form', [
            'rooms' => Room::orderBy('room_name')->get(),
            'selectedRoom' => isset($data['room_id']) ? Room::findOrFail($data['room_id']) : null,
            'reporterName' => $request->user()->name,
        ]);
    }

    /** Persist a facility report with its required evidence photo. */
    // Menyimpan laporan pengaduan baru beserta foto bukti dan mengirim notifikasi ke PIC ruangan.
    public function store(Request $request): RedirectResponse
    {
        abort_unless(in_array($request->user()->role?->role_name, ['Admin', 'Guru', 'Organisasi']), 403);
        // Validasi form pengaduan (max:5120 artinya ukuran foto maksimal 5 MB / 5120 KB).
        $data = $request->validate([
            'room_id' => ['required', 'exists:rooms,id'],
            'description' => ['required', 'string', 'max:2000'],
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'photo.uploaded' => 'Foto gagal diterima server. Pilih ulang foto maksimal 5 MB dan coba lagi.',
            'photo.max' => 'Ukuran foto maksimal 5 MB.',
            'photo.image' => 'File yang diunggah harus berupa gambar.',
            'photo.mimes' => 'Format foto harus JPG, PNG, atau WebP.',
        ]);
        $data['photo_path'] = $this->storeCompressedPhoto($data['photo']);
        unset($data['photo']);
        $complaint = Complaint::create($data + ['reporter_id' => $request->user()->id]);
        $room = Room::with('pic')->findOrFail($complaint->room_id);

        // Membuat notifikasi otomatis untuk PIC yang bertanggung jawab atas ruangan tersebut.
        Notification::create([
            'complaint_id' => $complaint->id,
            'user_id' => $room->pic_id,
            'message' => "Pengaduan baru di {$room->room_name} dari {$request->user()->name}: {$complaint->description}",
        ]);

        return redirect()->route('complaints.index')->with('success', 'Pengaduan berhasil dikirim.');
    }

    /** Add or replace a complaint photo as its reporter or an administrator. */
    // Mengganti foto bukti pengaduan (hanya boleh dilakukan oleh pelapor atau Admin).
    public function updatePhoto(Request $request, Complaint $complaint): RedirectResponse
    {
        abort_unless(
            $request->user()->role?->role_name === 'Admin'
                || $complaint->reporter_id === $request->user()->id,
            403,
        );

        $data = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'photo.uploaded' => 'Foto gagal diterima server. Pilih ulang foto maksimal 5 MB dan coba lagi.',
            'photo.max' => 'Ukuran foto maksimal 5 MB.',
            'photo.image' => 'File yang diunggah harus berupa gambar.',
            'photo.mimes' => 'Format foto harus JPG, PNG, atau WebP.',
        ]);
        $oldPhotoPath = $complaint->photo_path;
        $complaint->update(['photo_path' => $this->storeCompressedPhoto($data['photo'])]);

        if ($oldPhotoPath) {
            Storage::disk('public')->delete($oldPhotoPath);
        }

        return back()->with('success', 'Foto pengaduan berhasil diperbarui.');
    }

    /** Let the PIC responsible for the room change a complaint to a permitted repair state. */
    // Mengubah status perbaikan oleh PIC ruangan ('Belum Diperbaiki', 'Menunggu Spare Part', 'Sudah Diperbaiki') beserta catatan keterangan perbaikan.
    public function updateStatus(Request $request, Complaint $complaint): RedirectResponse
    {
        abort_unless(
            $request->user()->role?->role_name === 'PIC'
                && $complaint->room->pic_id === $request->user()->id,
            403,
        );
        // Validasi status perbaikan dan catatan keterangan (keterangan bersifat opsional)
        $data = $request->validate([
            'status_repair' => ['required', 'in:Belum Diperbaiki,Menunggu Spare Part,Sudah Diperbaiki'],
            'repair_note' => ['nullable', 'string', 'max:2000'],
        ], [
            'repair_note.max' => 'Keterangan perbaikan maksimal 2000 karakter.',
        ]);

        $this->ensureRepairNoteColumnExists();

        $updateData = ['status_repair' => $data['status_repair']];
        $hasRepairNoteColumn = false;
        try {
            $hasRepairNoteColumn = Schema::hasColumn('complaints', 'repair_note');
        } catch (\Throwable $e) {
            $hasRepairNoteColumn = false;
        }

        if ($hasRepairNoteColumn && ($data['status_repair'] === 'Sudah Diperbaiki' || array_key_exists('repair_note', $data))) {
            $updateData['repair_note'] = $data['repair_note'] ?? null;
        }

        $complaint->update($updateData);

        // Notifikasi ke pelapor pengaduan jika status diupdate oleh PIC
        $noteSnippet = ! empty($data['repair_note']) ? " Keterangan: {$data['repair_note']}" : '';
        Notification::create([
            'complaint_id' => $complaint->id,
            'user_id' => $complaint->reporter_id,
            'message' => "Status perbaikan pengaduan di {$complaint->room->room_name} diperbarui menjadi: {$data['status_repair']}.{$noteSnippet}",
        ]);

        return back()->with('success', 'Status perbaikan berhasil diperbarui.');
    }

    /** Remove a complaint and its evidence photo when it is no longer needed. */
    // Menghapus data pengaduan beserta file fotonya dari storage.
    public function destroy(Request $request, Complaint $complaint): RedirectResponse
    {
        abort_unless($request->user()->role?->role_name === 'Admin' || $complaint->reporter_id === $request->user()->id, 403);
        if ($complaint->photo_path) {
            Storage::disk('public')->delete($complaint->photo_path);
        } $complaint->delete();

        return back()->with('success', 'Pengaduan berhasil dihapus.');
    }

    /** Compress image evidence to JPEG when GD is available, then store it outside the public web root. */
    // Fungsi bantu untuk mengompres ukuran dan resolusi foto secara otomatis agar hemat ruang penyimpanan.
    private function storeCompressedPhoto(UploadedFile $photo): string
    {
        return app(ImageCompressionService::class)->compressAndStore($photo, 'complaints');
    }

    private function memoryLimitInBytes(): int
    {
        $memoryLimit = trim((string) ini_get('memory_limit'));
        if ($memoryLimit === '-1') {
            return -1;
        }

        if (! preg_match('/^(\d+(?:\.\d+)?)\s*([KMG])?$/i', $memoryLimit, $matches)) {
            return 0;
        }

        $multiplier = match (strtoupper($matches[2] ?? '')) {
            'G' => 1024 ** 3,
            'M' => 1024 ** 2,
            'K' => 1024,
            default => 1,
        };

        return (int) ((float) $matches[1] * $multiplier);
    }

    private function storeOriginalPhoto(UploadedFile $photo): string
    {
        $path = $photo->store('complaints', 'public');

        if (! is_string($path)) {
            throw new RuntimeException('Foto gagal disimpan. Periksa ruang penyimpanan lalu coba lagi.');
        }

        return $path;
    }
}
