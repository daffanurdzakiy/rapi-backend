<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\Event;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

// Controller khusus Admin untuk mengelola Data Master Ruangan beserta penugasan PIC-nya.
class RoomController extends Controller
{
    /** Display the master list of rooms with their delegated PIC. */
    // Menampilkan daftar seluruh ruangan beserta fitur pencarian nama ruangan, lokasi, deskripsi, atau nama PIC.
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $rooms = Room::with('pic')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($roomQuery) use ($search): void {
                    $roomQuery->where('room_name', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('pic', fn ($picQuery) => $picQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('room_name')
            ->get();

        return view('rooms.index', compact('rooms', 'search'));
    }

    /** Display a reusable room form for create or edit actions. */
    // Menampilkan form tambah/edit ruangan beserta pilihan daftar user yang memiliki Role PIC.
    public function form(?Room $room = null): View
    {
        return view('rooms.form', ['room' => $room, 'pics' => $this->pics()]);
    }

    /** Create a master room record. */
    // Menyimpan data ruangan baru ke database.
    public function store(Request $request): RedirectResponse
    {
        Room::create($this->validateRoom($request));

        return redirect()->route('rooms.index')->with('success', 'Ruangan berhasil ditambahkan.');
    }

    /** Update a master room record. */
    // Memperbarui data ruangan yang sudah ada.
    public function update(Request $request, Room $room): RedirectResponse
    {
        $room->update($this->validateRoom($request));

        return redirect()->route('rooms.index')->with('success', 'Ruangan berhasil diperbarui.');
    }

    /** Delete a room only when no related booking or complaint is needed anymore. */
    // Menahan penghapusan jika masih ada agenda aktif dan menampilkan agenda yang perlu ditangani admin.
    public function destroy(Room $room): RedirectResponse
    {
        return DB::transaction(function () use ($room): RedirectResponse {
            $room = Room::whereKey($room->id)->lockForUpdate()->firstOrFail();
            $now = now('Asia/Jakarta');
            $today = $now->toDateString();
            $currentTime = $now->format('H:i:s');

            $blockingEvents = Event::with('user')
                ->where('room_id', $room->id)
                ->whereIn('status', ['Pending', 'Disetujui', 'Menunggu Pembatalan'])
                ->where(function ($query) use ($today, $currentTime): void {
                    $query->whereDate('event_date', '>', $today)
                        ->orWhere(function ($todayQuery) use ($today, $currentTime): void {
                            $todayQuery->whereDate('event_date', $today)
                                ->where('end_time', '>=', $currentTime);
                        });
                })
                ->orderBy('event_date')
                ->orderBy('start_time')
                ->get();

            if ($blockingEvents->isNotEmpty()) {
                $details = $blockingEvents->map(fn (Event $event): array => [
                    'id' => $event->id,
                    'title' => $event->title,
                    'user' => $event->user->name,
                    'event_date' => $event->event_date->format('Y-m-d'),
                    'start_time' => substr($event->start_time, 0, 5),
                    'end_time' => substr($event->end_time, 0, 5),
                    'status' => $event->status,
                    'execution_status' => $event->execution_status,
                ])->all();

                return back()
                    ->with('error', 'Ruangan tidak dapat dihapus. Pindahkan atau batalkan agenda aktif berikut terlebih dahulu.')
                    ->with('room_blocking_events', $details);
            }

            if (Event::where('room_id', $room->id)->exists() || Complaint::where('room_id', $room->id)->exists()) {
                return back()->with('error', 'Ruangan tidak dapat dihapus karena masih memiliki riwayat agenda atau pengaduan.');
            }

            $room->delete();

            return back()->with('success', 'Ruangan berhasil dihapus.');
        });
    }

    /** Validate the master-room fields and its PIC assignment. */
    // Validasi input form Ruangan (nama, lokasi, deskripsi, kapasitas, dan ID PIC).
    private function validateRoom(Request $request): array
    {
        return $request->validate(['room_name' => ['required', 'string', 'max:255'], 'location' => ['nullable', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:1000'], 'capacity' => ['required', 'integer', 'min:0'], 'pic_id' => ['required', 'exists:users,id']]);
    }

    /** Fetch only users permitted to become a room PIC. */
    // Mengambil daftar user yang berperan sebagai 'PIC' untuk dropdown penanggung jawab ruangan.
    private function pics()
    {
        return User::whereHas('role', fn ($q) => $q->where('role_name', 'PIC'))->orderBy('name')->get();
    }
}
