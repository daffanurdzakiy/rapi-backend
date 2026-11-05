<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\Event;
use App\Models\Room;
use Illuminate\View\View;

// Controller untuk halaman Dashboard utama setelah user Login di Web.
class DashboardController extends Controller
{
    /** Render a role-aware summary using the existing application data. */
    // Menampilkan ringkasan statistik, notifikasi terbaru, dan agenda terdekat sesuai Role pengguna.
    public function index(): View
    {
        $user = request()->user();
        $role = $user->role?->role_name;
        // Jika user adalah PIC, ambil daftar ruangan yang ditugaskan kepadanya.
        $assignedRooms = $role === 'PIC'
            ? $user->assignedRooms()->orderBy('room_name')->get()
            : collect();
        $complaints = Complaint::query();
        if ($role === 'PIC') {
            $complaints->whereHas('room', fn ($q) => $q->where('pic_id', $user->id));
        }

        // Mengambil 5 notifikasi terbaru untuk ditampilkan di Dashboard (limit(5)).
        $notifications = match ($role) {
            'PIC' => $user->notifications()
                ->where(function ($query) use ($user) {
                    $query->whereNull('complaint_id')
                        ->orWhereHas('complaint.room', fn ($roomQuery) => $roomQuery->where('pic_id', $user->id));
                })
                ->with('complaint')
                ->latest()
                ->limit(5)
                ->get(),
            'Guru', 'Organisasi' => $user->notifications()
                ->with(['complaint', 'event'])
                ->latest()
                ->limit(5)
                ->get(),
            default => collect(),
        };

        // Mengambil 5 agenda peminjaman ruangan yang berstatus Pending atau Disetujui (tidak menampilkan yang sudah selesai lebih dari 2 hari).
        $recentEvents = Event::with(['room', 'user'])
            ->whereIn('status', ['Pending', 'Disetujui'])
            ->when(
                $role === 'PIC',
                fn ($query) => $query->whereHas('room', fn ($roomQuery) => $roomQuery->where('pic_id', $user->id)),
            )
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->get()
            ->reject(fn ($event) => $event->isCompletedExpired(48))
            ->take(5);

        $featuredRoomSchedules = collect(['Wijaya', 'Dak'])->mapWithKeys(function (string $roomName) {
            $room = Room::query()
                ->where('room_name', 'like', "%{$roomName}%")
                ->with(['events' => fn ($query) => $query
                    ->whereDate('event_date', today())
                    ->whereIn('status', ['Pending', 'Disetujui'])
                    ->orderBy('start_time')])
                ->first();

            return [$roomName => [
                'name' => $room?->room_name ?? "Ruang {$roomName}",
                'exists' => $room !== null,
                'events' => $room?->events ?? collect(),
            ]];
        });

        return view('dashboard', [
            'roomCount' => $role === 'PIC' ? $assignedRooms->count() : Room::count(),
            'assignedRooms' => $assignedRooms,
            'featuredRoomSchedules' => $featuredRoomSchedules,
            'todayEvents' => Event::whereDate('event_date', today())->count(),
            'pendingComplaints' => (clone $complaints)->where('status_repair', 'Belum Diperbaiki')->count(),
            'recentEvents' => $recentEvents,
            'notifications' => $notifications,
        ]);
    }
}
