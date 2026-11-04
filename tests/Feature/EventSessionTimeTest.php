<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EventSessionTimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_form_uses_the_default_admin_managed_sessions(): void
    {
        $teacher = $this->userWithRole('Guru');

        $this->actingAs($teacher)
            ->get(route('events.create'))
            ->assertOk()
            ->assertSee('Sesi 1 · 07:15')
            ->assertSee('Sesi 14 · 17:00')
            ->assertSee('<select id="start_time" name="start_time" required>', false)
            ->assertSee('min="'.now()->addDay()->toDateString().'"', false)
            ->assertDontSee('max="', false)
            ->assertSee('Peminjaman ruang hanya dapat diajukan mulai H-1 (besok) dan seterusnya.')
            ->assertSee('Guru dan Organisasi hanya dapat meminjam ruangan sampai sesi 14 (pukul 17.00).')
            ->assertSee('Batal')
            ->assertSee('href="'.route('events.index').'"', false)
            ->assertDontSee('Waktu bebas (setelah 17.00)')
            ->assertDontSee('name="start_time_free"', false)
            ->assertDontSee('type="time" name="start_time"', false);
    }

    public function test_admin_can_only_select_an_existing_room_and_gets_a_submit_confirmation(): void
    {
        $admin = $this->userWithRole('Admin');
        $room = $this->room('Ruang Rapat');

        $this->actingAs($admin)
            ->get(route('events.create'))
            ->assertOk()
            ->assertSee('id="room_search" type="search"', false)
            ->assertSee('Pilih atau cari ruangan...')
            ->assertSee('data-room-id="'.$room->id.'"', false)
            ->assertSee('data-room-name="'.$room->room_name.'"', false)
            ->assertSee('filterRooms', false)
            ->assertDontSee('Tambah ruangan baru')
            ->assertDontSee('name="room_name"', false)
            ->assertSee('window.confirm', false)
            ->assertSee('Kirim pengajuan agenda ini?', false)
            ->assertSee('Ruangan: ', false)
            ->assertSee($room->room_name);
    }

    public function test_room_booking_rejects_dates_before_tomorrow(): void
    {
        $teacher = $this->userWithRole('Guru');
        $room = $this->room();

        foreach ([now()->toDateString(), now()->subDay()->toDateString()] as $date) {
            $this->actingAs($teacher)
                ->from(route('events.create'))
                ->post(route('events.store'), [
                    'room_id' => $room->id,
                    'title' => 'Peminjaman sebelum H-1',
                    'event_date' => $date,
                    'start_time' => '09:30',
                    'end_time' => '10:15',
                ])
                ->assertRedirect(route('events.create'))
                ->assertSessionHasErrors(['event_date']);
        }

        $this->assertDatabaseCount('events', 0);
    }

    public function test_room_booking_accepts_dates_after_tomorrow(): void
    {
        $teacher = $this->userWithRole('Guru');
        $room = $this->room();

        $this->actingAs($teacher)
            ->post(route('events.store'), [
                'room_id' => $room->id,
                'title' => 'Peminjaman tanggal mendatang',
                'event_date' => now()->addDays(2)->toDateString(),
                'start_time' => '09:30',
                'end_time' => '10:15',
            ])
            ->assertRedirect(route('events.index'));

        $this->assertDatabaseHas('events', [
            'user_id' => $teacher->id,
            'room_id' => $room->id,
            'title' => 'Peminjaman tanggal mendatang',
            'event_date' => now()->addDays(2)->toDateString().' 00:00:00',
            'status' => 'Pending',
        ]);
    }

    public function test_admin_agenda_is_approved_immediately_without_an_approval_step(): void
    {
        $admin = $this->userWithRole('Admin');
        $room = $this->room();

        $this->actingAs($admin)
            ->post(route('events.store'), [
                'room_id' => $room->id,
                'title' => 'Agenda Langsung Disetujui',
                'event_date' => now()->addDay()->toDateString(),
                'start_time' => '09:30',
                'end_time' => '10:15',
            ])
            ->assertRedirect(route('events.index'))
            ->assertSessionHas('success', 'Agenda berhasil dibuat dan langsung disetujui.');

        $this->assertDatabaseHas('events', [
            'user_id' => $admin->id,
            'room_id' => $room->id,
            'title' => 'Agenda Langsung Disetujui',
            'status' => 'Disetujui',
        ]);
    }

    public function test_guru_can_book_through_the_last_session_ending_at_seventeen(): void
    {
        $teacher = $this->userWithRole('Guru');
        $room = $this->room();

        $this->actingAs($teacher)
            ->post(route('events.store'), [
                'room_id' => $room->id,
                'title' => 'Agenda sampai sesi 14',
                'event_date' => now()->addDay()->toDateString(),
                'start_time' => '16:15',
                'end_time' => '17:00',
            ])
            ->assertRedirect(route('events.index'));

        $this->assertDatabaseHas('events', [
            'user_id' => $teacher->id,
            'room_id' => $room->id,
            'title' => 'Agenda sampai sesi 14',
            'end_time' => '17:00',
        ]);
    }

    public function test_agenda_rejects_times_that_are_not_configured_as_sessions(): void
    {
        $teacher = $this->userWithRole('Guru');
        $room = $this->room();

        $this->actingAs($teacher)
            ->from(route('events.create'))
            ->post(route('events.store'), [
                'room_id' => $room->id,
                'title' => 'Agenda di luar sesi',
                'event_date' => now()->addDay()->toDateString(),
                'start_time' => '09:00',
                'end_time' => '10:00',
            ])
            ->assertRedirect(route('events.create'))
            ->assertSessionHasErrors(['start_time', 'end_time']);

        $this->assertDatabaseCount('events', 0);
    }

    public function test_admin_has_no_separate_session_management_page(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin)
            ->get(route('events.index'))
            ->assertOk()
            ->assertDontSee('Kelola Sesi Waktu');
    }

    public function test_admin_can_edit_agenda_time_and_move_it_to_another_room(): void
    {
        $admin = $this->userWithRole('Admin');
        $applicant = $this->userWithRole('Guru');
        $firstRoom = $this->room();
        $secondRoom = $this->room('Ruang Pengganti');
        $event = Event::create([
            'user_id' => $applicant->id,
            'room_id' => $firstRoom->id,
            'title' => 'Rapat Sekolah',
            'event_date' => now()->addDay()->toDateString(),
            'start_time' => '09:30',
            'end_time' => '10:15',
            'status' => 'Disetujui',
        ]);

        $this->actingAs($admin)
            ->get(route('events.index'))
            ->assertOk()
            ->assertSee('>Edit</a>', false);

        $this->get(route('events.edit', $event))
            ->assertOk()
            ->assertSee('Jam mulai')
            ->assertSee('Alasan perubahan / pemindahan ruangan');

        $this->put(route('events.update', $event), [
            'room_id' => $secondRoom->id,
            'start_time' => '11:00',
            'end_time' => '11:45',
            'move_reason' => 'Ruangan awal sedang diperbaiki.',
        ])->assertRedirect(route('events.index'));

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'room_id' => $secondRoom->id,
            'start_time' => '11:00',
            'end_time' => '11:45',
            'status' => 'Disetujui',
            'move_reason' => 'Ruangan awal sedang diperbaiki.',
        ]);
        $this->assertDatabaseHas('notifications', [
            'event_id' => $event->id,
            'user_id' => $applicant->id,
            'message' => 'Agenda Rapat Sekolah diperbarui: Ruang Pengganti, 11:00-11:45. Alasan: Ruangan awal sedang diperbaiki.',
        ]);
    }

    public function test_admin_can_move_to_room_with_pending_booking_but_not_approved_booking(): void
    {
        $admin = $this->userWithRole('Admin');
        $applicant = $this->userWithRole('Guru');
        $sourceRoom = $this->room('Ruang Asal');
        $targetRoom = $this->room('Ruang Tujuan');
        $eventDate = now()->addDay()->toDateString();
        $event = Event::create([
            'user_id' => $applicant->id,
            'room_id' => $sourceRoom->id,
            'title' => 'Agenda yang Dipindahkan',
            'event_date' => $eventDate,
            'start_time' => '09:30',
            'end_time' => '10:15',
            'status' => 'Disetujui',
        ]);
        $targetEvent = Event::create([
            'user_id' => $applicant->id,
            'room_id' => $targetRoom->id,
            'title' => 'Agenda di Ruang Tujuan',
            'event_date' => $eventDate,
            'start_time' => '11:00',
            'end_time' => '11:45',
            'status' => 'Pending',
        ]);

        $this->actingAs($admin)
            ->put(route('events.update', $event), [
                'room_id' => $targetRoom->id,
                'start_time' => '11:00',
                'end_time' => '11:45',
                'move_reason' => 'Agenda sebelumnya masih menunggu persetujuan.',
            ])
            ->assertRedirect(route('events.index'));

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'room_id' => $targetRoom->id,
            'start_time' => '11:00',
            'end_time' => '11:45',
        ]);

        $event->refresh()->update([
            'room_id' => $sourceRoom->id,
            'start_time' => '09:30',
            'end_time' => '10:15',
        ]);
        $targetEvent->update(['status' => 'Disetujui']);

        $this->from(route('events.edit', $event))
            ->put(route('events.update', $event), [
                'room_id' => $targetRoom->id,
                'start_time' => '11:00',
                'end_time' => '11:45',
                'move_reason' => 'Ruangan tujuan sudah disetujui untuk agenda lain.',
            ])
            ->assertRedirect(route('events.edit', $event))
            ->assertSessionHasErrors(['room_id']);

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'room_id' => $sourceRoom->id,
            'start_time' => '09:30',
            'end_time' => '10:15',
        ]);
    }

    public function test_only_admin_can_edit_an_agenda(): void
    {
        $teacher = $this->userWithRole('Guru');
        $event = Event::create([
            'user_id' => $teacher->id,
            'room_id' => $this->room()->id,
            'title' => 'Agenda Guru',
            'event_date' => now()->addDay()->toDateString(),
            'start_time' => '09:30',
            'end_time' => '10:15',
            'status' => 'Pending',
        ]);

        $this->actingAs($teacher)
            ->get(route('events.edit', $event))
            ->assertForbidden();

        $this->put(route('events.update', $event), [
            'room_id' => $event->room_id,
            'start_time' => '09:30',
            'end_time' => '10:15',
        ])->assertForbidden();
    }

    public function test_admin_can_set_custom_free_time_for_an_individual_agenda_after_seventeen(): void
    {
        $admin = $this->userWithRole('Admin');
        $applicant = $this->userWithRole('Guru');
        $room = $this->room();
        $event = Event::create([
            'user_id' => $applicant->id,
            'room_id' => $room->id,
            'title' => 'Kegiatan Sore',
            'event_date' => now()->addDays(2)->toDateString(),
            'start_time' => '16:15',
            'end_time' => '17:00',
            'status' => 'Disetujui',
        ]);

        $this->actingAs($admin)
            ->get(route('events.edit', $event))
            ->assertOk()
            ->assertSee('Waktu bebas (setelah 17.00)')
            ->assertSee('name="start_time_free"', false)
            ->assertSee('name="end_time_free"', false)
            ->assertSee('Pilih sesi 1–14, atau pilih waktu bebas untuk menentukan jam sendiri setelah pukul 17.00.');

        $this->put(route('events.update', $event), [
            'room_id' => $room->id,
            'start_time' => 'free',
            'start_time_free' => '17:15',
            'end_time' => 'free',
            'end_time_free' => '18:30',
        ])->assertRedirect(route('events.index'));

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'start_time' => '17:15',
            'end_time' => '18:30',
        ]);
        $this->assertDatabaseHas('notifications', [
            'event_id' => $event->id,
            'user_id' => $applicant->id,
        ]);
        $this->assertDatabaseCount('session_times', 14);
    }

    public function test_admin_must_give_a_rejection_reason_and_applicant_is_notified(): void
    {
        $admin = $this->userWithRole('Admin');
        $applicant = $this->userWithRole('Guru');
        $event = Event::create([
            'user_id' => $applicant->id,
            'room_id' => $this->room()->id,
            'title' => 'Rapat Evaluasi',
            'event_date' => now()->addDay()->toDateString(),
            'start_time' => '09:30',
            'end_time' => '10:15',
            'status' => 'Pending',
        ]);

        $this->actingAs($admin)
            ->get(route('events.index'))
            ->assertOk()
            ->assertSee('data-rejection-toggle="'.$event->id.'"', false)
            ->assertSee('id="rejection-panel-'.$event->id.'"', false)
            ->assertSee('disabled', false)
            ->assertSee('Konfirmasi Tolak');

        $this->from(route('events.index'))
            ->patch(route('events.decide', $event), ['status' => 'Ditolak'])
            ->assertRedirect(route('events.index'))
            ->assertSessionHasErrors([
                'rejection_reason' => 'Keterangan penolakan wajib diisi.',
            ]);
        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'status' => 'Pending',
            'rejection_reason' => null,
            'approved_by' => null,
        ]);

        $this->patch(route('events.decide', $event), [
            'status' => 'Ditolak',
            'rejection_reason' => 'Ruangan sedang digunakan untuk kegiatan sekolah.',
        ])->assertRedirect();

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'status' => 'Ditolak',
            'rejection_reason' => 'Ruangan sedang digunakan untuk kegiatan sekolah.',
            'approved_by' => null,
        ]);
        $this->assertDatabaseHas('notifications', [
            'event_id' => $event->id,
            'user_id' => $applicant->id,
            'message' => 'Pengajuan agenda Rapat Evaluasi ditolak. Alasan: Ruangan sedang digunakan untuk kegiatan sekolah.',
        ]);

        $this->actingAs($applicant)
            ->get(route('events.index'))
            ->assertOk()
            ->assertSee('Alasan ditolak:')
            ->assertSee('Ruangan sedang digunakan untuk kegiatan sekolah.');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Pengajuan agenda Rapat Evaluasi ditolak. Alasan: Ruangan sedang digunakan untuk kegiatan sekolah.');
    }

    public function test_admin_approval_is_recorded_and_shown_on_the_agenda(): void
    {
        $admin = $this->userWithRole('Admin');
        $applicant = $this->userWithRole('Guru');
        $event = Event::create([
            'user_id' => $applicant->id,
            'room_id' => $this->room()->id,
            'title' => 'Agenda Disetujui Admin',
            'event_date' => now()->addDay()->toDateString(),
            'start_time' => '09:30',
            'end_time' => '10:15',
            'status' => 'Pending',
        ]);

        $this->actingAs($admin)
            ->patch(route('events.decide', $event), ['status' => 'Disetujui'])
            ->assertRedirect();

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'status' => 'Disetujui',
            'approved_by' => $admin->id,
        ]);
        $this->get(route('events.index'))
            ->assertOk()
            ->assertSee('Disetujui oleh: '.$admin->name.' (Admin)');
    }

    public function test_guru_and_organization_cannot_request_time_after_seventeen(): void
    {
        foreach (['Guru', 'Organisasi'] as $role) {
            $applicant = $this->userWithRole($role);
            $room = $this->room('Ruang '.$role);

            $this->actingAs($applicant)
                ->get(route('events.create'))
                ->assertOk()
                ->assertDontSee('Waktu bebas (setelah 17.00)')
                ->assertDontSee('name="start_time_free"', false)
                ->assertDontSee('name="end_time_free"', false);

            $this->from(route('events.create'))
                ->post(route('events.store'), [
                    'room_id' => $room->id,
                    'title' => 'Agenda di luar jam sekolah',
                    'event_date' => now()->addDay()->toDateString(),
                    'start_time' => 'free',
                    'start_time_free' => '17:30',
                    'end_time' => 'free',
                    'end_time_free' => '19:00',
                ])
                ->assertRedirect(route('events.create'))
                ->assertSessionHasErrors(['start_time', 'end_time']);
        }

        $this->assertDatabaseCount('events', 0);
    }

    public function test_admin_can_request_custom_time_after_seventeen(): void
    {
        $admin = $this->userWithRole('Admin');
        $room = $this->room();

        $this->actingAs($admin)
            ->get(route('events.create'))
            ->assertOk()
            ->assertSee('Waktu bebas (setelah 17.00)')
            ->assertSee('name="start_time_free"', false)
            ->assertSee('name="end_time_free"', false);

        $this->post(route('events.store'), [
            'room_id' => $room->id,
            'title' => 'Agenda Admin Setelah Jam Sekolah',
            'event_date' => now()->addDay()->toDateString(),
            'start_time' => 'free',
            'start_time_free' => '17:30',
            'end_time' => 'free',
            'end_time_free' => '19:00',
        ])->assertRedirect(route('events.index'));

        $this->assertDatabaseHas('events', [
            'user_id' => $admin->id,
            'title' => 'Agenda Admin Setelah Jam Sekolah',
            'start_time' => '17:30',
            'end_time' => '19:00',
            'status' => 'Disetujui',
        ]);
    }

    public function test_admin_cannot_use_unconfigured_time_before_or_at_seventeen_for_an_agenda(): void
    {
        $admin = $this->userWithRole('Admin');
        $event = Event::create([
            'user_id' => $this->userWithRole('Guru')->id,
            'room_id' => $this->room()->id,
            'title' => 'Agenda Sore',
            'event_date' => now()->addDays(2)->toDateString(),
            'start_time' => '16:15',
            'end_time' => '17:00',
            'status' => 'Pending',
        ]);

        $this->actingAs($admin)
            ->from(route('events.edit', $event))
            ->put(route('events.update', $event), [
                'room_id' => $event->room_id,
                'start_time' => '16:30',
                'end_time' => '17:00',
            ])
            ->assertRedirect(route('events.edit', $event))
            ->assertSessionHasErrors(['start_time']);

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'start_time' => '16:15',
            'end_time' => '17:00',
        ]);
    }

    public function test_admin_must_choose_configured_sessions_and_explain_room_changes(): void
    {
        $admin = $this->userWithRole('Admin');
        $event = Event::create([
            'user_id' => $this->userWithRole('Guru')->id,
            'room_id' => $this->room()->id,
            'title' => 'Agenda Diperiksa',
            'event_date' => now()->addDays(2)->toDateString(),
            'start_time' => '09:30',
            'end_time' => '10:15',
            'status' => 'Pending',
        ]);

        $this->actingAs($admin)
            ->from(route('events.edit', $event))
            ->put(route('events.update', $event), [
                'room_id' => $this->room('Ruang Baru')->id,
                'start_time' => '09:30',
                'end_time' => '10:15',
            ])
            ->assertRedirect(route('events.edit', $event))
            ->assertSessionHasErrors(['move_reason']);

        $this->from(route('events.edit', $event))
            ->put(route('events.update', $event), [
                'room_id' => $event->room_id,
                'start_time' => '09:00',
                'end_time' => '10:00',
            ])
            ->assertRedirect(route('events.edit', $event))
            ->assertSessionHasErrors(['start_time', 'end_time']);

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'status' => 'Pending',
            'start_time' => '09:30',
            'end_time' => '10:15',
        ]);
    }

    public function test_admin_edit_agenda_view_provides_anti_bentrok_data_and_all_room_options(): void
    {
        $admin = $this->userWithRole('Admin');
        $applicant = $this->userWithRole('Guru');
        $roomA = $this->room('Ruang A');
        $roomB = $this->room('Ruang B');
        $eventDate = now()->addDays(3)->toDateString();

        $event = Event::create([
            'user_id' => $applicant->id,
            'room_id' => $roomA->id,
            'title' => 'UKK RPL',
            'event_date' => $eventDate,
            'start_time' => '08:00',
            'end_time' => '09:30',
            'status' => 'Disetujui',
        ]);

        $conflictingEvent = Event::create([
            'user_id' => $applicant->id,
            'room_id' => $roomB->id,
            'title' => 'Ujian Sertifikasi',
            'event_date' => $eventDate,
            'start_time' => '08:30',
            'end_time' => '10:00',
            'status' => 'Disetujui',
        ]);

        $this->actingAs($admin)
            ->get(route('events.edit', $event))
            ->assertOk()
            ->assertSee('Ruangan Pilihan / Tujuan')
            ->assertSee('id="room_search"', false)
            ->assertSee('id="room_picker_toggle"', false)
            ->assertSee('id="anti-bentrok-alert"', false)
            ->assertSee('data-status-for="'.$roomA->id.'"', false)
            ->assertSee('data-status-for="'.$roomB->id.'"', false)
            ->assertSee('data-room-name="'.$roomA->room_name.'"', false)
            ->assertSee('data-room-name="'.$roomB->room_name.'"', false)
            ->assertSee('Ujian Sertifikasi')
            ->assertSee('Ruangan Saat Ini');
    }

    public function test_admin_cannot_move_event_to_another_room_with_approved_booking_conflict(): void
    {
        $admin = $this->userWithRole('Admin');
        $applicant = $this->userWithRole('Guru');
        $sourceRoom = $this->room('Ruang Asal');
        $targetRoom = $this->room('Ruang Target');
        $eventDate = now()->addDays(3)->toDateString();

        $event = Event::create([
            'user_id' => $applicant->id,
            'room_id' => $sourceRoom->id,
            'title' => 'Agenda Asal',
            'event_date' => $eventDate,
            'start_time' => '08:00',
            'end_time' => '09:30',
            'status' => 'Disetujui',
        ]);

        Event::create([
            'user_id' => $applicant->id,
            'room_id' => $targetRoom->id,
            'title' => 'Agenda Lain yang Bentrok',
            'event_date' => $eventDate,
            'start_time' => '08:30',
            'end_time' => '10:00',
            'status' => 'Disetujui',
        ]);

        $response = $this->actingAs($admin)
            ->from(route('events.edit', $event))
            ->put(route('events.update', $event), [
                'room_id' => $targetRoom->id,
                'start_time' => '08:00',
                'end_time' => '09:30',
                'move_reason' => 'Pindah ke ruang target',
            ]);

        $response->assertRedirect(route('events.edit', $event))
            ->assertSessionHasErrors(['room_id']);

        $errorMsg = session('errors')->first('room_id');
        $this->assertStringContainsString('Ruangan sudah digunakan', $errorMsg);
        $this->assertStringContainsString('Agenda Lain yang Bentrok', $errorMsg);

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'room_id' => $sourceRoom->id,
        ]);
    }

    public function test_multi_day_booking_creates_daily_events_for_guru(): void
    {
        $guru = $this->userWithRole('Guru');
        $room = $this->room('Lab Komputer 1');
        $startDate = now()->addDays(2)->toDateString();
        $endDate = now()->addDays(4)->toDateString(); // 3 hari berturut-turut

        $response = $this->actingAs($guru)
            ->post(route('events.store'), [
                'room_id' => $room->id,
                'title' => 'Ujian Sertifikasi Keahlian',
                'booking_type' => 'range',
                'event_date' => $startDate,
                'end_date' => $endDate,
                'start_time' => '08:00',
                'end_time' => '11:45',
            ]);

        $response->assertRedirect(route('events.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('events', 3);
        $this->assertDatabaseHas('events', [
            'room_id' => $room->id,
            'user_id' => $guru->id,
            'title' => 'Ujian Sertifikasi Keahlian (Hari 1/3)',
            'event_date' => $startDate . ' 00:00:00',
            'start_time' => '08:00',
            'end_time' => '11:45',
            'status' => 'Pending',
        ]);
        $this->assertDatabaseHas('events', [
            'room_id' => $room->id,
            'user_id' => $guru->id,
            'title' => 'Ujian Sertifikasi Keahlian (Hari 2/3)',
            'event_date' => now()->addDays(3)->toDateString() . ' 00:00:00',
            'status' => 'Pending',
        ]);
        $this->assertDatabaseHas('events', [
            'room_id' => $room->id,
            'user_id' => $guru->id,
            'title' => 'Ujian Sertifikasi Keahlian (Hari 3/3)',
            'event_date' => $endDate . ' 00:00:00',
            'status' => 'Pending',
        ]);

        // Cek notifikasi terkirim ke PIC
        $this->assertDatabaseHas('notifications', [
            'user_id' => $room->pic_id,
        ]);
    }

    public function test_multi_day_booking_auto_approved_for_admin(): void
    {
        $admin = $this->userWithRole('Admin');
        $room = $this->room('Aula Utama');
        $startDate = now()->addDays(5)->toDateString();
        $endDate = now()->addDays(6)->toDateString(); // 2 hari

        $response = $this->actingAs($admin)
            ->post(route('events.store'), [
                'room_id' => $room->id,
                'title' => 'Pameran Karya Siswa',
                'booking_type' => 'range',
                'event_date' => $startDate,
                'end_date' => $endDate,
                'start_time' => '08:00',
                'end_time' => '15:30',
            ]);

        $response->assertRedirect(route('events.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('events', 2);
        $this->assertDatabaseHas('events', [
            'room_id' => $room->id,
            'user_id' => $admin->id,
            'title' => 'Pameran Karya Siswa (Hari 1/2)',
            'event_date' => $startDate . ' 00:00:00',
            'status' => 'Disetujui',
            'approved_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('events', [
            'room_id' => $room->id,
            'user_id' => $admin->id,
            'title' => 'Pameran Karya Siswa (Hari 2/2)',
            'event_date' => $endDate . ' 00:00:00',
            'status' => 'Disetujui',
            'approved_by' => $admin->id,
        ]);
    }

    public function test_multi_day_booking_rolls_back_if_any_day_conflicts(): void
    {
        $admin = $this->userWithRole('Admin');
        $guru = $this->userWithRole('Guru');
        $room = $this->room('Lab Komputer 2');

        $startDate = now()->addDays(3)->toDateString();
        $middleDate = now()->addDays(4)->toDateString();
        $endDate = now()->addDays(5)->toDateString();

        // Hari ke-2 sudah ada jadwal yang disetujui
        Event::create([
            'user_id' => $admin->id,
            'room_id' => $room->id,
            'title' => 'Workshop Internal Guru',
            'event_date' => $middleDate,
            'start_time' => '08:00',
            'end_time' => '11:45',
            'status' => 'Disetujui',
            'approved_by' => $admin->id,
        ]);

        $this->assertDatabaseCount('events', 1);

        // Guru mengajukan rentang 3 hari (hari ke-2 bentrok)
        $response = $this->actingAs($guru)
            ->from(route('events.create'))
            ->post(route('events.store'), [
                'room_id' => $room->id,
                'title' => 'Pelatihan Siswa',
                'booking_type' => 'range',
                'event_date' => $startDate,
                'end_date' => $endDate,
                'start_time' => '08:45',
                'end_time' => '11:00',
            ]);

        $response->assertRedirect(route('events.create'))
            ->assertSessionHasErrors(['room_id']);

        // Pastikan tidak ada event baru yang tersimpan (rollback DB transaction)
        $this->assertDatabaseCount('events', 1);
    }

    public function test_api_multi_day_booking_and_availability(): void
    {
        $guru = $this->userWithRole('Guru');
        $room = $this->room('Ruang Rapat Guru');
        $startDate = now()->addDays(2)->toDateString();
        $endDate = now()->addDays(3)->toDateString();

        // 1. Cek availability multi-hari via API
        $availResponse = $this->actingAs($guru, 'sanctum')
            ->getJson('/api/events/check-availability?' . http_build_query([
                'room_id' => $room->id,
                'event_date' => $startDate,
                'end_date' => $endDate,
                'start_time' => '08:00',
                'end_time' => '11:45',
            ]));

        $availResponse->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'available' => true,
                    'total_days' => 2,
                ],
            ]);

        // 2. Submit multi-hari via API
        $storeResponse = $this->actingAs($guru, 'sanctum')
            ->postJson('/api/events', [
                'room_id' => $room->id,
                'title' => 'Rapat Koordinasi Ujian',
                'event_date' => $startDate,
                'end_date' => $endDate,
                'booking_type' => 'range',
                'start_time' => '08:00',
                'end_time' => '11:45',
            ]);

        $storeResponse->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseCount('events', 2);
        $this->assertDatabaseHas('events', [
            'room_id' => $room->id,
            'title' => 'Rapat Koordinasi Ujian (Hari 1/2)',
            'event_date' => $startDate . ' 00:00:00',
            'status' => 'Pending',
        ]);
        $this->assertDatabaseHas('events', [
            'room_id' => $room->id,
            'title' => 'Rapat Koordinasi Ujian (Hari 2/2)',
            'event_date' => $endDate . ' 00:00:00',
            'status' => 'Pending',
        ]);
    }

    private function room(string $name = 'Ruang Agenda'): Room
    {
        $pic = $this->userWithRole('PIC');

        return Room::create([
            'room_name' => $name,
            'capacity' => 20,
            'pic_id' => $pic->id,
        ]);
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
