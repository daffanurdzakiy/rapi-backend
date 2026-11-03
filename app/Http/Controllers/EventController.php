<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Notification;
use App\Models\Room;
use App\Models\SessionTime;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

// Controller untuk mengelola Pengajuan & Persetujuan Peminjaman Ruangan (Agenda) di Web.
class EventController extends Controller
{
    /** List all bookings for admins, assigned rooms for PICs, and own bookings for other users. */
    // Menampilkan daftar agenda sesuai Role:
    // - Admin: melihat semua pengajuan agenda.
    // - PIC: melihat pengajuan agenda pada ruangan yang menjadi tanggung jawabnya.
    // - Guru & Organisasi: melihat agenda yang mereka ajukan sendiri.
    public function index(Request $request): View
    {
        $user = $request->user();
        $events = Event::with(['room', 'user', 'approver'])->latest('event_date');
        $role = $user->role?->role_name;
        if ($role === 'PIC') {
            $events->whereHas('room', fn ($query) => $query->where('pic_id', $user->id));
        } elseif ($role !== 'Admin') {
            $events->where('user_id', $user->id);
        }

        return view('events.index', ['events' => $events->get()]);
    }

    /** Display the room booking request form. */
    // Menampilkan form pengajuan peminjaman ruangan (hanya untuk Admin, Guru, dan Organisasi).
    public function create(Request $request): View
    {
        $roleName = $request->user()->role?->role_name;
        abort_unless(in_array($roleName, ['Admin', 'Guru', 'Organisasi']), 403);

        return view('events.form', [
            'rooms' => Room::orderBy('room_name')->get(),
            'sessions' => $this->regularSessions(),
            // Aturan H-1 (paling cepat meminjam untuk hari besok / Carbon::tomorrow()).
            'firstBookingDate' => Carbon::tomorrow()->toDateString(),
            // Hanya Admin yang diizinkan memilih waktu bebas di atas pukul 17:00.
            'canUseFreeTime' => $roleName === 'Admin',
        ]);
    }

    /** Save a request after rejecting overlapping room and time ranges. */
    // Menyimpan pengajuan agenda baru setelah memastikan tidak ada jadwal yang bentrok.
    public function store(Request $request): RedirectResponse
    {
        $roleName = $request->user()->role?->role_name;
        abort_unless(in_array($roleName, ['Admin', 'Guru', 'Organisasi']), 403);

        $data = $this->validateEvent($request);
        // Mengecek apakah ruangan sudah dipakai pada tanggal dan jam tersebut (Anti-Bentrok).
        $this->ensureAvailable($data['room_id'], $data['event_date'], $data['start_time'], $data['end_time']);
        // Jika dibuat oleh Admin langsung 'Disetujui', jika Guru/Organisasi statusnya 'Pending'.
        $status = $roleName === 'Admin' ? 'Disetujui' : 'Pending';
        Event::create($data + [
            'user_id' => $request->user()->id,
            'status' => $status,
            'approved_by' => $roleName === 'Admin' ? $request->user()->id : null,
        ]);

        $message = $roleName === 'Admin'
            ? 'Agenda berhasil dibuat dan langsung disetujui.'
            : 'Agenda berhasil diajukan dan menunggu persetujuan.';

        return redirect()->route('events.index')->with('success', $message);
    }

    // Menampilkan halaman Edit Agenda (khusus Admin untuk memindahkan ruangan atau mengubah jam).
    public function edit(Request $request, Event $event): View
    {
        abort_unless($request->user()->role?->role_name === 'Admin', 403);
        abort_unless(in_array($event->status, ['Pending', 'Disetujui']), 422);

        return view('events.edit', [
            'event' => $event->load(['room', 'user']),
            'rooms' => Room::orderBy('room_name')->get(),
            'sessions' => $this->regularSessions(),
        ]);
    }

    // Menyimpan perubahan ruangan/jam agenda oleh Admin dan mengirim notifikasi ke peminjam.
    public function update(Request $request, Event $event): RedirectResponse
    {
        abort_unless($request->user()->role?->role_name === 'Admin', 403);
        abort_unless(in_array($event->status, ['Pending', 'Disetujui']), 422);

        $this->useFreeTimeInputs($request);
        $sessionTimes = $this->regularSessionTimes();
        $canUseFreeTime = $request->user()->role?->role_name === 'Admin';

        $data = $request->validate([
            'room_id' => ['required', 'exists:rooms,id'],
            'start_time' => [
                'required',
                'date_format:H:i',
                function (string $attribute, mixed $value, \Closure $fail) use ($sessionTimes, $canUseFreeTime): void {
                    if (! in_array($value, $sessionTimes, true) && (! $canUseFreeTime || $value <= '17:00')) {
                        $fail($canUseFreeTime
                            ? 'Jam mulai harus merupakan sesi 1–14 atau waktu bebas setelah pukul 17.00.'
                            : 'Guru dan Organisasi hanya dapat memilih sesi 1–14, paling lambat pukul 17.00.');
                    }
                },
            ],
            'end_time' => [
                'required',
                'date_format:H:i',
                'after:start_time',
                function (string $attribute, mixed $value, \Closure $fail) use ($sessionTimes, $canUseFreeTime): void {
                    if (! in_array($value, $sessionTimes, true) && (! $canUseFreeTime || $value <= '17:00')) {
                        $fail($canUseFreeTime
                            ? 'Jam selesai harus merupakan sesi 1–14 atau waktu bebas setelah pukul 17.00.'
                            : 'Guru dan Organisasi hanya dapat memilih sesi 1–14, paling lambat pukul 17.00.');
                    }
                },
            ],
            'move_reason' => ['nullable', 'string', 'max:1000'],
        ], [
            'end_time.after' => 'Jam selesai harus setelah jam mulai.',
        ]);

        $roomChanged = (int) $data['room_id'] !== (int) $event->room_id;
        if ($roomChanged && blank($data['move_reason'] ?? null)) {
            throw ValidationException::withMessages([
                'move_reason' => 'Alasan pemindahan ruangan wajib diisi.',
            ]);
        }

        $date = Carbon::parse($event->event_date)->toDateString();
        $this->ensureAvailable(
            (int) $data['room_id'],
            $date,
            $data['start_time'],
            $data['end_time'],
            $event->id,
        );

        $timeChanged = substr($event->start_time, 0, 5) !== $data['start_time']
            || substr($event->end_time, 0, 5) !== $data['end_time'];
        $event->update([
            'room_id' => $data['room_id'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'move_reason' => $roomChanged ? $data['move_reason'] : $event->move_reason,
        ]);

        if ($roomChanged || $timeChanged) {
            $message = "Agenda {$event->title} diperbarui: {$event->room->room_name}, "
                .Carbon::parse($event->start_time)->format('H:i').'-'
                .Carbon::parse($event->end_time)->format('H:i').'.';
            if ($roomChanged) {
                $message .= ' Alasan: '.$data['move_reason'];
            }

            Notification::create([
                'event_id' => $event->id,
                'user_id' => $event->user_id,
                'message' => $message,
            ]);
        }

        return redirect()->route('events.index')->with('success', 'Agenda berhasil diperbarui.');
    }

    /** Approve or reject a pending booking as Admin or the assigned room's PIC. */
    // Memproses keputusan Setujui ('Disetujui') atau Tolak ('Ditolak') oleh Admin maupun PIC ruangan terkait.
    public function decide(Request $request, Event $event): RedirectResponse
    {
        $user = $request->user();
        $role = $user->role?->role_name;
        abort_unless(
            $role === 'Admin'
                || ($role === 'PIC' && $event->room()->where('pic_id', $user->id)->exists()),
            403,
        );
        abort_unless(
            $role === 'Admin' || $event->status === 'Pending',
            422,
            'Hanya agenda yang menunggu persetujuan yang dapat diproses.',
        );

        // Jika ditolak, wajib mengisi alasan penolakan (rejection_reason).
        $data = $request->validate([
            'status' => ['required', 'in:Disetujui,Ditolak'],
            'rejection_reason' => ['required_if:status,Ditolak', 'nullable', 'string', 'max:1000'],
        ], [
            'rejection_reason.required_if' => 'Keterangan penolakan wajib diisi.',
            'rejection_reason.max' => 'Keterangan penolakan maksimal 1000 karakter.',
        ]);
        if ($data['status'] === 'Disetujui') {
            $this->ensureAvailable($event->room_id, Carbon::parse($event->event_date)->toDateString(), $event->start_time, $event->end_time, $event->id);
        }

        $reason = $data['status'] === 'Ditolak' ? trim($data['rejection_reason']) : null;
        $event->update([
            'status' => $data['status'],
            'approved_by' => $data['status'] === 'Disetujui' ? $user->id : null,
            'rejection_reason' => $reason,
        ]);

        // Kirim notifikasi ke pemohon jika agendanya ditolak beserta alasannya.
        if ($reason !== null && $reason !== '') {
            Notification::create([
                'event_id' => $event->id,
                'user_id' => $event->user_id,
                'message' => "Pengajuan agenda {$event->title} ditolak. Alasan: {$reason}",
            ]);
        }

        return back()->with('success', 'Status agenda berhasil diperbarui.');
    }

    // Membatalkan/menghapus agenda yang sudah disetujui (khusus Admin).
    public function destroy(Request $request, Event $event): RedirectResponse
    {
        abort_unless($request->user()->role?->role_name === 'Admin', 403);
        abort_unless($event->status === 'Disetujui', 422, 'Hanya agenda yang disetujui yang dapat dibatalkan.');
        $event->delete();

        return back()->with('success', 'Agenda yang disetujui berhasil dihapus.');
    }

    /** Validate common event fields and ensure an end time follows the start time. */
    // Validasi aturan pengajuan peminjaman ruangan:
    // 1. event_date 'after:today' -> minimal pengajuan H-1 (besok).
    // 2. Guru & Organisasi hanya boleh memilih Sesi 1-14 (maksimal pukul 17:00).
    // 3. Admin boleh memilih waktu bebas setelah pukul 17:00.
    private function validateEvent(Request $request): array
    {
        $this->useFreeTimeInputs($request);
        $sessionTimes = $this->regularSessionTimes();
        $canUseFreeTime = $request->user()->role?->role_name === 'Admin';

        $data = $request->validate([
            'room_id' => ['required', 'exists:rooms,id'],
            'title' => ['required', 'string', 'max:255'],
            'event_date' => ['required', 'date', 'after:today'],
            'start_time' => [
                'required',
                'date_format:H:i',
                function (string $attribute, mixed $value, \Closure $fail) use ($sessionTimes, $canUseFreeTime): void {
                    if (! in_array($value, $sessionTimes, true) && (! $canUseFreeTime || $value <= '17:00')) {
                        $fail($canUseFreeTime
                            ? 'Pilih sesi 1–14 atau waktu bebas setelah pukul 17.00.'
                            : 'Guru dan Organisasi hanya dapat memilih sesi 1–14, paling lambat pukul 17.00.');
                    }
                },
            ],
            'end_time' => [
                'required',
                'date_format:H:i',
                'after:start_time',
                function (string $attribute, mixed $value, \Closure $fail) use ($sessionTimes, $canUseFreeTime): void {
                    if (! in_array($value, $sessionTimes, true) && (! $canUseFreeTime || $value <= '17:00')) {
                        $fail($canUseFreeTime
                            ? 'Pilih sesi 1–14 atau waktu bebas setelah pukul 17.00.'
                            : 'Guru dan Organisasi hanya dapat memilih sesi 1–14, paling lambat pukul 17.00.');
                    }
                },
            ],
        ], [
            'event_date.after' => 'Tanggal peminjaman ruang paling cepat besok (H-1) atau setelahnya.',
            'end_time.after' => 'Jam selesai harus setelah jam mulai.',
        ]);

        return $data;
    }

    // Mengambil nilai input jam manual (start_time_free / end_time_free) apabila Admin memilih opsi Waktu Bebas ('free').
    private function useFreeTimeInputs(Request $request): void
    {
        foreach (['start_time', 'end_time'] as $field) {
            if ($request->input($field) === 'free') {
                $request->merge([$field => $request->input($field.'_free')]);
            }
        }
    }

    // Mengambil daftar Sesi reguler (Sesi 1 s/d Sesi 14) dari tabel session_times.
    private function regularSessions()
    {
        return SessionTime::where('is_free', false)->orderBy('time')->get();
    }

    /** @return list<string> */
    private function regularSessionTimes(): array
    {
        return $this->regularSessions()
            ->map(fn (SessionTime $session) => substr($session->time, 0, 5))
            ->all();
    }

    /** Enforce UKK conflict prevention for pending and approved bookings in one room. */
    // Logika Anti-Bentrok Jadwal Ruangan.
    // Mengecek apakah di ruangan & tanggal yang sama sudah ada agenda 'Pending' atau 'Disetujui' yang jamnya beririsan.
    private function ensureAvailable(int $roomId, string $date, string $start, string $end, ?int $ignoreId = null): void
    {
        $conflict = Event::where('room_id', $roomId)->whereDate('event_date', $date)->whereIn('status', ['Pending', 'Disetujui'])->where('start_time', '<', $end)->where('end_time', '>', $start)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists();
        if ($conflict) {
            throw ValidationException::withMessages(['room_id' => 'Ruangan sudah digunakan pada rentang waktu tersebut. Silakan pilih ruang atau waktu lain.']);
        }
    }
}
