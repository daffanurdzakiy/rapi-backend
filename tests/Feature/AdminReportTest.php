<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_weekly_room_and_daily_reports_for_selected_period(): void
    {
        $admin = $this->userWithRole('Admin');
        $pic = $this->userWithRole('PIC');
        $reporter = $this->userWithRole('Guru');
        $room = Room::create([
            'room_name' => 'Ruang Laporan',
            'capacity' => 20,
            'pic_id' => $pic->id,
        ]);

        $weekStart = now()->startOfWeek();
        Event::create([
            'user_id' => $reporter->id,
            'room_id' => $room->id,
            'title' => 'Agenda Mingguan',
            'event_date' => $weekStart->copy()->addDays(2)->toDateString(),
            'start_time' => '09:30',
            'end_time' => '10:15',
            'status' => 'Disetujui',
        ]);
        Event::create([
            'user_id' => $reporter->id,
            'room_id' => $room->id,
            'title' => 'Agenda Di Luar Periode',
            'event_date' => $weekStart->copy()->subDay()->toDateString(),
            'start_time' => '09:30',
            'end_time' => '10:15',
            'status' => 'Disetujui',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.reports.index', [
                'periode' => 'mingguan',
                'tanggal' => $weekStart->copy()->addDays(2)->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Laporan Mingguan')
            ->assertDontSee('report-section-title">Laporan Harian')
            ->assertDontSee('report-section-title">Laporan Bulanan')
            ->assertSee('Rincian Jadwal')
            ->assertSee('Laporan Mingguan yang dipilih:')
            ->assertSee('Agenda Mingguan')
            ->assertDontSee('Agenda Di Luar Periode')
            ->assertDontSee('Laporan Pengaduan Kerusakan')
            ->assertSee('Cetak / Simpan PDF');
    }

    public function test_admin_must_select_a_period_before_report_is_displayed(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('Pilih jenis periode')
            ->assertSee('Harian')
            ->assertSee('Mingguan')
            ->assertSee('Bulanan')
            ->assertDontSee('Laporan Harian')
            ->assertDontSee('Laporan Jadwal Ruangan')
            ->assertDontSee('Cetak / Simpan PDF');
    }

    public function test_admin_can_view_a_daily_report_for_the_selected_date_only(): void
    {
        $admin = $this->userWithRole('Admin');
        $pic = $this->userWithRole('PIC');
        $reporter = $this->userWithRole('Guru');
        $room = Room::create([
            'room_name' => 'Ruang Laporan Harian',
            'capacity' => 20,
            'pic_id' => $pic->id,
        ]);
        $selectedDate = now()->toDateString();

        Event::create([
            'user_id' => $reporter->id,
            'room_id' => $room->id,
            'title' => 'Agenda Harian',
            'event_date' => $selectedDate,
            'start_time' => '09:30',
            'end_time' => '10:15',
            'status' => 'Disetujui',
        ]);
        Event::create([
            'user_id' => $reporter->id,
            'room_id' => $room->id,
            'title' => 'Agenda Di Luar Hari',
            'event_date' => now()->addMonth()->startOfMonth()->toDateString(),
            'start_time' => '09:30',
            'end_time' => '10:15',
            'status' => 'Disetujui',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.reports.index', [
                'periode' => 'bulanan',
                'tanggal' => $selectedDate,
            ]))
            ->assertOk()
            ->assertSee('Laporan Bulanan')
            ->assertDontSee('report-section-title">Laporan Harian')
            ->assertDontSee('report-section-title">Laporan Mingguan')
            ->assertSee('Laporan Bulanan yang dipilih:')
            ->assertSee('Agenda Harian')
            ->assertDontSee('Agenda Di Luar Hari');

        $this->actingAs($admin)
            ->get(route('admin.reports.index', [
                'periode' => 'harian',
                'tanggal' => $selectedDate,
            ]))
            ->assertOk()
            ->assertSee('Laporan Harian')
            ->assertSee('Laporan Harian yang dipilih:')
            ->assertSee('Agenda Harian')
            ->assertDontSee('Agenda Di Luar Hari')
            ->assertSee('Ruang Laporan Harian');
    }

    public function test_non_admin_cannot_access_admin_reports(): void
    {
        $user = $this->userWithRole('Guru');

        $this->actingAs($user)
            ->get(route('admin.reports.index'))
            ->assertForbidden();
    }

    private function userWithRole(string $name): User
    {
        $roleId = DB::table('roles')->insertGetId([
            'role_name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::factory()->create(['role_id' => $roleId]);
    }
}
