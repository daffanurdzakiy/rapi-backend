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

        // 1. Disetujui & Besok -> 'Belum Terlaksana'
        $eventUpcomingTomorrow = Event::create([
            'user_id' => $guru->id,
            'room_id' => $room->id,
            'title' => 'Seminar Besok',
            'event_date' => Carbon::tomorrow()->toDateString(),
            'start_time' => '08:00:00',
            'end_time' => '11:00:00',
            'status' => 'Disetujui',
            'approved_by' => $admin->id,
        ]);
        $this->assertSame('Belum Terlaksana', $eventUpcomingTomorrow->execution_status);

        // 1b. Disetujui & Lusa -> 'Belum Terlaksana' (Untuk verifikasi urutan ke bawah)
        $eventUpcomingLusa = Event::create([
            'user_id' => $guru->id,
            'room_id' => $room->id,
            'title' => 'Lomba Lusa',
            'event_date' => Carbon::today()->addDays(2)->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'status' => 'Disetujui',
            'approved_by' => $admin->id,
        ]);

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

        // 3. Disetujui & Selesai Hari Ini -> 'Sudah Terlaksana' (Masih tampil hingga 23:59)
        $eventCompletedToday = Event::create([
            'user_id' => $guru->id,
            'room_id' => $room->id,
            'title' => 'Rapat Pagi Tadi',
            'event_date' => Carbon::today()->toDateString(),
            'start_time' => Carbon::now()->subHours(3)->format('H:i:s'),
            'end_time' => Carbon::now()->subHours(1)->format('H:i:s'),
            'status' => 'Disetujui',
            'approved_by' => $admin->id,
        ]);
        $this->assertSame('Sudah Terlaksana', $eventCompletedToday->execution_status);
        $this->assertFalse($eventCompletedToday->isCompletedExpired());

        // 3b. Disetujui & Selesai Kemarin -> Sudah ganti hari, otomatis HILANG setelah jam 23:59
        $eventExpiredYesterday = Event::create([
            'user_id' => $guru->id,
            'room_id' => $room->id,
            'title' => 'Agenda Kemarin Sudah Lewat',
            'event_date' => Carbon::yesterday()->toDateString(),
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'status' => 'Disetujui',
            'approved_by' => $admin->id,
        ]);
        $this->assertSame('Sudah Terlaksana', $eventExpiredYesterday->execution_status);
        $this->assertTrue($eventExpiredYesterday->isCompletedExpired());

        // 4. Ditolak Baru Saja (< 24 Jam) -> Tetap tampil di Jadwal Ruangan
        $eventRejected = Event::create([
            'user_id' => $guru->id,
            'room_id' => $room->id,
            'title' => 'Lomba Ditolak Baru',
            'event_date' => Carbon::tomorrow()->toDateString(),
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'status' => 'Ditolak',
            'rejection_reason' => 'Ruangan bentrok jadwal perawatan.',
        ]);
        $this->assertSame('Ditolak', $eventRejected->execution_status);
        $this->assertFalse($eventRejected->isRejectedExpired());

        // 4b. Ditolak Lebih Dari 24 Jam Lalu -> Otomatis Hilang dari Jadwal Ruangan
        $eventRejectedExpired = Event::create([
            'user_id' => $guru->id,
            'room_id' => $room->id,
            'title' => 'Pengajuan Ditolak 2 Hari Lalu',
            'event_date' => Carbon::tomorrow()->toDateString(),
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'status' => 'Ditolak',
            'rejection_reason' => 'Alasan penolakan lama.',
        ]);
        $eventRejectedExpired->updated_at = Carbon::now()->subHours(26);
        $eventRejectedExpired->save(['timestamps' => false]);
        $this->assertSame('Ditolak', $eventRejectedExpired->execution_status);
        $this->assertTrue($eventRejectedExpired->isRejectedExpired());

        // 5. Pending -> 'Menunggu Persetujuan'
        $eventPending = Event::create([
            'user_id' => $guru->id,
            'room_id' => $room->id,
            'title' => 'Pengajuan Baru Menunggu',
            'event_date' => Carbon::tomorrow()->toDateString(),
            'start_time' => '13:00:00',
            'end_time' => '15:00:00',
            'status' => 'Pending',
        ]);
        $this->assertSame('Menunggu Persetujuan', $eventPending->execution_status);

        // Cek Tampilan Halaman Pengajuan Ruangan (events.index) & Pengurutan
        $response = $this->actingAs($admin)->get(route('events.index'))->assertOk();
        $response->assertSee('Sedang Berlangsung')
            ->assertSee('Belum Terlaksana')
            ->assertSee('Sudah Terlaksana')
            ->assertSee('Ditolak')
            ->assertSee('Menunggu Persetujuan')
            ->assertSee('Rapat Pagi Tadi')
            ->assertDontSee('Agenda Kemarin Sudah Lewat');

        // Verifikasi Urutan: Hari ini paling teratas -> Menuju tanggal terdekat yang akan datang (Besok -> Lusa)
        $html = $response->getContent();
        $posToday = strpos($html, 'Workshop Coding Live');
        $posTomorrow = strpos($html, 'Seminar Besok');
        $posLusa = strpos($html, 'Lomba Lusa');

        $this->assertNotFalse($posToday, 'Agenda hari ini harus muncul');
        $this->assertNotFalse($posTomorrow, 'Agenda besok harus muncul');
        $this->assertNotFalse($posLusa, 'Agenda lusa harus muncul');
        $this->assertTrue($posToday < $posTomorrow, 'Agenda hari ini harus berada di atas agenda besok');
        $this->assertTrue($posTomorrow < $posLusa, 'Agenda besok harus berada di atas agenda lusa');

        // Cek Filter 'menunggu_persetujuan'
        $this->actingAs($admin)
            ->get(route('events.index', ['status' => 'menunggu_persetujuan']))
            ->assertOk()
            ->assertSee('Pengajuan Baru Menunggu')
            ->assertDontSee('Workshop Coding Live')
            ->assertDontSee('Seminar Besok');

        // Cek Filter alias 'pending'
        $this->actingAs($admin)
            ->get(route('events.index', ['status' => 'pending']))
            ->assertOk()
            ->assertSee('Pengajuan Baru Menunggu');

        // Cek Filter 'sedang_berlangsung'
        $this->actingAs($admin)
            ->get(route('events.index', ['status' => 'sedang_berlangsung']))
            ->assertOk()
            ->assertSee('Workshop Coding Live')
            ->assertDontSee('Seminar Besok')
            ->assertDontSee('Rapat Pagi Tadi');

        // Cek Filter 'belum_terlaksana'
        $this->actingAs($admin)
            ->get(route('events.index', ['status' => 'belum_terlaksana']))
            ->assertOk()
            ->assertSee('Seminar Besok')
            ->assertSee('Lomba Lusa')
            ->assertDontSee('Workshop Coding Live')
            ->assertDontSee('Rapat Pagi Tadi');

        // Cek Filter 'sudah_terlaksana' (hanya menampilkan yang hari ini, yang kemarin otomatis hilang setelah 23:59)
        $this->actingAs($admin)
            ->get(route('events.index', ['status' => 'sudah_terlaksana']))
            ->assertOk()
            ->assertSee('Rapat Pagi Tadi')
            ->assertDontSee('Agenda Kemarin Sudah Lewat')
            ->assertDontSee('Workshop Coding Live')
            ->assertDontSee('Seminar Besok');

        // Cek Filter 'ditolak' (menampilkan yang ditolak baru < 24 jam, dan menyembunyikan yang > 24 jam)
        $this->actingAs($admin)
            ->get(route('events.index', ['status' => 'ditolak']))
            ->assertOk()
            ->assertSee('Lomba Ditolak Baru')
            ->assertDontSee('Pengajuan Ditolak 2 Hari Lalu')
            ->assertDontSee('Workshop Coding Live');

        // Pastikan agenda kemarin dan agenda ditolak lama tetap ada di Database dan Menu Laporan Admin
        $this->assertDatabaseHas('events', [
            'id' => $eventExpiredYesterday->id,
            'title' => 'Agenda Kemarin Sudah Lewat',
        ]);
        $this->assertDatabaseHas('events', [
            'id' => $eventRejectedExpired->id,
            'title' => 'Pengajuan Ditolak 2 Hari Lalu',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.reports.index', [
                'periode' => 'bulanan',
                'tanggal' => Carbon::today()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Agenda Kemarin Sudah Lewat')
            ->assertSee('Pengajuan Ditolak 2 Hari Lalu');
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

