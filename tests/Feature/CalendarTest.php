<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_calendar_shows_two_agendas_and_a_dialog_for_the_rest(): void
    {
        $room = $this->room('Ruang Rapat');
        $date = now()->startOfMonth()->addDays(10)->toDateString();

        foreach ([
            ['Agenda Pertama', '08:00', '08:45'],
            ['Agenda Kedua', '08:45', '09:30'],
            ['Agenda Ketiga', '09:30', '10:15'],
        ] as [$title, $start, $end]) {
            Event::create([
                'user_id' => $this->userWithRole('Guru')->id,
                'room_id' => $room->id,
                'title' => $title,
                'event_date' => $date,
                'start_time' => $start,
                'end_time' => $end,
                'status' => 'Disetujui',
            ]);
        }

        Event::create([
            'user_id' => $this->userWithRole('Guru')->id,
            'room_id' => $room->id,
            'title' => 'Agenda Belum Disetujui',
            'event_date' => $date,
            'start_time' => '10:15',
            'end_time' => '11:00',
            'status' => 'Pending',
        ]);

        $response = $this->get(route('calendar.public', ['month' => now()->format('Y-m')]))
            ->assertOk()
            ->assertSee('Kalender Agenda')
            ->assertSee('images/school-emblem.png')
            ->assertSee('Logo SMK Negeri 1 Bantul')
            ->assertSee('Agenda Pertama')
            ->assertSee('Agenda Kedua')
            ->assertSee('Agenda Ketiga')
            ->assertSee('+1 lainnya')
            ->assertSee('id="agenda-'.str_replace('-', '', $date).'"', false)
            ->assertDontSee('Agenda Belum Disetujui')
            ->assertSee('chip-applicant');

        $this->assertStringContainsString('agenda-chip chip-applicant', $response->getContent());
        $this->assertStringContainsString('dialog-event chip-applicant', $response->getContent());
    }

    public function test_calendar_color_coding_for_roles_and_holidays_and_weekends(): void
    {
        $room = $this->room('Ruang Teater');
        $date = Carbon::parse('2026-05-15'); // Hari Jumat

        // 1. Peminjaman oleh Guru -> chip-applicant (Hijau)
        $eventGuru = Event::create([
            'user_id' => $this->userWithRole('Guru')->id,
            'room_id' => $room->id,
            'title' => 'Workshop Guru',
            'event_date' => $date->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'status' => 'Disetujui',
        ]);
        $this->assertSame('applicant', $eventGuru->calendar_color_type);
        $this->assertSame('chip-applicant', $eventGuru->calendar_badge_class);

        // 2. Peminjaman oleh Organisasi -> chip-applicant (Hijau)
        $eventOrg = Event::create([
            'user_id' => $this->userWithRole('Organisasi')->id,
            'room_id' => $room->id,
            'title' => 'Rapat OSIS',
            'event_date' => $date->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'Disetujui',
        ]);
        $this->assertSame('applicant', $eventOrg->calendar_color_type);
        $this->assertSame('chip-applicant', $eventOrg->calendar_badge_class);

        // 3. Peminjaman oleh Admin -> chip-admin (Kuning)
        $eventAdmin = Event::create([
            'user_id' => $this->userWithRole('Admin')->id,
            'room_id' => $room->id,
            'title' => 'Rapat Pleno Admin',
            'event_date' => $date->toDateString(),
            'start_time' => '13:00',
            'end_time' => '15:00',
            'status' => 'Disetujui',
        ]);
        $this->assertSame('admin', $eventAdmin->calendar_color_type);
        $this->assertSame('chip-admin', $eventAdmin->calendar_badge_class);

        // 4. Request halaman kalender bulan Mei 2026 (terdapat libur nasional 1 Mei Hari Buruh & 14 Mei Kenaikan Yesus)
        $response = $this->get(route('calendar.public', ['month' => '2026-05']))
            ->assertOk()
            ->assertSee('Guru &amp; Organisasi (Hijau)', false)
            ->assertSee('Admin (Kuning)')
            ->assertSee('Libur Nasional (Merah)')
            ->assertSee('chip-applicant')
            ->assertSee('chip-admin')
            ->assertSee('chip-holiday')
            ->assertSee('Hari Buruh Internasional')
            ->assertSee('calendar-day weekend', false);

        // Pastikan kolom weekend (Sabtu & Minggu) teridentifikasi
        $html = $response->getContent();
        $this->assertStringContainsString('weekend-header', $html);
        $this->assertStringContainsString('weekend', $html);
    }

    public function test_signed_in_calendar_uses_the_same_month_view(): void
    {
        $user = $this->userWithRole('Guru');

        $this->actingAs($user)
            ->get(route('calendar', ['month' => now()->format('Y-m')]))
            ->assertOk()
            ->assertSee('Kalender Agenda')
            ->assertSee('calendar-card', false)
            ->assertSee('calendar-page', false)
            ->assertSee('class="profile-link" href="'.route('profile').'"', false)
            ->assertSee('class="profile-avatar"', false)
            ->assertDontSee('>Profil</a>', false);
    }

    private function room(string $name): Room
    {
        return Room::create([
            'room_name' => $name,
            'capacity' => 20,
            'pic_id' => $this->userWithRole('PIC')->id,
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
