<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\User;
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
