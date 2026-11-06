<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\Notification;
use App\Models\Room;
use App\Models\SessionTime;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

// =========================================================================
// API CONTROLLER: EVENT CONTROLLER (PEMINJAMAN RUANGAN / AGENDA)
// =========================================================================
// Mengatur seluruh logika peminjaman ruangan: daftar agenda sesuai role,
// pengajuan peminjaman baru (beserta validasi H-1, sesi jam, dan anti-bentrok),
// melihat detail agenda, keputusan persetujuan/penolakan oleh PIC, dan cek ketersediaan.
class EventController extends ApiController
{
    /**
     * List bookings based on viewer's role and optional query filters.
     */
    // Endpoint: GET /api/events
    // Menampilkan daftar pengajuan agenda:
    // - PIC: Hanya melihat agenda pada ruangan yang ia kelola
    // - Guru & Organisasi: Hanya melihat agenda yang mereka ajukan sendiri
    // - Admin: Melihat seluruh agenda
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user->role?->role_name;

        $query = Event::with(['room.pic', 'user', 'approver']);

        // Pembatasan data berdasarkan role user yang login
        if ($role === 'PIC') {
            $query->whereHas('room', fn ($roomQuery) => $roomQuery->where('pic_id', $user->id));
        } elseif ($role !== 'Admin') {
            $query->where('user_id', $user->id);
        }

        // Filter opsional: status ('Pending', 'Disetujui', 'Ditolak')
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter opsional: berdasarkan ruangan tertentu (`room_id`)
        if ($request->filled('room_id')) {
            $query->where('room_id', $request->input('room_id'));
        }

        // Filter opsional: berdasarkan tanggal spesifik (`date`) atau bulan (`month` format YYYY-MM)
        if ($request->filled('date')) {
            $query->whereDate('event_date', $request->input('date'));
        } elseif ($request->filled('month')) {
            $month = Carbon::parse($request->input('month'))->startOfMonth();
            $query->whereBetween('event_date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()]);
        }

        // Urutkan dari tanggal agenda terbaru lalu jam mulai paling awal, dengan pagination
        $events = $query->orderByDesc('event_date')->orderBy('start_time')->paginate($request->input('per_page', 20));

        return $this->sendResponse([
            'items' => EventResource::collection($events->items()),
            'pagination' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ],
        ], 'Daftar agenda peminjaman berhasil dimuat.');
    }

    /**
     * Submit a new room booking request (Guru, Organisasi, Admin).
     */
    // Endpoint: POST /api/events
    // Mengajukan peminjaman ruangan baru
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $roleName = $user->role?->role_name;

        // Aturan Bisnis: PIC tidak mengajukan peminjaman ruangan (hanya memverifikasi pengajuan di ruangannya)
        // Jika ingin agar PIC juga bisa meminjam ruangan, tambahkan 'PIC' ke array di bawah.
        if (! in_array($roleName, ['Admin', 'Guru', 'Organisasi'], true)) {
            return $this->sendError('PIC tidak memiliki hak akses untuk mengajukan agenda peminjaman ruangan.', null, 403);
        }

        // Ambil daftar jam sesi reguler (Sesi 1-14, maksimal sampai 17:00). Hanya Admin yang boleh memilih jam bebas di atas 17:00.
        $sessionTimes = $this->regularSessionTimes();
        $canUseFreeTime = $roleName === 'Admin';
        $minDateRule = $roleName === 'Admin' ? 'after_or_equal:today' : 'after:today';

        // Validasi input pengajuan agenda
        $validator = Validator::make($request->all(), [
            'room_id' => ['required_without:room_ids', 'nullable', 'exists:rooms,id'],
            'room_ids' => ['required_without:room_id', 'nullable', 'array', 'min:1'],
            'room_ids.*' => ['integer', 'exists:rooms,id'],
            'title' => ['required', 'string', 'max:255'],
            'event_date' => ['required_without:dates', 'nullable', 'date', $minDateRule],
            'dates' => ['nullable', 'array', 'min:1', 'max:30'],
            'dates.*' => ['date', $minDateRule],
            'end_date' => ['nullable', 'date', 'after_or_equal:event_date'],
            'booking_type' => ['nullable', 'in:single,range,multi'],
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
            'room_id.required_without' => 'Ruangan wajib dipilih.',
            'room_ids.required_without' => 'Pilih minimal satu ruangan.',
            'room_ids.min' => 'Pilih minimal satu ruangan.',
            'event_date.after' => 'Tanggal peminjaman ruang paling cepat besok (H-1) atau setelahnya.',
            'event_date.after_or_equal' => 'Tanggal peminjaman ruang tidak boleh sebelum hari ini.',
            'dates.*.after' => 'Setiap tanggal peminjaman ruang paling cepat besok (H-1) atau setelahnya.',
            'dates.*.after_or_equal' => 'Setiap tanggal peminjaman ruang tidak boleh sebelum hari ini.',
            'end_date.after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
            'end_time.after' => 'Jam selesai harus setelah jam mulai.',
            'room_id.exists' => 'Ruangan yang dipilih tidak valid.',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validasi gagal.', $validator->errors(), 422);
        }

        $data = $validator->validated();

        $roomIds = [];
        if ($request->has('room_ids') && is_array($request->input('room_ids')) && ! empty($request->input('room_ids'))) {
            $roomIds = array_values(array_unique(array_filter(array_map('intval', $request->input('room_ids')))));
        } elseif (! empty($data['room_id'])) {
            $roomIds = [(int) $data['room_id']];
        }

        if (empty($roomIds)) {
            return $this->sendError('Pilih minimal satu ruangan.', ['room_id' => ['Pilih minimal satu ruangan.']], 422);
        }

        $dates = [];
        if ($request->has('dates') && is_array($request->input('dates')) && ! empty($request->input('dates'))) {
            $parsedDates = collect($request->input('dates'))
                ->map(fn ($d) => Carbon::parse($d))
                ->sortBy(fn ($c) => $c->timestamp)
                ->unique(fn ($c) => $c->toDateString())
                ->values();
            foreach ($parsedDates as $pd) {
                $dates[] = $pd;
            }
            if (empty($data['event_date']) && ! empty($dates)) {
                $data['event_date'] = $dates[0]->toDateString();
            }
        } else {
            $startDate = Carbon::parse($data['event_date']);
            $isRange = ($request->input('booking_type') === 'range' || ! empty($data['end_date']))
                && ! empty($data['end_date'])
                && $data['end_date'] > $data['event_date'];

            $endDate = $isRange ? Carbon::parse($data['end_date']) : $startDate->copy();

            $current = $startDate->copy();
            while ($current->lte($endDate)) {
                $dates[] = $current->copy();
                $current->addDay();
            }
        }

        if (count($dates) > 30) {
            return $this->sendError('Peminjaman ruangan maksimal 30 hari berturut-turut.', [
                'end_date' => ['Peminjaman ruangan maksimal 30 hari berturut-turut.'],
            ], 422);
        }

        // Mengecek apakah seluruh ruangan yang dipilih sudah dipakai pada setiap hari dalam rentang tanggal
        $roomsById = Room::whereIn('id', $roomIds)->get()->keyBy('id');
        foreach ($roomIds as $rId) {
            $roomName = $roomsById[$rId]->room_name ?? "Ruang {$rId}";
            foreach ($dates as $date) {
                if ($this->hasConflict($rId, $date->toDateString(), $data['start_time'], $data['end_time'])) {
                    $dateFormatted = $date->translatedFormat('d M Y');
                    return $this->sendError("Ruangan {$roomName} sudah digunakan pada tanggal {$dateFormatted} pada rentang waktu tersebut. Silakan pilih ruang atau waktu lain.", [
                        'room_id' => ["Ruangan {$roomName} sudah dipesan pada tanggal {$dateFormatted}."],
                    ], 422);
                }
            }
        }

        // Jika yang membuat adalah Admin, status otomatis 'Disetujui'. Jika Guru/Organisasi, status awal adalah 'Pending'.
        $totalDays = count($dates);
        $totalRooms = count($roomIds);
        $status = $roleName === 'Admin' ? 'Disetujui' : 'Pending';
        $approvedBy = $roleName === 'Admin' ? $user->id : null;
        $createdEvents = [];

        DB::transaction(function () use ($roomIds, $roomsById, $dates, $totalDays, $totalRooms, $data, $user, $status, $approvedBy, &$createdEvents) {
            foreach ($roomIds as $rId) {
                $roomName = $roomsById[$rId]->room_name ?? "Ruang {$rId}";
                foreach ($dates as $index => $date) {
                    $dayNum = $index + 1;
                    if ($totalRooms > 1 && $totalDays > 1) {
                        $title = "{$data['title']} - {$roomName} (Hari {$dayNum}/{$totalDays})";
                    } elseif ($totalRooms > 1) {
                        $title = "{$data['title']} - {$roomName}";
                    } elseif ($totalDays > 1) {
                        $title = "{$data['title']} (Hari {$dayNum}/{$totalDays})";
                    } else {
                        $title = $data['title'];
                    }

                    $createdEvents[] = Event::create([
                        'user_id' => $user->id,
                        'room_id' => $rId,
                        'title' => $title,
                        'event_date' => $date->toDateString(),
                        'start_time' => $data['start_time'],
                        'end_time' => $data['end_time'],
                        'status' => $status,
                        'approved_by' => $approvedBy,
                    ]);
                }
            }
        });

        // If submitted by Guru/Organisasi, optionally notify each room's PIC
        // Kirim notifikasi ke PIC masing-masing ruangan bahwa ada pengajuan peminjaman baru yang menunggu persetujuan
        $firstDate = $dates[0];
        $lastDate = $dates[count($dates) - 1];
        if ($status === 'Pending') {
            $dateSummary = $totalDays > 1
                ? "{$firstDate->translatedFormat('d M')} s/d {$lastDate->translatedFormat('d M Y')} ({$totalDays} hari)"
                : $firstDate->translatedFormat('d M Y');

            foreach ($roomIds as $rId) {
                $room = $roomsById[$rId] ?? null;
                if ($room && $room->pic_id) {
                    $firstRoomEvent = collect($createdEvents)->firstWhere('room_id', $rId);
                    Notification::create([
                        'event_id' => $firstRoomEvent?->id,
                        'user_id' => $room->pic_id,
                        'message' => "Pengajuan peminjaman ruangan {$room->room_name} baru dari {$user->name} untuk {$dateSummary}: {$data['title']}.",
                    ]);
                }
            }
        }

        $firstEvent = $createdEvents[0];
        if ($roleName === 'Admin') {
            $message = ($totalRooms > 1 && $totalDays > 1)
                ? "Agenda {$totalRooms} ruangan selama {$totalDays} hari berhasil dibuat dan langsung disetujui."
                : (($totalRooms > 1)
                    ? "Agenda {$totalRooms} ruangan berhasil dibuat dan langsung disetujui."
                    : (($totalDays > 1)
                        ? "Agenda {$totalDays} hari berhasil dibuat dan langsung disetujui."
                        : 'Agenda berhasil dibuat dan langsung disetujui.'));
        } else {
            $message = ($totalRooms > 1 && $totalDays > 1)
                ? "Agenda {$totalRooms} ruangan selama {$totalDays} hari berhasil diajukan dan menunggu persetujuan PIC."
                : (($totalRooms > 1)
                    ? "Agenda {$totalRooms} ruangan berhasil diajukan dan menunggu persetujuan PIC."
                    : (($totalDays > 1)
                        ? "Agenda {$totalDays} hari berhasil diajukan dan menunggu persetujuan PIC."
                        : 'Agenda berhasil diajukan dan menunggu persetujuan PIC.'));
        }

        return $this->sendResponse(
            new EventResource($firstEvent->load(['room.pic', 'user'])),
            $message,
            201,
        );
    }

    /**
     * Get detail of a specific event.
     */
    // Endpoint: GET /api/events/{event}
    // Menampilkan detail satu agenda peminjaman ruangan
    public function show(Request $request, Event $event): JsonResponse
    {
        $user = $request->user();
        $role = $user->role?->role_name;

        // Agenda yang sudah 'Disetujui' boleh dilihat siapa saja; jika belum disetujui, hanya Admin, PIC ruangan tersebut, atau pengaju asli yang boleh melihat
        $canView = $event->status === 'Disetujui'
            || $role === 'Admin'
            || ($role === 'PIC' && $event->room()->where('pic_id', $user->id)->exists())
            || ($event->user_id === $user->id);

        if (! $canView) {
            return $this->sendError('Anda tidak memiliki akses ke detail agenda ini.', null, 403);
        }

        $event->load(['room.pic', 'user', 'approver']);

        return $this->sendResponse(
            new EventResource($event),
            'Detail agenda berhasil dimuat.',
        );
    }

    /**
     * Approve or reject a pending booking (PIC or Admin).
     */
    // Endpoint: PATCH /api/events/{event}/decide
    // Memproses keputusan persetujuan ('Disetujui') atau penolakan ('Ditolak') oleh PIC ruangan atau Admin
    public function decide(Request $request, Event $event): JsonResponse
    {
        $user = $request->user();
        $role = $user->role?->role_name;

        // Pastikan user yang memproses adalah Admin atau PIC yang bertanggung jawab atas ruangan agenda ini
        $isRoomPic = $role === 'PIC' && $event->room()->where('pic_id', $user->id)->exists();
        if ($role !== 'Admin' && ! $isRoomPic) {
            return $this->sendError('Anda bukan PIC dari ruangan agenda ini.', null, 403);
        }

        // Bagi PIC, hanya agenda yang masih berstatus 'Pending' yang boleh diubah keputusannya
        if ($role !== 'Admin' && $event->status !== 'Pending') {
            return $this->sendError('Hanya agenda yang berstatus Pending yang dapat diproses.', null, 422);
        }

        // Validasi input keputusan: jika ditolak ('Ditolak'), alasan penolakan (`rejection_reason`) wajib diisi
        $validator = Validator::make($request->all(), [
            'status' => ['required', 'in:Disetujui,Ditolak'],
            'rejection_reason' => ['required_if:status,Ditolak', 'nullable', 'string', 'max:1000'],
        ], [
            'rejection_reason.required_if' => 'Keterangan alasan penolakan wajib diisi jika status Ditolak.',
            'rejection_reason.max' => 'Keterangan penolakan maksimal 1000 karakter.',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validasi gagal.', $validator->errors(), 422);
        }

        $data = $validator->validated();

        // Jika akan disetujui, cek sekali lagi apakah ada bentrok jadwal dengan agenda lain di ruangan dan waktu yang sama
        if ($data['status'] === 'Disetujui') {
            $eventDate = Carbon::parse($event->event_date)->toDateString();
            if ($this->hasConflict((int) $event->room_id, $eventDate, $event->start_time, $event->end_time, $event->id)) {
                return $this->sendError('Tidak dapat menyetujui karena ada bentrok jadwal dengan agenda lain pada ruangan dan waktu tersebut.', null, 422);
            }
        }

        $reason = $data['status'] === 'Ditolak' ? trim((string) $data['rejection_reason']) : null;

        // Simpan perubahan status, ID pemberi persetujuan (`approved_by`), dan alasan penolakan (jika ditolak)
        $event->update([
            'status' => $data['status'],
            'approved_by' => $data['status'] === 'Disetujui' ? $user->id : null,
            'rejection_reason' => $reason,
        ]);

        // Kirimkan notifikasi hasil keputusan (Disetujui / Ditolak beserta alasannya) kepada pemohon agenda
        if ($data['status'] === 'Ditolak' && ! blank($reason)) {
            Notification::create([
                'event_id' => $event->id,
                'user_id' => $event->user_id,
                'message' => "Pengajuan agenda {$event->title} ditolak. Alasan: {$reason}",
            ]);
        } elseif ($data['status'] === 'Disetujui') {
            Notification::create([
                'event_id' => $event->id,
                'user_id' => $event->user_id,
                'message' => "Pengajuan agenda {$event->title} di {$event->room->room_name} telah disetujui.",
            ]);
        }

        return $this->sendResponse(
            new EventResource($event->fresh(['room.pic', 'user', 'approver'])),
            "Status agenda berhasil diperbarui menjadi {$data['status']}.",
        );
    }

    /**
     * Check if one or multiple rooms are available for the given date(s) and time range.
     */
    // Endpoint: GET /api/events/check-availability
    // Mengecek secara real-time apakah suatu atau seluruh ruangan kosong (available) pada tanggal dan rentang jam tertentu
    public function checkAvailability(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'room_id' => ['nullable'],
            'room_ids' => ['nullable'],
            'event_date' => ['nullable', 'date'],
            'dates' => ['nullable'],
            'end_date' => ['nullable', 'date', 'after_or_equal:event_date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'ignore_event_id' => ['nullable', 'integer'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Parameter tidak valid.', $validator->errors(), 422);
        }

        $data = $validator->validated();

        $roomIds = [];
        if ($request->filled('room_ids')) {
            $raw = is_array($request->input('room_ids'))
                ? $request->input('room_ids')
                : explode(',', (string) $request->input('room_ids'));
            $roomIds = array_values(array_unique(array_filter(array_map('intval', $raw))));
        } elseif ($request->filled('room_id')) {
            $roomIds = [(int) $request->input('room_id')];
        }

        if (empty($roomIds)) {
            return $this->sendError('Ruangan harus dipilih.', null, 422);
        }

        $dates = [];
        if ($request->filled('dates')) {
            $inputDates = is_array($request->input('dates'))
                ? $request->input('dates')
                : explode(',', (string) $request->input('dates'));
            foreach ($inputDates as $d) {
                $trimmed = trim($d);
                if (! empty($trimmed)) {
                    $dates[] = Carbon::parse($trimmed);
                }
            }
            $dates = collect($dates)->unique(fn ($c) => $c->toDateString())->sortBy(fn ($c) => $c->timestamp)->values()->all();
        } elseif (! empty($data['event_date'])) {
            $startDate = Carbon::parse($data['event_date']);
            $endDate = ! empty($data['end_date']) && $data['end_date'] > $data['event_date']
                ? Carbon::parse($data['end_date'])
                : $startDate->copy();

            $cur = $startDate->copy();
            while ($cur->lte($endDate)) {
                $dates[] = $cur->copy();
                $cur->addDay();
            }
        } else {
            return $this->sendError('Tanggal kegiatan harus disertakan.', null, 422);
        }

        $roomsById = Room::whereIn('id', $roomIds)->get()->keyBy('id');
        $conflictRoom = null;
        $conflictDate = null;
        $ignoreId = isset($data['ignore_event_id']) ? (int) $data['ignore_event_id'] : null;

        foreach ($roomIds as $rId) {
            foreach ($dates as $d) {
                if ($this->hasConflict(
                    $rId,
                    $d->toDateString(),
                    $data['start_time'],
                    $data['end_time'],
                    $ignoreId
                )) {
                    $conflictRoom = $roomsById[$rId] ?? Room::find($rId);
                    $conflictDate = $d;
                    break 2;
                }
            }
        }

        $totalDays = count($dates);
        $totalRooms = count($roomIds);
        $primaryDate = $data['event_date'] ?? ($dates[0] ?? null)?->toDateString();
        if ($conflictDate !== null && $conflictRoom !== null) {
            $msg = ($totalRooms > 1 || $totalDays > 1)
                ? "Ruangan {$conflictRoom->room_name} tidak tersedia pada tanggal {$conflictDate->translatedFormat('d M Y')}."
                : 'Ruangan tidak tersedia pada waktu tersebut.';

            return $this->sendResponse([
                'available' => false,
                'room_id' => $conflictRoom->id,
                'room_name' => $conflictRoom->room_name,
                'event_date' => $primaryDate,
                'end_date' => $data['end_date'] ?? null,
                'conflict_date' => $conflictDate->toDateString(),
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'message' => $msg,
            ]);
        }

        $successMsg = ($totalRooms > 1 && $totalDays > 1)
            ? "Semua {$totalRooms} ruangan tersedia untuk seluruh {$totalDays} tanggal yang dipilih."
            : (($totalRooms > 1)
                ? "Semua {$totalRooms} ruangan tersedia."
                : (($totalDays > 1)
                    ? "Ruangan tersedia untuk seluruh {$totalDays} tanggal yang dipilih."
                    : 'Ruangan tersedia.'));

        return $this->sendResponse([
            'available' => true,
            'room_id' => $roomIds[0],
            'room_ids' => $roomIds,
            'total_rooms' => $totalRooms,
            'event_date' => $primaryDate,
            'end_date' => $data['end_date'] ?? null,
            'total_days' => $totalDays,
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'message' => $successMsg,
        ]);
    }

    /**
     * @return list<string>
     */
    // Mengambil daftar jam sesi reguler (di mana `is_free = false`) dalam format array string 'HH:MM'
    private function regularSessionTimes(): array
    {
        return SessionTime::where('is_free', false)
            ->orderBy('time')
            ->get()
            ->map(fn (SessionTime $s) => substr($s->time, 0, 5))
            ->all();
    }

    /**
     * Check if room booking conflicts with another approved event.
     */
    // Logika inti pendeteksi bentrok jadwal (Time Overlap):
    // Dua rentang waktu [startA, endA] dan [startB, endB] beririsan (bentrok) jika `start_time < $end` DAN `end_time > $start`.
    // Agenda Pending dan Ditolak tidak dianggap menghalangi peminjaman baru.
    private function hasConflict(int $roomId, string $date, string $start, string $end, ?int $ignoreId = null): bool
    {
        return Event::where('room_id', $roomId)
            ->whereDate('event_date', $date)
            ->where('status', 'Disetujui')
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();
    }
}
