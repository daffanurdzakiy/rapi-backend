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

class ComplaintAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_complaint_form_but_submission_still_requires_valid_data(): void
    {
        $admin = $this->userWithRole('Admin');
        $this->actingAs($admin);

        $this->get(route('complaints.create'))
            ->assertOk()
            ->assertSee('Batal')
            ->assertSee(route('complaints.index'));
        $this->from(route('complaints.create'))
            ->post(route('complaints.store'), [])
            ->assertRedirect(route('complaints.create'))
            ->assertSessionHasErrors(['room_id', 'description', 'photo']);
        $this->assertDatabaseCount('complaints', 0);
    }

    public function test_admin_can_report_a_specific_room_from_room_management(): void
    {
        Storage::fake('public');

        $admin = $this->userWithRole('Admin');
        $pic = $this->userWithRole('PIC');
        $room = Room::create([
            'room_name' => 'Ruang Rapat',
            'capacity' => 20,
            'pic_id' => $pic->id,
        ]);

        $this->actingAs($admin)
            ->get(route('rooms.index'))
            ->assertOk()
            ->assertSee(route('complaints.create', ['room_id' => $room->id]))
            ->assertSee('Pengaduan Kerusakan');

        $this->get(route('complaints.create', ['room_id' => $room->id]))
            ->assertOk()
            ->assertSee('Nama Pengadu')
            ->assertSee($admin->name)
            ->assertSee('Ruang Rapat')
            ->assertSee('name="room_id" value="'.$room->id.'"', false)
            ->assertSee('name="photo"', false)
            ->assertSee('Pilih dari file')
            ->assertSee('Ambil dari kamera')
            ->assertSee('getUserMedia')
            ->assertDontSee('name="camera_photo"', false)
            ->assertDontSee('<select name="room_id"', false);

        $this->post(route('complaints.store'), [
            'room_id' => $room->id,
            'description' => 'Pintu ruang rusak',
            'photo' => UploadedFile::fake()->image('pintu-rusak.jpg', 1, 1),
        ])->assertRedirect(route('complaints.index'));

        $complaint = Complaint::query()->firstOrFail();
        $this->assertSame($admin->id, $complaint->reporter_id);
        $this->assertSame($room->id, $complaint->room_id);
        $this->assertDatabaseHas('notifications', [
            'complaint_id' => $complaint->id,
            'user_id' => $pic->id,
        ]);
    }

    public function test_admin_can_open_a_room_complaint_from_pending_or_approved_agendas(): void
    {
        $admin = $this->userWithRole('Admin');
        $pic = $this->userWithRole('PIC');
        $eligibleRoom = Room::create([
            'room_name' => 'Ruang Agenda Admin',
            'capacity' => 20,
            'pic_id' => $pic->id,
        ]);
        $rejectedRoom = Room::create([
            'room_name' => 'Ruang Agenda Ditolak Admin',
            'capacity' => 20,
            'pic_id' => $pic->id,
        ]);

        foreach ([
            [$eligibleRoom, 'Agenda Disetujui Admin', 'Disetujui'],
            [$eligibleRoom, 'Agenda Pending Admin', 'Pending'],
            [$rejectedRoom, 'Agenda Ditolak Admin', 'Ditolak'],
        ] as [$room, $title, $status]) {
            Event::create([
                'user_id' => $admin->id,
                'room_id' => $room->id,
                'title' => $title,
                'event_date' => now()->addDay()->toDateString(),
                'start_time' => '09:30',
                'end_time' => '10:15',
                'status' => $status,
            ]);
        }

        $this->actingAs($admin)
            ->get(route('events.index'))
            ->assertOk()
            ->assertSee('Laporkan Kerusakan Ruangan')
            ->assertSee(route('complaints.create', ['room_id' => $eligibleRoom->id]))
            ->assertDontSee(route('complaints.create', ['room_id' => $rejectedRoom->id]));
    }

    public function test_guru_and_organization_can_report_the_room_from_pending_or_approved_agendas(): void
    {
        $pic = $this->userWithRole('PIC');
        $guru = $this->userWithRole('Guru');
        $organization = $this->userWithRole('Organisasi');
        $guruRoom = Room::create([
            'room_name' => 'Ruang Agenda Guru',
            'capacity' => 20,
            'pic_id' => $pic->id,
        ]);
        $organizationRoom = Room::create([
            'room_name' => 'Ruang Agenda Organisasi',
            'capacity' => 20,
            'pic_id' => $pic->id,
        ]);
        $pendingRoom = Room::create([
            'room_name' => 'Ruang Agenda Pending',
            'capacity' => 20,
            'pic_id' => $pic->id,
        ]);
        $rejectedRoom = Room::create([
            'room_name' => 'Ruang Agenda Ditolak',
            'capacity' => 20,
            'pic_id' => $pic->id,
        ]);

        foreach ([
            [$guru, $guruRoom, 'Agenda Guru', 'Disetujui'],
            [$organization, $organizationRoom, 'Agenda Organisasi', 'Disetujui'],
            [$guru, $pendingRoom, 'Agenda Pending', 'Pending'],
            [$guru, $rejectedRoom, 'Agenda Ditolak', 'Ditolak'],
        ] as [$applicant, $room, $title, $status]) {
            Event::create([
                'user_id' => $applicant->id,
                'room_id' => $room->id,
                'title' => $title,
                'event_date' => now()->addDay()->toDateString(),
                'start_time' => '09:30',
                'end_time' => '10:15',
                'status' => $status,
            ]);
        }

        $this->actingAs($guru)
            ->get(route('events.index'))
            ->assertOk()
            ->assertSee(route('complaints.create', ['room_id' => $guruRoom->id]))
            ->assertSee('Laporkan Kerusakan Ruangan')
            ->assertSee(route('complaints.create', ['room_id' => $pendingRoom->id]))
            ->assertDontSee(route('complaints.create', ['room_id' => $rejectedRoom->id]));

        $this->get(route('complaints.create', ['room_id' => $guruRoom->id]))
            ->assertOk()
            ->assertSee('Ruang Agenda Guru')
            ->assertSee('name="room_id" value="'.$guruRoom->id.'"', false);

        $this->get(route('complaints.create', ['room_id' => $pendingRoom->id]))
            ->assertOk()
            ->assertSee('Ruang Agenda Pending')
            ->assertSee('name="room_id" value="'.$pendingRoom->id.'"', false);

        $this->actingAs($organization)
            ->get(route('events.index'))
            ->assertOk()
            ->assertSee(route('complaints.create', ['room_id' => $organizationRoom->id]))
            ->assertSee('Laporkan Kerusakan Ruangan');

        $this->get(route('complaints.create', ['room_id' => $organizationRoom->id]))
            ->assertOk()
            ->assertSee('Ruang Agenda Organisasi')
            ->assertSee('name="room_id" value="'.$organizationRoom->id.'"', false);
    }

    public function test_complaint_photos_are_previewed_through_authorized_storage_route(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('complaints/evidence.jpg', 'photo');

        $admin = $this->userWithRole('Admin');
        $reporter = $this->userWithRole('Guru');
        $pic = $this->userWithRole('PIC');
        $room = Room::create([
            'room_name' => 'Lab Komputer',
            'capacity' => 30,
            'pic_id' => $pic->id,
        ]);
        Complaint::create([
            'room_id' => $room->id,
            'reporter_id' => $reporter->id,
            'description' => 'Proyektor rusak',
            'photo_path' => 'complaints/evidence.jpg',
        ]);

        $complaint = Complaint::query()->firstOrFail();
        $photoUrl = route('complaints.show-photo', $complaint, false);
        $adminResponse = $this->actingAs($admin)
            ->withServerVariables(['HTTP_HOST' => '127.0.0.1:8000'])
            ->get(route('complaints.index'));
        $adminResponse->assertOk()
            ->assertSeeInOrder([
                'Belum Diperbaiki',
                'src="'.$photoUrl.'"',
            ], false)
            ->assertSee('href="'.$photoUrl.'"', false)
            ->assertDontSee('Buat Pengaduan');

        $this->get(route('complaints.show-photo', $complaint))
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg')
            ->assertHeader('content-disposition', 'inline; filename=evidence.jpg')
            ->assertStreamedContent('photo');

        $this->actingAs($reporter)
            ->get(route('complaints.index'))
            ->assertOk()
            ->assertSee('src="'.$photoUrl.'"', false);

        $this->actingAs($pic)
            ->get(route('complaints.index'))
            ->assertOk()
            ->assertSee('src="'.$photoUrl.'"', false);

        $otherPic = $this->userWithRole('PIC');
        $this->actingAs($otherPic)
            ->get(route('complaints.show-photo', $complaint))
            ->assertForbidden();
    }

    public function test_admin_whatsapp_warning_opens_assigned_pic_chat_with_complaint_details(): void
    {
        $admin = $this->userWithRole('Admin');
        $reporter = $this->userWithRole('Guru');
        $pic = $this->userWithRole('PIC');
        $pic->update(['whatsapp' => '081234567890']);
        $this->assertDatabaseHas('users', [
            'id' => $pic->id,
            'whatsapp' => '081234567890',
        ]);
        $room = Room::create([
            'room_name' => 'Lab Komputer',
            'capacity' => 30,
            'pic_id' => $pic->id,
        ]);
        Complaint::create([
            'room_id' => $room->id,
            'reporter_id' => $reporter->id,
            'description' => 'Proyektor tidak menyala',
            'status_repair' => 'Belum Diperbaiki',
        ]);
        $this->assertSame('081234567890', $room->fresh()->pic->whatsapp);

        $message = implode("\n", [
            'Peringatan Pengaduan Fasilitas RAPI-Sekolah',
            'Ruangan: Lab Komputer',
            'Pengaduan: Proyektor tidak menyala',
            'Pelapor: '.$reporter->name,
            'Status: Belum Diperbaiki',
            'Mohon segera ditindaklanjuti.',
        ]);

        $this->actingAs($admin)
            ->get(route('complaints.index'))
            ->assertOk()
            ->assertSee(
                'href="https://wa.me/6281234567890?text='.rawurlencode($message).'"',
                false,
            );
    }

    public function test_pic_only_sees_complaints_for_rooms_they_are_responsible_for(): void
    {
        $actingPic = $this->userWithRole('PIC');
        $assignedPic = $this->userWithRole('PIC');
        $reporter = $this->userWithRole('Guru');
        $assignedRoom = Room::create([
            'room_name' => 'Ruangan Tanggung Jawab PIC',
            'capacity' => 20,
            'pic_id' => $actingPic->id,
        ]);
        $room = Room::create([
            'room_name' => 'Ruang PIC Lain',
            'capacity' => 20,
            'pic_id' => $assignedPic->id,
        ]);
        Complaint::create([
            'room_id' => $assignedRoom->id,
            'reporter_id' => $reporter->id,
            'description' => 'Pengaduan untuk PIC yang login',
        ]);
        Complaint::create([
            'room_id' => $room->id,
            'reporter_id' => $reporter->id,
            'description' => 'Pengaduan milik PIC lain',
        ]);

        $this->actingAs($actingPic)
            ->get(route('complaints.index'))
            ->assertOk()
            ->assertSee('Pengaduan untuk PIC yang login')
            ->assertDontSee('Pengaduan milik PIC lain');
    }

    public function test_pic_cannot_update_complaint_status_for_another_pics_room(): void
    {
        $actingPic = $this->userWithRole('PIC');
        $assignedPic = $this->userWithRole('PIC');
        $reporter = $this->userWithRole('Guru');
        $room = Room::create([
            'room_name' => 'Ruang Status PIC',
            'capacity' => 20,
            'pic_id' => $assignedPic->id,
        ]);
        $complaint = Complaint::create([
            'room_id' => $room->id,
            'reporter_id' => $reporter->id,
            'description' => 'Status dapat diperbarui PIC lain',
        ]);

        $this->actingAs($actingPic)
            ->patch(route('complaints.status', $complaint), [
                'status_repair' => 'Menunggu Spare Part',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('complaints', [
            'id' => $complaint->id,
            'status_repair' => 'Belum Diperbaiki',
        ]);
    }

    public function test_organization_can_submit_a_complaint_and_booking_like_a_teacher(): void
    {
        Storage::fake('public');

        $this->assertDatabaseHas('roles', ['role_name' => 'Organisasi']);
        $organization = $this->userWithRole('Organisasi');
        $pic = $this->userWithRole('PIC');
        $room = Room::create([
            'room_name' => 'Lab Organisasi',
            'capacity' => 30,
            'pic_id' => $pic->id,
        ]);

        $this->actingAs($organization)
            ->get(route('complaints.create'))
            ->assertOk();

        $this->post(route('complaints.store'), [
            'room_id' => $room->id,
            'description' => 'Lampu rusak',
            'photo' => UploadedFile::fake()->image('bukti.jpg', 1, 1),
        ])->assertRedirect(route('complaints.index'));

        $this->assertDatabaseHas('complaints', [
            'reporter_id' => $organization->id,
            'room_id' => $room->id,
            'description' => 'Lampu rusak',
        ]);
        $organizationComplaint = Complaint::query()->where('reporter_id', $organization->id)->firstOrFail();
        $this->assertNotNull($organizationComplaint->photo_path);
        Storage::disk('public')->assertExists($organizationComplaint->photo_path);

        $this->get(route('events.create'))->assertOk();
        $this->post(route('events.store'), [
            'room_id' => $room->id,
            'title' => 'Rapat Organisasi',
            'event_date' => now()->addDay()->toDateString(),
            'start_time' => '09:30',
            'end_time' => '10:15',
        ])->assertRedirect(route('events.index'));

        $this->assertDatabaseHas('events', [
            'user_id' => $organization->id,
            'room_id' => $room->id,
            'title' => 'Rapat Organisasi',
        ]);
    }

    public function test_uploaded_complaint_photo_is_saved_and_visible_to_admin_and_pic(): void
    {
        Storage::fake('public');

        $reporter = $this->userWithRole('Guru');
        $admin = $this->userWithRole('Admin');
        $pic = $this->userWithRole('PIC');
        $room = Room::create([
            'room_name' => 'Lab Foto',
            'capacity' => 30,
            'pic_id' => $pic->id,
        ]);

        $this->actingAs($reporter)
            ->post(route('complaints.store'), [
                'room_id' => $room->id,
                'description' => 'Perlu diperiksa',
                'photo' => UploadedFile::fake()->image('bukti.jpg', 1, 1),
            ])
            ->assertRedirect(route('complaints.index'));

        $complaint = Complaint::query()->latest('id')->firstOrFail();
        $this->assertNotNull($complaint->photo_path);
        Storage::disk('public')->assertExists($complaint->photo_path);

        $photoUrl = route('complaints.show-photo', $complaint, false);
        $this->actingAs($admin)->get(route('complaints.index'))
            ->assertOk()
            ->assertSee('src="'.$photoUrl.'"', false);
        $this->get($photoUrl)
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg')
            ->assertStreamedContent(file_get_contents(Storage::disk('public')->path($complaint->photo_path)));

        $this->actingAs($pic)->get(route('complaints.index'))
            ->assertOk()
            ->assertSee('src="'.$photoUrl.'"', false);

        $this->get($photoUrl)
            ->assertOk()
            ->assertStreamedContent(file_get_contents(Storage::disk('public')->path($complaint->photo_path)));

        $this->actingAs($reporter)->get($photoUrl)
            ->assertOk()
            ->assertStreamedContent(file_get_contents(Storage::disk('public')->path($complaint->photo_path)));
    }

    public function test_new_complaint_notifies_only_the_pic_assigned_to_its_room(): void
    {
        Storage::fake('public');

        $reporter = $this->userWithRole('Guru');
        $assignedPic = $this->userWithRole('PIC');
        $otherPic = $this->userWithRole('PIC');
        $room = Room::create([
            'room_name' => 'Ruang untuk Notifikasi',
            'capacity' => 20,
            'pic_id' => $assignedPic->id,
        ]);

        $this->actingAs($reporter)
            ->post(route('complaints.store'), [
                'room_id' => $room->id,
                'description' => 'Pintu perlu diperbaiki',
                'photo' => UploadedFile::fake()->image('bukti.jpg', 1, 1),
            ])
            ->assertRedirect(route('complaints.index'));

        $complaint = Complaint::query()->latest('id')->firstOrFail();
        $this->assertDatabaseHas('notifications', [
            'complaint_id' => $complaint->id,
            'user_id' => $assignedPic->id,
            'message' => 'Pengaduan baru di Ruang untuk Notifikasi dari '.$reporter->name.': Pintu perlu diperbaiki',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'complaint_id' => $complaint->id,
            'user_id' => $otherPic->id,
        ]);

        $this->actingAs($assignedPic)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Pengaduan baru di Ruang untuk Notifikasi');

        $this->actingAs($otherPic)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Pengaduan baru di Ruang untuk Notifikasi');
    }

    public function test_pic_no_longer_sees_complaint_notification_after_room_is_reassigned(): void
    {
        $oldPic = $this->userWithRole('PIC');
        $newPic = $this->userWithRole('PIC');
        $reporter = $this->userWithRole('Guru');
        $room = Room::create([
            'room_name' => 'Ruangan Berganti PIC',
            'capacity' => 20,
            'pic_id' => $oldPic->id,
        ]);
        $complaint = Complaint::create([
            'room_id' => $room->id,
            'reporter_id' => $reporter->id,
            'description' => 'Laporan lama di ruangan',
        ]);

        Notification::create([
            'complaint_id' => $complaint->id,
            'user_id' => $oldPic->id,
            'message' => 'Notifikasi pengaduan lama',
        ]);

        $room->update(['pic_id' => $newPic->id]);

        $this->actingAs($oldPic)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Notifikasi pengaduan lama');

        $this->actingAs($newPic)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Notifikasi pengaduan lama');
    }

    public function test_new_complaint_requires_a_photo(): void
    {
        $reporter = $this->userWithRole('Guru');
        $pic = $this->userWithRole('PIC');
        $room = Room::create([
            'room_name' => 'Lab Tanpa Foto',
            'capacity' => 30,
            'pic_id' => $pic->id,
        ]);

        $this->actingAs($reporter)
            ->from(route('complaints.create'))
            ->post(route('complaints.store'), [
                'room_id' => $room->id,
                'description' => 'Pengaduan tanpa foto',
            ])
            ->assertRedirect(route('complaints.create'))
            ->assertSessionHasErrors('photo');

        $this->assertDatabaseCount('complaints', 0);
    }

    public function test_complaint_photo_upload_accepts_up_to_five_megabytes(): void
    {
        Storage::fake('public');

        $reporter = $this->userWithRole('Guru');
        $pic = $this->userWithRole('PIC');
        $room = Room::create([
            'room_name' => 'Lab Ukuran Foto',
            'capacity' => 30,
            'pic_id' => $pic->id,
        ]);

        $this->actingAs($reporter)
            ->post(route('complaints.store'), [
                'room_id' => $room->id,
                'description' => 'Foto berukuran lima MB',
                'photo' => UploadedFile::fake()->image('besar.jpg', 1, 1)->size(5120),
            ])
            ->assertRedirect(route('complaints.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('complaints', 1);
    }

    public function test_complaint_photo_upload_rejects_files_over_five_megabytes_with_a_clear_message(): void
    {
        Storage::fake('public');

        $reporter = $this->userWithRole('Guru');
        $pic = $this->userWithRole('PIC');
        $room = Room::create([
            'room_name' => 'Lab Foto Terlalu Besar',
            'capacity' => 30,
            'pic_id' => $pic->id,
        ]);

        $this->actingAs($reporter)
            ->from(route('complaints.create'))
            ->post(route('complaints.store'), [
                'room_id' => $room->id,
                'description' => 'Foto lebih dari lima MB',
                'photo' => UploadedFile::fake()->image('terlalu-besar.jpg', 1, 1)->size(5121),
            ])
            ->assertRedirect(route('complaints.create'))
            ->assertSessionHasErrors([
                'photo' => 'Ukuran foto maksimal 5 MB.',
            ]);

        $this->assertDatabaseCount('complaints', 0);
    }

    public function test_admin_can_add_a_photo_to_an_existing_complaint(): void
    {
        Storage::fake('public');

        $admin = $this->userWithRole('Admin');
        $reporter = $this->userWithRole('Guru');
        $pic = $this->userWithRole('PIC');
        $room = Room::create([
            'room_name' => 'Lab Lama',
            'capacity' => 30,
            'pic_id' => $pic->id,
        ]);
        $complaint = Complaint::create([
            'room_id' => $room->id,
            'reporter_id' => $reporter->id,
            'description' => 'Foto belum ada',
        ]);

        $this->actingAs($admin)
            ->patch(route('complaints.photo', $complaint), [
                'photo' => UploadedFile::fake()->image('susulan.jpg', 1, 1),
            ])
            ->assertRedirect();

        $complaint->refresh();
        $this->assertNotNull($complaint->photo_path);
        Storage::disk('public')->assertExists($complaint->photo_path);
    }

    public function test_complaints_can_be_filtered_by_status(): void
    {
        $admin = $this->userWithRole('Admin');
        $reporter = $this->userWithRole('Guru');
        $pic = $this->userWithRole('PIC');
        $room = Room::create([
            'room_name' => 'Ruang Uji Filter',
            'capacity' => 25,
            'pic_id' => $pic->id,
        ]);

        $c1 = Complaint::create([
            'room_id' => $room->id,
            'reporter_id' => $reporter->id,
            'description' => 'Kerusakan Pending AC',
            'status_repair' => 'Belum Diperbaiki',
        ]);
        $c2 = Complaint::create([
            'room_id' => $room->id,
            'reporter_id' => $reporter->id,
            'description' => 'Kerusakan Menunggu Baut',
            'status_repair' => 'Menunggu Spare Part',
        ]);
        $c3 = Complaint::create([
            'room_id' => $room->id,
            'reporter_id' => $reporter->id,
            'description' => 'Kerusakan Selesai Meja',
            'status_repair' => 'Sudah Diperbaiki',
            'repair_note' => 'Kaki meja sudah diganti dan diperkuat.',
        ]);

        // 1. Filter 'semua'
        $this->actingAs($admin)
            ->get(route('complaints.index', ['status' => 'semua']))
            ->assertOk()
            ->assertSee('Kerusakan Pending AC')
            ->assertSee('Kerusakan Menunggu Baut')
            ->assertSee('Kerusakan Selesai Meja')
            ->assertSee('Keterangan Sudah Diperbaiki')
            ->assertSee('Kaki meja sudah diganti dan diperkuat.');

        // 2. Filter 'belum_diperbaiki'
        $this->actingAs($admin)
            ->get(route('complaints.index', ['status' => 'belum_diperbaiki']))
            ->assertOk()
            ->assertSee('Kerusakan Pending AC')
            ->assertDontSee('Kerusakan Menunggu Baut')
            ->assertDontSee('Kerusakan Selesai Meja');

        // 3. Filter 'menunggu_spare_part'
        $this->actingAs($admin)
            ->get(route('complaints.index', ['status' => 'menunggu_spare_part']))
            ->assertOk()
            ->assertDontSee('Kerusakan Pending AC')
            ->assertSee('Kerusakan Menunggu Baut')
            ->assertDontSee('Kerusakan Selesai Meja');

        // 4. Filter 'sudah_diperbaiki'
        $this->actingAs($admin)
            ->get(route('complaints.index', ['status' => 'sudah_diperbaiki']))
            ->assertOk()
            ->assertDontSee('Kerusakan Pending AC')
            ->assertDontSee('Kerusakan Menunggu Baut')
            ->assertSee('Kerusakan Selesai Meja')
            ->assertSee('Kaki meja sudah diganti dan diperkuat.');
    }

    public function test_pic_must_fill_repair_note_when_marking_sudah_diperbaiki(): void
    {
        $pic = $this->userWithRole('PIC');
        $reporter = $this->userWithRole('Guru');
        $room = Room::create([
            'room_name' => 'Lab Komputer PIC',
            'capacity' => 30,
            'pic_id' => $pic->id,
        ]);
        $complaint = Complaint::create([
            'room_id' => $room->id,
            'reporter_id' => $reporter->id,
            'description' => 'Kabel LAN putus',
            'status_repair' => 'Belum Diperbaiki',
        ]);

        // Validasi gagal jika status 'Sudah Diperbaiki' tapi repair_note kosong
        $this->actingAs($pic)
            ->from(route('complaints.index'))
            ->patch(route('complaints.status', $complaint), [
                'status_repair' => 'Sudah Diperbaiki',
                'repair_note' => '',
            ])
            ->assertRedirect(route('complaints.index'))
            ->assertSessionHasErrors(['repair_note']);

        $this->assertDatabaseHas('complaints', [
            'id' => $complaint->id,
            'status_repair' => 'Belum Diperbaiki',
            'repair_note' => null,
        ]);

        // Berhasil jika repair_note diisi
        $this->actingAs($pic)
            ->from(route('complaints.index'))
            ->patch(route('complaints.status', $complaint), [
                'status_repair' => 'Sudah Diperbaiki',
                'repair_note' => 'Kabel LAN baru Cat6 telah dipasang dan diterminasi.',
            ])
            ->assertRedirect(route('complaints.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('complaints', [
            'id' => $complaint->id,
            'status_repair' => 'Sudah Diperbaiki',
            'repair_note' => 'Kabel LAN baru Cat6 telah dipasang dan diterminasi.',
        ]);

        // Cek halaman pengaduan menampilkan keterangan perbaikan
        $this->actingAs($reporter)
            ->get(route('complaints.index'))
            ->assertOk()
            ->assertSee('Keterangan Sudah Diperbaiki')
            ->assertSee('Kabel LAN baru Cat6 telah dipasang dan diterminasi.');
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
