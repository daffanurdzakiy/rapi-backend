<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoomManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_room_creation_form_confirms_details_before_submission(): void
    {
        $admin = $this->userWithRole('Admin');
        $this->userWithRole('PIC');

        $this->actingAs($admin)
            ->get(route('rooms.create'))
            ->assertOk()
            ->assertSee('Konfirmasi penambahan ruangan ini?', false)
            ->assertSee('window.confirm(message)', false)
            ->assertSee('Nama ruangan:', false)
            ->assertSee('Lokasi:', false)
            ->assertSee('Kapasitas:', false)
            ->assertSee('PIC:', false)
            ->assertSee('Deskripsi:', false);
    }

    public function test_room_list_has_searchable_expandable_room_cards_with_details_and_actions(): void
    {
        $admin = $this->userWithRole('Admin');
        $pic = $this->userWithRole('PIC');
        $room = Room::create([
            'room_name' => 'Laboratorium IPA',
            'location' => 'Gedung A',
            'description' => 'Laboratorium praktikum',
            'capacity' => 32,
            'pic_id' => $pic->id,
        ]);
        Room::create([
            'room_name' => 'Ruang Musik',
            'location' => 'Gedung B',
            'capacity' => 20,
            'pic_id' => $pic->id,
        ]);

        $this->actingAs($admin)
            ->get(route('rooms.index'))
            ->assertOk()
            ->assertSee('name="q"', false)
            ->assertSee('Tambah Ruangan')
            ->assertSee('class="room-card"', false)
            ->assertSee('<dialog class="room-dialog"', false)
            ->assertSee('dialog.showModal()', false)
            ->assertDontSee('<details class="room-card">', false)
            ->assertSee('Laboratorium IPA')
            ->assertSee('32 orang')
            ->assertSee($pic->name)
            ->assertSee('Gedung A')
            ->assertSee(route('rooms.edit', $room))
            ->assertSee(route('complaints.create', ['room_id' => $room->id]))
            ->assertSee('Pengaduan Kerusakan')
            ->assertSee('Hapus');

        $this->get(route('rooms.index', ['q' => 'Laboratorium IPA']))
            ->assertOk()
            ->assertSee('Laboratorium IPA')
            ->assertDontSee('Ruang Musik');

        $this->get(route('rooms.index', ['q' => $pic->name]))
            ->assertOk()
            ->assertSee('Laboratorium IPA')
            ->assertSee('Ruang Musik');
    }

    public function test_room_deletion_shows_active_agendas_with_move_and_cancel_actions(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 11, 10, 0, 0, 'Asia/Jakarta'));
        $admin = $this->userWithRole('Admin');
        $pic = $this->userWithRole('PIC');
        $borrower = $this->userWithRole('Guru');
        $room = Room::create([
            'room_name' => 'Ruang Rapat',
            'capacity' => 12,
            'pic_id' => $pic->id,
        ]);
        $event = Event::create([
            'user_id' => $borrower->id,
            'room_id' => $room->id,
            'title' => 'Rapat OSIS',
            'event_date' => '2026-11-12',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'status' => 'Pending',
        ]);

        $this->actingAs($admin)
            ->from(route('rooms.index'))
            ->delete(route('rooms.destroy', $room))
            ->assertRedirect(route('rooms.index'))
            ->assertSessionHas('room_blocking_events', fn (array $events): bool => count($events) === 1 && $events[0]['id'] === $event->id);

        $this->get(route('rooms.index'))
            ->assertOk()
            ->assertSee('Agenda masih menggunakan ruangan')
            ->assertSee('Rapat OSIS')
            ->assertSee($borrower->name)
            ->assertSee(route('events.edit', $event))
            ->assertSee(route('events.destroy', $event))
            ->assertSee('Pindahkan')
            ->assertSee('Batalkan');
    }

    public function test_ongoing_agenda_can_be_cancelled_but_not_moved_before_room_deletion(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 11, 10, 0, 0, 'Asia/Jakarta'));
        $admin = $this->userWithRole('Admin');
        $pic = $this->userWithRole('PIC');
        $borrower = $this->userWithRole('Guru');
        $room = Room::create([
            'room_name' => 'Ruang Serbaguna',
            'capacity' => 40,
            'pic_id' => $pic->id,
        ]);
        $event = Event::create([
            'user_id' => $borrower->id,
            'room_id' => $room->id,
            'title' => 'Kegiatan Berlangsung',
            'event_date' => '2026-11-11',
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'status' => 'Disetujui',
            'approved_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->from(route('rooms.index'))
            ->delete(route('rooms.destroy', $room))
            ->assertRedirect(route('rooms.index'));

        $this->get(route('rooms.index'))
            ->assertOk()
            ->assertSee('Kegiatan Berlangsung')
            ->assertSee('Agenda yang sedang berlangsung hanya dapat dibatalkan.')
            ->assertDontSee(route('events.edit', $event));

        $this->get(route('events.edit', $event))->assertStatus(422);

        $this->from(route('rooms.index'))
            ->delete(route('events.destroy', $event))
            ->assertRedirect(route('rooms.index'))
            ->assertSessionHas('success', 'Agenda berhasil dibatalkan dan pemohon telah diberi notifikasi.');

        $this->assertDatabaseMissing('events', ['id' => $event->id]);
        $this->assertDatabaseHas('notifications', [
            'event_id' => null,
            'user_id' => $borrower->id,
            'message' => 'Agenda Kegiatan Berlangsung pada 11 Nov 2026 dibatalkan oleh admin.',
        ]);
        $this->app['auth']->forgetGuards();
        $this->withToken($borrower->createToken('room-cancellation-test')->plainTextToken)
            ->getJson(route('api.notifications.index'))
            ->assertOk()
            ->assertJsonFragment([
                'event_id' => null,
                'message' => 'Agenda Kegiatan Berlangsung pada 11 Nov 2026 dibatalkan oleh admin.',
            ]);

        $this->actingAs($admin);
        $this->delete(route('rooms.destroy', $room))
            ->assertRedirect(route('rooms.index'))
            ->assertSessionHas('success', 'Ruangan berhasil dihapus.');
        $this->assertDatabaseMissing('rooms', ['id' => $room->id]);
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
}
