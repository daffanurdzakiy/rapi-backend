<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\ComplaintResource;
use App\Http\Resources\EventResource;
use App\Http\Resources\NotificationResource;
use App\Http\Resources\RoomResource;
use App\Models\Complaint;
use App\Models\Event;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// =========================================================================
// API CONTROLLER: DASHBOARD CONTROLLER (RINGKASAN STATISTIK PER ROLE)
// =========================================================================
// Menyediakan angka-angka statistik dan 5 data terbaru (agenda, pengaduan, notifikasi)
// yang disesuaikan secara dinamis dengan Role pengguna yang sedang login.
// Jika ingin mengubah atau mengatur perubahan jumlah data terbaru yang tampil di beranda,
// ubah angka pada `->limit(5)` di bawah ini.
class DashboardController extends ApiController
{
    /**
     * Get role-aware dashboard statistics and recent data.
     */
    // Endpoint: GET /api/dashboard
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user->role?->role_name;

        // Jadwal status ruangan unggulan hari ini (Ruang Wijaya dan Ruang DAK)
        $featuredRoomSchedules = collect(['Wijaya', 'Dak'])->map(function (string $keyword) {
            $room = Room::query()
                ->where('room_name', 'like', "%{$keyword}%")
                ->with(['events' => fn ($query) => $query
                    ->whereDate('event_date', today('Asia/Jakarta'))
                    ->whereIn('status', ['Pending', 'Disetujui'])
                    ->orderBy('start_time')])
                ->first();

            $events = $room?->events ?? collect();

            return [
                'room_id' => $room?->id,
                'keyword' => $keyword,
                'name' => $room?->room_name ?? "Ruang {$keyword}",
                'exists' => $room !== null,
                'capacity' => $room?->capacity,
                'events' => $events->map(fn ($event) => [
                    'id' => $event->id,
                    'title' => $event->title,
                    'start_time' => substr($event->start_time, 0, 5),
                    'end_time' => substr($event->end_time, 0, 5),
                    'status' => $event->status,
                    'execution_status' => $event->execution_status,
                ])->values()->all(),
            ];
        })->values()->all();

        // -----------------------------------------------------------------
        // 1. DASHBOARD KHUSUS ROLE PIC (PENANGGUNG JAWAB RUANGAN)
        // -----------------------------------------------------------------
        if ($role === 'PIC') {
            // Ambil daftar ruangan yang ditugaskan kepada PIC ini
            $assignedRooms = $user->assignedRooms()->orderBy('room_name')->get();
            $assignedRoomIds = $assignedRooms->pluck('id');

            // Hitung jumlah agenda hari ini di ruangan milik PIC
            $todayEventsCount = Event::whereIn('room_id', $assignedRoomIds)
                ->whereDate('event_date', today())
                ->whereIn('status', ['Pending', 'Disetujui'])
                ->count();

            // Hitung jumlah agenda yang masih menunggu persetujuan ('Pending') di ruangan milik PIC
            $pendingEventsCount = Event::whereIn('room_id', $assignedRoomIds)
                ->where('status', 'Pending')
                ->count();

            // Hitung jumlah pengaduan yang berstatus 'Belum Diperbaiki' di ruangan milik PIC
            $pendingComplaintsCount = Complaint::whereIn('room_id', $assignedRoomIds)
                ->where('status_repair', 'Belum Diperbaiki')
                ->count();

            $totalComplaintsCount = Complaint::whereIn('room_id', $assignedRoomIds)->count();

            // Ambil 5 agenda terdekat pada ruangan milik PIC
            $recentEvents = Event::with(['room', 'user'])
                ->whereIn('room_id', $assignedRoomIds)
                ->whereIn('status', ['Pending', 'Disetujui'])
                ->orderBy('event_date')
                ->orderBy('start_time')
                ->limit(5)
                ->get();

            // Ambil 5 pengaduan terbaru pada ruangan milik PIC
            $recentComplaints = Complaint::with(['room', 'reporter'])
                ->whereIn('room_id', $assignedRoomIds)
                ->latest()
                ->limit(5)
                ->get();

            // Ambil 5 notifikasi terbaru untuk PIC
            $notifications = $user->notifications()
                ->where(function ($query) use ($user) {
                    $query->whereNull('complaint_id')
                        ->orWhereHas('complaint.room', fn ($roomQuery) => $roomQuery->where('pic_id', $user->id));
                })
                ->with(['complaint', 'event'])
                ->latest()
                ->limit(5)
                ->get();

            $unreadNotificationsCount = $user->notifications()->where('is_read', false)->count();

            return $this->sendResponse([
                'role' => $role,
                'stats' => [
                    'assigned_rooms_count' => $assignedRooms->count(),
                    'today_events_count' => $todayEventsCount,
                    'pending_events_count' => $pendingEventsCount,
                    'pending_complaints_count' => $pendingComplaintsCount,
                    'total_complaints_count' => $totalComplaintsCount,
                    'unread_notifications_count' => $unreadNotificationsCount,
                ],
                'assigned_rooms' => RoomResource::collection($assignedRooms),
                'featured_room_schedules' => $featuredRoomSchedules,
                'recent_events' => EventResource::collection($recentEvents),
                'recent_complaints' => ComplaintResource::collection($recentComplaints),
                'recent_notifications' => NotificationResource::collection($notifications),
            ], 'Dashboard PIC berhasil dimuat.');
        }

        // -----------------------------------------------------------------
        // 2. DASHBOARD KHUSUS ROLE GURU & ORGANISASI (PEMOHON / PELAPOR)
        // -----------------------------------------------------------------
        if (in_array($role, ['Guru', 'Organisasi'], true)) {
            // Query dasar untuk agenda dan pengaduan yang diajukan sendiri oleh user ini
            $myEventsQuery = Event::where('user_id', $user->id);
            $myComplaintsQuery = Complaint::where('reporter_id', $user->id);

            // Hitung statistik agenda milik sendiri (total, pending, dan disetujui)
            $myEventsCount = (clone $myEventsQuery)->count();
            $myPendingEventsCount = (clone $myEventsQuery)->where('status', 'Pending')->count();
            $myApprovedEventsCount = (clone $myEventsQuery)->where('status', 'Disetujui')->count();

            // Hitung statistik pengaduan milik sendiri
            $myComplaintsCount = (clone $myComplaintsQuery)->count();
            $myPendingComplaintsCount = (clone $myComplaintsQuery)->where('status_repair', 'Belum Diperbaiki')->count();

            // Hitung total seluruh agenda yang disetujui hari ini di sekolah
            $todayEventsCount = Event::whereDate('event_date', today())
                ->where('status', 'Disetujui')
                ->count();

            // Ambil 5 agenda terbaru yang diajukan oleh user ini
            $recentEvents = Event::with(['room', 'approver'])
                ->where('user_id', $user->id)
                ->latest('event_date')
                ->limit(5)
                ->get();

            // Ambil 5 pengaduan terbaru yang dilaporkan oleh user ini
            $recentComplaints = Complaint::with('room')
                ->where('reporter_id', $user->id)
                ->latest()
                ->limit(5)
                ->get();

            // Ambil 5 notifikasi terbaru milik user ini
            $notifications = $user->notifications()
                ->with(['complaint', 'event'])
                ->latest()
                ->limit(5)
                ->get();

            $unreadNotificationsCount = $user->notifications()->where('is_read', false)->count();

            return $this->sendResponse([
                'role' => $role,
                'stats' => [
                    'my_events_count' => $myEventsCount,
                    'my_pending_events_count' => $myPendingEventsCount,
                    'my_approved_events_count' => $myApprovedEventsCount,
                    'my_complaints_count' => $myComplaintsCount,
                    'my_pending_complaints_count' => $myPendingComplaintsCount,
                    'today_approved_events_count' => $todayEventsCount,
                    'unread_notifications_count' => $unreadNotificationsCount,
                ],
                'recent_events' => EventResource::collection($recentEvents),
                'featured_room_schedules' => $featuredRoomSchedules,
                'recent_complaints' => ComplaintResource::collection($recentComplaints),
                'recent_notifications' => NotificationResource::collection($notifications),
            ], "Dashboard {$role} berhasil dimuat.");
        }

        // -----------------------------------------------------------------
        // 3. DASHBOARD FALLBACK UNTUK ROLE ADMIN
        // -----------------------------------------------------------------
        // Admin fallback
        return $this->sendResponse([
            'role' => $role,
            'featured_room_schedules' => $featuredRoomSchedules,
            'stats' => [
                'total_rooms' => Room::count(),
                'today_events' => Event::whereDate('event_date', today())->whereIn('status', ['Pending', 'Disetujui'])->count(),
                'pending_events' => Event::where('status', 'Pending')->count(),
                'pending_complaints' => Complaint::where('status_repair', 'Belum Diperbaiki')->count(),
                'unread_notifications_count' => $user->notifications()->where('is_read', false)->count(),
            ],
        ], 'Dashboard Admin berhasil dimuat.');
    }
}

