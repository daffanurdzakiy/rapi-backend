<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EventExecutionStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_automatic_execution_status_for_events(): void
    {
        $admin = $this->userWithRole('Admin');
        $guru = $this->userWithRole('Guru');
        $room = Room::create([
            'room_name' => 'Ruang Teater',
            'capacity' => 100,
            'pic_id' => $admin->id,
        ]);

        // 1. Disetujui & Masa Depan -> 'Belum Terlaksana'
        $eventUpcoming = Event::create([
            'user_id' => $guru->id,
            'room_id' => $room->id,
            'title' => 'Seminar Pendidikan Masa Depan',
            'event_date' => Carbon::tomorrow()->toDateString(),
            'start_time' => '08:00:00',
            'end_time' => '11:00:00',
            'status' => 'Disetujui',
            'approved_by' => $admin->id,
        ]);
        $this->assertSame('Belum Terlaksana', $eventUpcoming->execution_status);

        // 2. Disetujui & Sedang Berlangsung Hari Ini -> 'Sedang Berlangsung'
        $eventOngoing = Event::create([
            'user_id' => $guru->id,
            'room_id' => $room->id,
            'title' => 'Workshop Coding Live',
            'event_date' => Carbon::today()->toDateString(),
            'start_time' => Carbon::now()->subMinutes(30)->format('H:i:s'),
            'end_time' => Carbon::now()->addMinutes(30)->format('H:i:s'),
            'status' => 'Disetujui',
            'approved_by' => $admin->id,
        ]);
        $this->assertSame('Sedang Berlangsung', $eventOngoing->execution_status);

        // 3. Disetujui & Waktu Sudah Selesai -> 'Sudah Terlaksana'
        $eventCompleted = Event::create([
            'user_id' => $guru->id,
            'room_id' => $room->id,
            'title' => 'Rapat Kemarin',
            'event_date' => Carbon::yesterday()->toDateString(),
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'status' => 'Disetujui',
            'approved_by' => $admin->id,
        ]);
        $this->assertSame('Sudah Terlaksana', $eventCompleted->execution_status);

        // 4. Ditolak -> 'Ditolak'
        $eventRejected = Event::create([
            'user_id' => $guru->id,
            'room_id' => $room->id,
            'title' => 'Lomba Ditolak',
            'event_date' => Carbon::tomorrow()->toDateString(),
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'status' => 'Ditolak',
            'rejection_reason' => 'Ruangan bentrok jadwal perawatan.',
        ]);
        $this->assertSame('Ditolak', $eventRejected->execution_status);

        // 5. Pending -> 'Pending'
        $eventPending = Event::create([
            'user_id' => $guru->id,
            'room_id' => $room->id,
            'title' => 'Pengajuan Baru Menunggu',
            'event_date' => Carbon::tomorrow()->toDateString(),
            'start_time' => '13:00:00',
            'end_time' => '15:00:00',
            'status' => 'Pending',
        ]);
        $this->assertSame('Pending', $eventPending->execution_status);

        // Cek Tampilan Halaman Pengajuan Ruangan (events.index)
        $this->actingAs($admin)
            ->get(route('events.index'))
            ->assertOk()
            ->assertSee('Sedang Berlangsung')
            ->assertSee('Belum Terlaksana')
            ->assertSee('Sudah Terlaksana')
            ->assertSee('Ditolak')
            ->assertSee('Pending');

        // Cek Filter 'sedang_berlangsung'
        $this->actingAs($admin)
            ->get(route('events.index', ['status' => 'sedang_berlangsung']))
            ->assertOk()
            ->assertSee('Workshop Coding Live')
            ->assertDontSee('Seminar Pendidikan Masa Depan')
            ->assertDontSee('Rapat Kemarin');

        // Cek Filter 'belum_terlaksana'
        $this->actingAs($admin)
            ->get(route('events.index', ['status' => 'belum_terlaksana']))
            ->assertOk()
            ->assertSee('Seminar Pendidikan Masa Depan')
            ->assertDontSee('Workshop Coding Live')
            ->assertDontSee('Rapat Kemarin');

        // Cek Filter 'sudah_terlaksana'
        $this->actingAs($admin)
            ->get(route('events.index', ['status' => 'sudah_terlaksana']))
            ->assertOk()
            ->assertSee('Rapat Kemarin')
            ->assertDontSee('Workshop Coding Live')
            ->assertDontSee('Seminar Pendidikan Masa Depan');
    }

    private function userWithRole(string $roleName): User
    {
        $roleId = DB::table('roles')->where('role_name', $roleName)->value('id')
            ?? DB::table('roles')->insertGetId([
                'role_name' => $roleName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return User::factory()->create(['role_id' => $roleId]);
    }
}
