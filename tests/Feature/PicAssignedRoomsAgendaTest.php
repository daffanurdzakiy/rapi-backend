<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Event;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PicAssignedRoomsAgendaTest extends TestCase
{
    use RefreshDatabase;

    public function test_pic_sees_all_agendas_for_assigned_rooms_only(): void
    {
        $pic = $this->userWithRole('PIC');
        $otherPic = $this->userWithRole('PIC');
        $assignedRoom = $this->roomFor($pic, 'Ruang Laboratorium');
        $otherRoom = $this->roomFor($otherPic, 'Ruang Perpustakaan');
        $requester = $this->userWithRole('Guru');
        Event::create([
            'user_id' => $requester->id,
            'room_id' => $assignedRoom->id,
            'title' => 'Agenda Guru untuk PIC',
            'event_date' => '2026-11-10',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'status' => 'Pending',
        ]);
        Event::create([
            'user_id' => $requester->id,
            'room_id' => $otherRoom->id,
            'title' => 'Agenda Ruangan PIC Lain',
            'event_date' => '2026-11-10',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'status' => 'Pending',
        ]);

        $this->actingAs($pic)
            ->get(route('events.index'))
            ->assertOk()
            ->assertSee('Jadwal Ruangan')
            ->assertSee('Agenda Guru untuk PIC')
            ->assertSee($assignedRoom->room_name)
            ->assertDontSee('Agenda Ruangan PIC Lain')
            ->assertDontSee('Ruang Perpustakaan')
            ->assertDontSee('Setujui')
            ->assertDontSee('Tolak')
            ->assertDontSee(route('events.index', ['status' => 'menunggu_persetujuan']), false)
            ->assertDontSee(route('events.index', ['status' => 'ditolak']), false)
            ->assertDontSee(route('events.index', ['status' => 'menunggu_pembatalan']), false)
            ->assertDontSee('Ajukan Agenda')
            ->assertDontSee('Laporkan Kerusakan Ruangan')
            ->assertDontSee('Edit');
    }

    public function test_pic_schedule_page_does_not_show_facility_complaint_section(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 9, 10, 0, 0, 'Asia/Jakarta'));
        $pic = $this->userWithRole('PIC');
        $room = $this->roomFor($pic, 'Ruang Laboratorium');

        Event::create([
            'user_id' => $pic->id,
            'room_id' => $room->id,
            'title' => 'Praktik jaringan',
            'event_date' => '2026-11-10',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'status' => 'Disetujui',
        ]);
        Complaint::create([
            'room_id' => $room->id,
            'reporter_id' => $pic->id,
            'description' => 'Proyektor tidak menyala',
            'status_repair' => 'Belum Diperbaiki',
        ]);

        $this->actingAs($pic)
            ->get(route('events.index'))
            ->assertOk()
            ->assertSee('Jadwal Ruangan')
            ->assertSee('Praktik jaringan')
            ->assertSee('10 Nov 2026')
            ->assertSee('Disetujui')
            ->assertDontSee('Laporan Pengaduan Fasilitas')
            ->assertDontSee('Proyektor tidak menyala');
    }

    public function test_pic_dashboard_lists_only_rooms_assigned_to_the_pic(): void
    {
        $pic = $this->userWithRole('PIC');
        $otherPic = $this->userWithRole('PIC');
        $assignedRoom = $this->roomFor($pic, 'Ruang Tanggung Jawab PIC');
        $this->roomFor($otherPic, 'Ruang PIC Lain');
        Event::create([
            'user_id' => $this->userWithRole('Guru')->id,
            'room_id' => $assignedRoom->id,
            'title' => 'Agenda Terkini PIC',
            'event_date' => now()->addDay()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '09:00',
            'status' => 'Pending',
        ]);
        Event::create([
            'user_id' => $pic->id,
            'room_id' => $assignedRoom->id,
            'title' => 'Agenda ditolak hari ini',
            'event_date' => today()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'Ditolak',
        ]);

        $this->actingAs($pic)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewIs('dashboard.index')
            ->assertViewHas('todayEvents', 1)
            ->assertSee('Agenda Terkini')
            ->assertSee('Agenda Terkini PIC')
            ->assertSee('class="stat-value"', false)
            ->assertSee('font-size: 72px !important;')
            ->assertSee('font-size: 56px !important;')
            ->assertSee('Daftar Ruangan Tanggung Jawab')
            ->assertSee('RUANGAN TANGGUNG JAWAB')
            ->assertSee('Jumlah agenda pada hari ini')
            ->assertDontSee('Pending dan disetujui')
            ->assertSee($assignedRoom->room_name)
            ->assertDontSee('Ruang PIC Lain')
            ->assertDontSee('Aksi Cepat');
    }

    public function test_legacy_dashboard_view_name_renders_without_new_schedule_data(): void
    {
        $pic = $this->userWithRole('PIC');
        $response = $this->actingAs($pic)
            ->get(route('dashboard'))
            ->assertOk();
        $viewData = $response->viewData();
        unset($viewData['featuredRoomSchedules']);

        $html = view('dashboard.index', $viewData)->render();

        $this->assertStringContainsString('Selamat datang', $html);
    }

    public function test_featured_room_schedules_are_shown_to_all_dashboard_roles(): void
    {
        $pic = $this->userWithRole('PIC');
        $wijayaRoom = $this->roomFor($pic, 'Ruang Wijaya');
        $dakRoom = $this->roomFor($pic, 'Ruang Dak');
        $requester = $this->userWithRole('Guru');

        Event::create([
            'user_id' => $requester->id,
            'room_id' => $wijayaRoom->id,
            'title' => 'Rapat Wijaya Hari Ini',
            'event_date' => today()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '09:30',
            'status' => 'Disetujui',
        ]);
        Event::create([
            'user_id' => $requester->id,
            'room_id' => $dakRoom->id,
            'title' => 'Agenda Ditolak Dak',
            'event_date' => today()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'Ditolak',
        ]);
        Event::create([
            'user_id' => $requester->id,
            'room_id' => $dakRoom->id,
            'title' => 'Agenda Dak Besok',
            'event_date' => today()->addDay()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'Disetujui',
        ]);

        foreach (['Admin', 'PIC', 'Guru', 'Organisasi'] as $roleName) {
            $user = $roleName === 'PIC' ? $pic : $this->userWithRole($roleName);

            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee('Ruang Wijaya')
                ->assertSee('Rapat Wijaya Hari Ini')
                ->assertSee('08:00–09:30')
                ->assertSee('Ruang Dak')
                ->assertSee('KOSONG')
                ->assertSee('Belum ada agenda untuk hari ini.')
                ->assertDontSee('Agenda Ditolak Dak')
                ->assertViewHas('featuredRoomSchedules', fn ($schedules) => $schedules['Wijaya']['events']->pluck('title')->contains('Rapat Wijaya Hari Ini')
                    && $schedules['Dak']['events']->isEmpty()
                );
        }
    }

    public function test_pic_cannot_decide_agendas_and_cannot_edit_or_submit_agendas(): void
    {
        $pic = $this->userWithRole('PIC');
        $otherPic = $this->userWithRole('PIC');
        $requester = $this->userWithRole('Guru');
        $assignedRoom = $this->roomFor($pic, 'Ruang Laboratorium');
        $otherRoom = $this->roomFor($otherPic, 'Ruang Perpustakaan');
        $assignedEvent = Event::create([
            'user_id' => $requester->id,
            'room_id' => $assignedRoom->id,
            'title' => 'Agenda untuk diputuskan PIC',
            'event_date' => '2026-11-10',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'status' => 'Pending',
        ]);
        $approvedEvent = Event::create([
            'user_id' => $requester->id,
            'room_id' => $assignedRoom->id,
            'title' => 'Agenda untuk disetujui PIC',
            'event_date' => '2026-11-11',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'status' => 'Pending',
        ]);
        $otherEvent = Event::create([
            'user_id' => $requester->id,
            'room_id' => $otherRoom->id,
            'title' => 'Agenda PIC lain',
            'event_date' => '2026-11-10',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'status' => 'Pending',
        ]);

        $this->actingAs($pic)
            ->patch(route('events.decide', $assignedEvent), [
                'status' => 'Ditolak',
                'rejection_reason' => 'Ruangan sedang diperbaiki.',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('events', [
            'id' => $assignedEvent->id,
            'status' => 'Pending',
            'rejection_reason' => null,
        ]);

        $this->patch(route('events.decide', $approvedEvent), [
            'status' => 'Disetujui',
        ])->assertForbidden();
        $this->assertDatabaseHas('events', [
            'id' => $approvedEvent->id,
            'status' => 'Pending',
            'approved_by' => null,
        ]);
        $this->get(route('events.index'))
            ->assertOk()
            ->assertSee('Agenda untuk diputuskan PIC')
            ->assertDontSee(route('events.index', ['status' => 'menunggu_persetujuan']), false)
            ->assertDontSee(route('events.index', ['status' => 'ditolak']), false)
            ->assertDontSee(route('events.index', ['status' => 'menunggu_pembatalan']), false)
            ->assertDontSee('Setujui')
            ->assertDontSee('Konfirmasi Tolak');

        $this->patch(route('events.decide', $otherEvent), [
            'status' => 'Disetujui',
        ])->assertForbidden();

        $this->get(route('events.edit', $assignedEvent))->assertForbidden();
        $this->get(route('events.create'))->assertForbidden();
        $this->post(route('events.store'), [])->assertForbidden();
        $this->get(route('complaints.create'))->assertForbidden();
        $this->post(route('complaints.store'), [])->assertForbidden();
        $this->delete(route('events.destroy', $approvedEvent))->assertForbidden();
    }

    public function test_admin_dashboard_shows_recent_complaints_and_actions_under_date_without_aksi_cepat(): void
    {
        $admin = $this->userWithRole('Admin');
        $pic = $this->userWithRole('PIC');
        $reporter = $this->userWithRole('Guru');
        $room = $this->roomFor($pic, 'Ruang Multimedia');

        $olderComplaint = Complaint::create([
            'room_id' => $room->id,
            'reporter_id' => $reporter->id,
            'description' => 'Kerusakan Lama AC',
            'status_repair' => 'Belum Diperbaiki',
        ]);
        $newerComplaint = Complaint::create([
            'room_id' => $room->id,
            'reporter_id' => $reporter->id,
            'description' => 'Kerusakan Baru Proyektor',
            'status_repair' => 'Menunggu Spare Part',
        ]);

        DB::table('complaints')->where('id', $olderComplaint->id)->update(['created_at' => now()->subHours(5)]);
        DB::table('complaints')->where('id', $newerComplaint->id)->update(['created_at' => now()->subMinutes(10)]);

        $response = $this->actingAs($admin)->get(route('dashboard'));
        $response->assertOk()
            ->assertDontSee('Aksi Cepat')
            ->assertSee('Laporan Kerusakan Terbaru')
            ->assertSee('＋ Ajukan agenda')
            ->assertSee('＋ Tambah ruangan')
            ->assertSeeInOrder([
                'Kerusakan Baru Proyektor',
                'Kerusakan Lama AC',
            ]);
    }

    private function userWithRole(string $roleName): User
    {
        $roleId = DB::table('roles')->insertGetId([
            'role_name' => $roleName,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::factory()->create(['role_id' => $roleId]);
    }

    private function roomFor(User $pic, string $name): Room
    {
        return Room::create([
            'room_name' => $name,
            'location' => 'Gedung A',
            'capacity' => 30,
            'pic_id' => $pic->id,
        ]);
    }
}
