<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Event;
use App\Models\Notification;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WebNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_and_complaint_notifications_appear_structured_for_pic_and_admin(): void
    {
        Storage::fake('public');

        $admin = $this->userWithRole('Admin');
        $pic = $this->userWithRole('PIC');
        $guru = $this->userWithRole('Guru');
        $room = Room::create([
            'room_name' => 'Lab Multimedia',
            'capacity' => 30,
            'pic_id' => $pic->id,
        ]);

        // 1. Guru mengajukan peminjaman ruang -> PIC & Admin menerima notifikasi
        $this->actingAs($guru)
            ->post(route('events.store'), [
                'room_id' => $room->id,
                'title' => 'Workshop Desain Grafis',
                'event_date' => now()->addDays(2)->toDateString(),
                'start_time' => '08:00',
                'end_time' => '09:30',
            ])
            ->assertRedirect(route('events.index'));

        $event = Event::query()->firstOrFail();
        $this->assertDatabaseHas('notifications', [
            'event_id' => $event->id,
            'user_id' => $pic->id,
            'is_read' => false,
        ]);
        $this->assertDatabaseHas('notifications', [
            'event_id' => $event->id,
            'user_id' => $admin->id,
            'is_read' => false,
        ]);

        // 2. Guru mengirim pengaduan fasilitas -> PIC & Admin menerima notifikasi
        $this->actingAs($guru)
            ->post(route('complaints.store'), [
                'room_id' => $room->id,
                'description' => 'Proyektor berkedip dan buram',
                'photo' => UploadedFile::fake()->image('proyektor.jpg', 10, 10),
            ])
            ->assertRedirect(route('complaints.index'));

        $complaint = Complaint::query()->firstOrFail();
        $this->assertDatabaseHas('notifications', [
            'complaint_id' => $complaint->id,
            'user_id' => $pic->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'complaint_id' => $complaint->id,
            'user_id' => $admin->id,
        ]);

        // 3. Halaman Pusat Notifikasi dan Feed Dropdown menampilkan metadata terstruktur untuk Admin
        $this->actingAs($admin)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Pusat Notifikasi')
            ->assertSee('Peminjaman Ruang')
            ->assertSee('Pengaduan Ruang')
            ->assertSee('Permohonan Peminjaman Ruangan Baru')
            ->assertSee('Laporan Kerusakan Fasilitas Baru')
            ->assertSee('Lab Multimedia')
            ->assertSee('Workshop Desain Grafis')
            ->assertSee('Proyektor berkedip dan buram');

        $this->actingAs($admin)
            ->getJson(route('notifications.feed'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('unread_count', 2)
            ->assertJsonCount(2, 'items');
    }

    public function test_admin_deciding_booking_notifies_applicant_and_room_pic_without_duplicating_actor(): void
    {
        $admin = $this->userWithRole('Admin');
        $pic = $this->userWithRole('PIC');
        $guru = $this->userWithRole('Guru');
        $room = Room::create([
            'room_name' => 'Ruang Rapat Guru',
            'capacity' => 25,
            'pic_id' => $pic->id,
        ]);
        $event = Event::create([
            'user_id' => $guru->id,
            'room_id' => $room->id,
            'title' => 'Koordinasi Kurikulum',
            'event_date' => now()->addDays(2)->toDateString(),
            'start_time' => '08:00',
            'end_time' => '09:30',
            'status' => 'Pending',
        ]);

        $this->actingAs($admin)
            ->patch(route('events.decide', $event), [
                'status' => 'Disetujui',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'event_id' => $event->id,
            'user_id' => $guru->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'event_id' => $event->id,
            'user_id' => $pic->id,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'event_id' => $event->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_opening_notification_marks_read_and_redirects_to_target_with_highlight(): void
    {
        $pic = $this->userWithRole('PIC');
        $guru = $this->userWithRole('Guru');
        $room = Room::create([
            'room_name' => 'Aula Sekolah',
            'capacity' => 100,
            'pic_id' => $pic->id,
        ]);

        $event = Event::create([
            'user_id' => $guru->id,
            'room_id' => $room->id,
            'title' => 'Rapat OSIS',
            'event_date' => now()->addDays(3)->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:30',
            'status' => 'Pending',
        ]);

        $notification = Notification::create([
            'event_id' => $event->id,
            'user_id' => $pic->id,
            'message' => 'Pengajuan peminjaman ruangan Aula Sekolah dari Guru: Rapat OSIS.',
            'is_read' => false,
        ]);

        $expectedUrl = route('events.index', [
            'status' => 'menunggu_persetujuan',
            'highlight' => $event->id,
        ]).'#event-'.$event->id;

        $this->actingAs($pic)
            ->get(route('notifications.open', $notification))
            ->assertRedirect($expectedUrl);

        $this->assertTrue($notification->fresh()->is_read);

        // Pastikan halaman events.index merender anchor dan class highlight
        $this->actingAs($pic)
            ->get(route('events.index', ['status' => 'menunggu_persetujuan', 'highlight' => $event->id]))
            ->assertOk()
            ->assertSee('id="event-'.$event->id.'"', false)
            ->assertSee('highlight-target', false);
    }

    public function test_users_can_filter_and_mark_notifications_read_while_other_users_are_blocked(): void
    {
        $guru = $this->userWithRole('Guru');
        $otherUser = $this->userWithRole('Guru');
        $pic = $this->userWithRole('PIC');
        $room = Room::create([
            'room_name' => 'Ruang Teori 1',
            'capacity' => 30,
            'pic_id' => $pic->id,
        ]);

        $event = Event::create([
            'user_id' => $guru->id,
            'room_id' => $room->id,
            'title' => 'Ujian Praktik',
            'event_date' => now()->addDays(2)->toDateString(),
            'start_time' => '08:00',
            'end_time' => '09:30',
            'status' => 'Ditolak',
            'rejection_reason' => 'Ruangan dipakai rapat komite.',
        ]);

        $complaint = Complaint::create([
            'room_id' => $room->id,
            'reporter_id' => $guru->id,
            'description' => 'Stopkontak rusak',
            'status_repair' => 'Sudah Diperbaiki',
            'repair_note' => 'Stopkontak sudah diganti baru.',
        ]);

        $bookingNotif = Notification::create([
            'event_id' => $event->id,
            'user_id' => $guru->id,
            'message' => 'Pengajuan agenda Ujian Praktik ditolak. Alasan: Ruangan dipakai rapat komite.',
            'is_read' => false,
        ]);

        $complaintNotif = Notification::create([
            'complaint_id' => $complaint->id,
            'user_id' => $guru->id,
            'message' => 'Status perbaikan pengaduan di Ruang Teori 1 diperbarui menjadi: Sudah Diperbaiki. Keterangan: Stopkontak sudah diganti baru.',
            'is_read' => false,
        ]);

        // Filter khusus peminjaman ruang
        $this->actingAs($guru)
            ->get(route('notifications.index', ['filter' => 'booking']))
            ->assertOk()
            ->assertSee('Pengajuan Peminjaman Ditolak')
            ->assertSee('Alasan Penolakan')
            ->assertSee('Ruangan dipakai rapat komite.')
            ->assertDontSee('Perbaikan Fasilitas Telah Selesai');

        // Filter khusus pengaduan ruang
        $this->actingAs($guru)
            ->get(route('notifications.index', ['filter' => 'complaint']))
            ->assertOk()
            ->assertSee('Perbaikan Fasilitas Telah Selesai')
            ->assertSee('Keterangan Perbaikan')
            ->assertSee('Stopkontak sudah diganti baru.')
            ->assertDontSee('Pengajuan Peminjaman Ditolak');

        // User lain tidak boleh mengakses/menandai notifikasi milik $guru
        $this->actingAs($otherUser)
            ->patchJson(route('notifications.read', $bookingNotif))
            ->assertNotFound();

        $this->actingAs($otherUser)
            ->get(route('notifications.open', $bookingNotif))
            ->assertNotFound();

        // Guru menandai 1 dibaca via JSON, lalu semua dibaca
        $this->actingAs($guru)
            ->patchJson(route('notifications.read', $bookingNotif))
            ->assertOk()
            ->assertJsonPath('unread_count', 1);

        $this->assertTrue($bookingNotif->fresh()->is_read);
        $this->assertFalse($complaintNotif->fresh()->is_read);

        $this->actingAs($guru)
            ->postJson(route('notifications.read-all'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertTrue($complaintNotif->fresh()->is_read);
    }

    public function test_reassigned_pic_no_longer_sees_old_room_complaint_notification_in_notification_center(): void
    {
        $oldPic = $this->userWithRole('PIC');
        $newPic = $this->userWithRole('PIC');
        $guru = $this->userWithRole('Guru');
        $room = Room::create([
            'room_name' => 'Ruang Musik',
            'capacity' => 20,
            'pic_id' => $oldPic->id,
        ]);
        $complaint = Complaint::create([
            'room_id' => $room->id,
            'reporter_id' => $guru->id,
            'description' => 'Speaker ruang musik mati',
        ]);
        $notif = Notification::create([
            'complaint_id' => $complaint->id,
            'user_id' => $oldPic->id,
            'message' => 'Pengaduan baru di Ruang Musik dari Guru: Speaker ruang musik mati',
            'is_read' => false,
        ]);

        $room->update(['pic_id' => $newPic->id]);

        $this->actingAs($oldPic)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertDontSee('Speaker ruang musik mati');

        $this->actingAs($oldPic)
            ->getJson(route('notifications.feed'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0)
            ->assertJsonCount(0, 'items');

        $this->actingAs($oldPic)
            ->get(route('notifications.open', $notif))
            ->assertNotFound();
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
