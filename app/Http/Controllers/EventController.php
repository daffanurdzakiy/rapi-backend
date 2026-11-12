<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Notification;
use App\Models\Room;
use App\Models\SessionTime;
use App\Services\EventCancellationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
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
        $today = now('Asia/Jakarta')->toDateString();

        // Urutkan dari hari ini yang paling teratas dan semakin ke bawah menuju tanggal terdekat yang akan datang
        $query = Event::with(['room', 'user', 'approver', 'cancellationReviewer'])
            ->orderByRaw('CASE WHEN event_date >= ? THEN 0 ELSE 1 END', [$today])
            ->orderBy('event_date', 'asc')
            ->orderBy('start_time', 'asc');

        $role = $user->role?->role_name;
        if ($role === 'PIC') {
            $query->whereHas('room', fn ($q) => $q->where('pic_id', $user->id));
        } elseif ($role !== 'Admin') {
            $query->where('user_id', $user->id);
        }

        $rawEvents = $query->get();

        // Pada tampilan Jadwal Ruangan / Manajemen Agenda:
        // - Agenda yang sudah terlaksana hari ini tetap tampil hingga pukul 23:59, dan hilang setelah ganti hari.
        // - Pengajuan agenda yang ditolak akan hilang dari tampilan setelah kurun waktu 24 jam sejak ditolak.
        // Seluruh data di database dan menu laporan rekap tetap tersimpan utuh.
        $allEvents = $rawEvents->reject(function ($event) {
            return $event->isExpiredFromView();
        })->values();

        // Menghitung jumlah per status (otomatis berdasarkan waktu pelaksanaan agenda)
        $counts = [
            'semua' => $allEvents->count(),
            'sedang_berlangsung' => $allEvents->where('execution_status', 'Sedang Berlangsung')->count(),
            'belum_terlaksana' => $allEvents->where('execution_status', 'Belum Terlaksana')->count(),
            'sudah_terlaksana' => $allEvents->where('execution_status', 'Sudah Terlaksana')->count(),
            'menunggu_persetujuan' => $allEvents->filter(fn ($item) => in_array($item->execution_status, ['Menunggu Persetujuan', 'Pending']))->count(),
            'pending' => $allEvents->filter(fn ($item) => in_array($item->execution_status, ['Menunggu Persetujuan', 'Pending']))->count(),
            'ditolak' => $allEvents->where('status', 'Ditolak')->count(),
            'menunggu_pembatalan' => $allEvents->where('status', 'Menunggu Pembatalan')->count(),
            'dibatalkan' => $allEvents->where('status', 'Dibatalkan')->count(),
        ];

        $filter = $request->query('status', 'semua');
        $statusFilterMap = [
            'sedang_berlangsung' => 'Sedang Berlangsung',
            'belum_terlaksana' => 'Belum Terlaksana',
            'sudah_terlaksana' => 'Sudah Terlaksana',
            'menunggu_persetujuan' => 'Menunggu Persetujuan',
            'pending' => 'Menunggu Persetujuan',
            'ditolak' => 'Ditolak',
            'menunggu_pembatalan' => 'Menunggu Pembatalan',
            'dibatalkan' => 'Dibatalkan',
        ];

        if (isset($statusFilterMap[$filter])) {
            $target = $statusFilterMap[$filter];
            $events = $allEvents->filter(function ($item) use ($target) {
                if ($target === 'Menunggu Persetujuan') {
                    return $item->execution_status === 'Menunggu Persetujuan' || $item->status === 'Pending';
                }
                if ($target === 'Ditolak') {
                    return $item->status === 'Ditolak';
                }
                if (in_array($target, ['Menunggu Pembatalan', 'Dibatalkan'], true)) {
                    return $item->status === $target;
                }

                return $item->execution_status === $target;
            })->values();
        } else {
            $events = $allEvents;
            $filter = 'semua';
        }

        return view('events.index', [
            'events' => $events,
            'counts' => $counts,
            'currentFilter' => $filter,
        ]);
    }

    /** Display the room booking request form. */
    // Menampilkan form pengajuan peminjaman ruangan (hanya untuk Admin, Guru, dan Organisasi).
    public function create(Request $request): View
    {
        $roleName = $request->user()->role?->role_name;
        abort_unless(in_array($roleName, ['Admin', 'Guru', 'Organisasi']), 403);
        $isAdmin = $roleName === 'Admin';

        return view('events.form', [
            'rooms' => Room::orderBy('room_name')->get(),
            'sessions' => $this->regularSessions(),
            // Khusus Admin dapat meminjam mulai hari ini (hari H). Guru dan Organisasi mulai H-1 (besok).
            'firstBookingDate' => $isAdmin ? Carbon::today()->toDateString() : Carbon::tomorrow()->toDateString(),
            'canUseFreeTime' => $isAdmin,
            'isAdmin' => $isAdmin,
        ]);
    }

    /** Save a request after rejecting overlapping room and time ranges (supports single and multi-day). */
    // Menyimpan pengajuan agenda baru setelah memastikan tidak ada jadwal yang bentrok (mendukung 1 hari maupun multi-hari).
    public function store(Request $request): RedirectResponse
    {
        $roleName = $request->user()->role?->role_name;
        abort_unless(in_array($roleName, ['Admin', 'Guru', 'Organisasi']), 403);

        $data = $this->validateEvent($request);

        $roomIds = [];
        if ($request->has('room_ids') && is_array($request->input('room_ids')) && ! empty($request->input('room_ids'))) {
            $roomIds = array_values(array_unique(array_filter(array_map('intval', $request->input('room_ids')))));
        } elseif (! empty($data['room_id'])) {
            $roomIds = [(int) $data['room_id']];
        }

        if (empty($roomIds)) {
            throw ValidationException::withMessages([
                'room_id' => 'Pilih minimal satu ruangan.',
            ]);
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
            // Menentukan rentang tanggal kegiatan (mendukung peminjaman 1 hari maupun multi-hari berturut-turut)
            $startDate = Carbon::parse($data['event_date']);
            $isRange = ($request->input('booking_type') === 'range' || ! empty($data['end_date']))
                && ! empty($data['end_date'])
                && $data['end_date'] > $data['event_date'];

            $endDate = $isRange ? Carbon::parse($data['end_date']) : $startDate->copy();

            if ($endDate->lt($startDate)) {
                throw ValidationException::withMessages([
                    'end_date' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
                ]);
            }

            // Himpun seluruh tanggal dalam rentang peminjaman
            $current = $startDate->copy();
            while ($current->lte($endDate)) {
                $dates[] = $current->copy();
                $current->addDay();
            }
        }

        if (count($dates) > 30) {
            throw ValidationException::withMessages([
                'end_date' => 'Peminjaman ruangan maksimal 30 hari berturut-turut.',
            ]);
        }

        if ($roleName === 'Admin' && $this->isStartTimeBeforeNowOnToday($dates, $data['start_time'])) {
            throw ValidationException::withMessages([
                'start_time' => 'Untuk peminjaman hari ini, jam mulai tidak boleh sebelum waktu sekarang.',
            ]);
        }

        // Mengecek ketersediaan seluruh ruangan (Anti-Bentrok) untuk setiap hari dalam rentang tanggal
        $roomsById = Room::whereIn('id', $roomIds)->get()->keyBy('id');
        foreach ($roomIds as $rId) {
            foreach ($dates as $date) {
                $this->ensureAvailable(
                    $rId,
                    $date->toDateString(),
                    $data['start_time'],
                    $data['end_time']
                );
            }
        }

        $totalDays = count($dates);
        $totalRooms = count($roomIds);
        $status = $roleName === 'Admin' ? 'Disetujui' : 'Pending';
        $approvedBy = $roleName === 'Admin' ? $request->user()->id : null;
        $createdEvents = [];

        DB::transaction(function () use ($roomIds, $roomsById, $dates, $totalDays, $totalRooms, $data, $request, $status, $approvedBy, &$createdEvents) {
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
                        'user_id' => $request->user()->id,
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

        // Kirimkan notifikasi ke PIC masing-masing ruangan jika status masih 'Pending'
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
                        'message' => "Pengajuan peminjaman ruangan {$room->room_name} ({$dateSummary}) dari {$request->user()->name}: {$data['title']}.",
                    ]);
                }
            }
        }

        if ($roleName === 'Admin') {
            if ($totalRooms > 1 && $totalDays > 1) {
                $message = "Agenda berhasil dibuat untuk {$totalRooms} ruangan selama {$totalDays} hari dan langsung disetujui.";
            } elseif ($totalRooms > 1) {
                $message = "Agenda berhasil dibuat untuk {$totalRooms} ruangan dan langsung disetujui.";
            } elseif ($totalDays > 1) {
                $message = "Agenda berhasil dibuat untuk {$totalDays} hari dan langsung disetujui.";
            } else {
                $message = 'Agenda berhasil dibuat dan langsung disetujui.';
            }
        } else {
            if ($totalRooms > 1 && $totalDays > 1) {
                $message = "Agenda berhasil diajukan untuk {$totalRooms} ruangan selama {$totalDays} hari ({$firstDate->translatedFormat('d M')} - {$lastDate->translatedFormat('d M Y')}) dan menunggu persetujuan PIC.";
            } elseif ($totalRooms > 1) {
                $message = "Agenda berhasil diajukan untuk {$totalRooms} ruangan dan menunggu persetujuan PIC.";
            } elseif ($totalDays > 1) {
                $message = "Agenda berhasil diajukan untuk {$totalDays} hari ({$firstDate->translatedFormat('d M')} - {$lastDate->translatedFormat('d M Y')}) dan menunggu persetujuan PIC.";
            } else {
                $message = 'Agenda berhasil diajukan dan menunggu persetujuan.';
            }
        }

        return redirect()->route('events.index')->with('success', $message);
    }

    public function requestCancellation(Request $request, Event $event, EventCancellationService $cancellations): RedirectResponse
    {
        $data = $request->validate([
            'cancellation_reason' => ['required', 'string', 'max:1000'],
        ], [
            'cancellation_reason.required' => 'Alasan pembatalan wajib diisi.',
        ]);

        $cancellations->request($event, $request->user(), trim($data['cancellation_reason']));

        return back()->with('success', 'Permintaan pembatalan berhasil dikirim dan menunggu persetujuan PIC atau Admin.');
    }

    public function decideCancellation(Request $request, Event $event, EventCancellationService $cancellations): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:Dibatalkan,Disetujui'],
            'cancellation_decision_reason' => ['required_if:status,Disetujui', 'nullable', 'string', 'max:1000'],
        ], [
            'cancellation_decision_reason.required_if' => 'Alasan penolakan pembatalan wajib diisi.',
        ]);

        $cancellations->decide(
            $event,
            $request->user(),
            $data['status'],
            $data['cancellation_decision_reason'] ?? null,
        );

        return back()->with(
            'success',
            $data['status'] === 'Dibatalkan'
                ? 'Pembatalan agenda disetujui.'
                : 'Permintaan pembatalan ditolak dan agenda tetap disetujui.',
        );
    }

    // Menampilkan halaman Edit Agenda (khusus Admin untuk memindahkan ruangan atau mengubah jam).
    public function edit(Request $request, Event $event): View
    {
        abort_unless($request->user()->role?->role_name === 'Admin', 403);
        abort_unless(in_array($event->status, ['Pending', 'Disetujui']), 422);
        abort_unless($event->execution_status !== 'Sedang Berlangsung', 422, 'Agenda yang sedang berlangsung tidak dapat dipindahkan.');

        $dateString = Carbon::parse($event->event_date)->toDateString();
        $approvedEventsOnDate = Event::with('room')
            ->whereDate('event_date', $dateString)
            ->whereIn('status', ['Disetujui', 'Menunggu Pembatalan'])
            ->whereKeyNot($event->id)
            ->get(['id', 'room_id', 'title', 'start_time', 'end_time']);

        return view('events.edit', [
            'event' => $event->load(['room', 'user']),
            'rooms' => Room::orderBy('room_name')->get(),
            'sessions' => $this->regularSessions(),
            'approvedEventsOnDate' => $approvedEventsOnDate,
        ]);
    }

    // Menyimpan perubahan ruangan/jam agenda oleh Admin dan mengirim notifikasi ke peminjam.
    public function update(Request $request, Event $event): RedirectResponse
    {
        abort_unless($request->user()->role?->role_name === 'Admin', 403);
        abort_unless(in_array($event->status, ['Pending', 'Disetujui']), 422);
        abort_unless($event->execution_status !== 'Sedang Berlangsung', 422, 'Agenda yang sedang berlangsung tidak dapat dipindahkan.');

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
        abort_unless($event->status !== 'Menunggu Pembatalan', 422, 'Gunakan keputusan permintaan pembatalan untuk agenda ini.');
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

    // Membatalkan agenda aktif dan memberi tahu pemohon (khusus Admin).
    public function destroy(Request $request, Event $event): RedirectResponse
    {
        abort_unless($request->user()->role?->role_name === 'Admin', 403);
        abort_unless(in_array($event->status, ['Pending', 'Disetujui', 'Menunggu Pembatalan']), 422, 'Hanya agenda aktif yang dapat dibatalkan.');

        DB::transaction(function () use ($event): void {
            Notification::create([
                'event_id' => null,
                'user_id' => $event->user_id,
                'message' => "Agenda {$event->title} pada {$event->event_date->format('d M Y')} dibatalkan oleh admin.",
            ]);
            $event->delete();
        });

        return back()->with('success', 'Agenda berhasil dibatalkan dan pemohon telah diberi notifikasi.');
    }

    /** Check if one or multiple rooms are available for the given date(s) and time range (Web AJAX). */
    public function checkAvailability(Request $request): JsonResponse
    {
        $this->useFreeTimeInputs($request);
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
            return response()->json([
                'success' => false,
                'available' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

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
            return response()->json([
                'success' => false,
                'available' => false,
                'message' => 'Pilih minimal satu ruangan untuk divalidasi.',
            ], 422);
        }

        $dates = [];
        if ($request->filled('dates')) {
            $rawDates = is_array($request->input('dates'))
                ? $request->input('dates')
                : explode(',', (string) $request->input('dates'));
            foreach ($rawDates as $d) {
                $trimmed = trim($d);
                if (! empty($trimmed)) {
                    $dates[] = Carbon::parse($trimmed);
                }
            }
            $dates = collect($dates)->unique(fn ($c) => $c->toDateString())->sortBy(fn ($c) => $c->timestamp)->values()->all();
        } elseif ($request->filled('event_date')) {
            $startDate = Carbon::parse($request->input('event_date'));
            $endDate = $request->filled('end_date') && $request->input('end_date') > $request->input('event_date')
                ? Carbon::parse($request->input('end_date'))
                : $startDate->copy();

            $cur = $startDate->copy();
            while ($cur->lte($endDate)) {
                $dates[] = $cur->copy();
                $cur->addDay();
            }
        }

        if (empty($dates)) {
            return response()->json([
                'success' => false,
                'available' => false,
                'message' => 'Tentukan tanggal peminjaman untuk divalidasi.',
            ], 422);
        }

        $roomsById = Room::whereIn('id', $roomIds)->get()->keyBy('id');
        $startTime = $request->input('start_time');
        $endTime = $request->input('end_time');
        $ignoreId = $request->input('ignore_event_id') ? (int) $request->input('ignore_event_id') : null;

        if (
            $request->user()->role?->role_name === 'Admin'
            && $this->isStartTimeBeforeNowOnToday($dates, $startTime)
        ) {
            $message = 'Untuk peminjaman hari ini, jam mulai tidak boleh sebelum waktu sekarang.';

            return response()->json([
                'success' => false,
                'available' => false,
                'message' => $message,
                'errors' => ['start_time' => [$message]],
            ], 422);
        }

        foreach ($roomIds as $rId) {
            $roomName = $roomsById[$rId]->room_name ?? "Ruang #{$rId}";
            foreach ($dates as $date) {
                $dateStr = $date->toDateString();
                $conflict = Event::where('room_id', $rId)
                    ->whereDate('event_date', $dateStr)
                    ->whereIn('status', ['Disetujui', 'Menunggu Pembatalan'])
                    ->where('start_time', '<', $endTime)
                    ->where('end_time', '>', $startTime)
                    ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
                    ->first();

                if ($conflict) {
                    $conflictTime = substr($conflict->start_time, 0, 5).'–'.substr($conflict->end_time, 0, 5);
                    $dateFormatted = $date->translatedFormat('d M Y');

                    return response()->json([
                        'success' => true,
                        'available' => false,
                        'conflict' => true,
                        'conflict_room_id' => $rId,
                        'conflict_room_name' => $roomName,
                        'conflict_date' => $dateStr,
                        'conflict_title' => $conflict->title,
                        'conflict_time' => $conflictTime,
                        'message' => "Ruangan {$roomName} sedang digunakan pada tanggal {$dateFormatted} ({$conflictTime}) oleh \"{$conflict->title}\".",
                    ]);
                }
            }
        }

        $totalRooms = count($roomIds);
        $totalDays = count($dates);
        $msg = ($totalRooms > 1 && $totalDays > 1)
            ? "Semua {$totalRooms} ruangan TERSEDIA untuk seluruh {$totalDays} hari yang dipilih."
            : (($totalRooms > 1)
                ? "Semua {$totalRooms} ruangan TERSEDIA pada waktu tersebut."
                : (($totalDays > 1)
                    ? "Ruangan TERSEDIA untuk seluruh {$totalDays} hari yang dipilih."
                    : 'Ruangan TERSEDIA pada waktu tersebut.'));

        return response()->json([
            'success' => true,
            'available' => true,
            'conflict' => false,
            'total_rooms' => $totalRooms,
            'total_days' => $totalDays,
            'message' => $msg,
        ]);
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
        $isAdmin = $request->user()->role?->role_name === 'Admin';
        $canUseFreeTime = $isAdmin;
        $minDateRule = $isAdmin ? 'after_or_equal:today' : 'after:today';

        $data = $request->validate([
            'room_id' => ['required_without:room_ids', 'nullable', 'exists:rooms,id'],
            'room_ids' => ['required_without:room_id', 'nullable', 'array', 'min:1'],
            'room_ids.*' => ['integer', 'exists:rooms,id'],
            'title' => ['required', 'string', 'max:255'],
            'booking_type' => ['nullable', 'in:single,range,multi'],
            'event_date' => ['required_without:dates', 'nullable', 'date', $minDateRule],
            'dates' => ['nullable', 'array', 'min:1', 'max:30'],
            'dates.*' => ['date', $minDateRule],
            'end_date' => ['nullable', 'date', 'after_or_equal:event_date'],
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

    /** Prevent overlapping bookings only when the existing booking is approved. */
    // Logika Anti-Bentrok Jadwal Ruangan.
    // Hanya agenda 'Disetujui' yang beririsan yang menghalangi penggunaan ruangan.
    private function ensureAvailable(int $roomId, string $date, string $start, string $end, ?int $ignoreId = null): void
    {
        $conflict = Event::with('room')
            ->where('room_id', $roomId)
            ->whereDate('event_date', $date)
            ->whereIn('status', ['Disetujui', 'Menunggu Pembatalan'])
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->first();

        if ($conflict) {
            $conflictTime = substr($conflict->start_time, 0, 5).'–'.substr($conflict->end_time, 0, 5);
            $roomName = $conflict->room?->room_name ?? Room::find($roomId)?->room_name ?? "Ruangan #{$roomId}";
            $dateFormatted = Carbon::parse($date)->translatedFormat('d M Y');
            throw ValidationException::withMessages([
                'room_id' => "Ruangan sudah digunakan pada rentang waktu tersebut ({$dateFormatted}) oleh agenda \"{$conflict->title}\" ({$conflictTime}) di {$roomName}. Silakan pilih ruang atau waktu lain.",
            ]);
        }
    }

    /** Prevent Admin bookings from starting in the past when any selected date is today. */
    private function isStartTimeBeforeNowOnToday(array $dates, string $startTime): bool
    {
        $today = Carbon::today()->toDateString();
        $includesToday = collect($dates)->contains(
            fn ($date) => Carbon::parse($date)->toDateString() === $today,
        );

        return $includesToday && $startTime < now()->format('H:i');
    }
}
