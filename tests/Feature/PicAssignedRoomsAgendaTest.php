<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Event;
use App\Models\Room;
use App\Models\User;
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
            ->assertSee('Setujui')
            ->assertSee('Tolak')
            ->assertDontSee('Ajukan Agenda')
            ->assertDontSee('Laporkan Kerusakan Ruangan')
            ->assertDontSee('Edit');
    }

    public function test_pic_schedule_page_does_not_show_facility_complaint_section(): void
    {
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

        $this->actingAs($pic)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Agenda Terkini')
            ->assertSee('Agenda Terkini PIC')
            ->assertSee('Daftar Ruangan Tanggung Jawab')
            ->assertSee('RUANGAN TANGGUNG JAWAB')
            ->assertSee($assignedRoom->room_name)
            ->assertDontSee('Ruang PIC Lain')
            ->assertDontSee('Aksi Cepat');
    }

    public function test_pic_can_decide_agenda_for_assigned_room_but_cannot_edit_or_submit_agendas(): void
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
            ->assertRedirect();

        $this->assertDatabaseHas('events', [
            'id' => $assignedEvent->id,
            'status' => 'Ditolak',
            'rejection_reason' => 'Ruangan sedang diperbaiki.',
        ]);

        $this->patch(route('events.decide', $approvedEvent), [
            'status' => 'Disetujui',
        ])->assertRedirect();
        $this->assertDatabaseHas('events', [
            'id' => $approvedEvent->id,
            'status' => 'Disetujui',
            'approved_by' => $pic->id,
        ]);
        $this->get(route('events.index'))
            ->assertOk()
            ->assertSee('Disetujui oleh: '.$pic->name.' (PIC)');

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
