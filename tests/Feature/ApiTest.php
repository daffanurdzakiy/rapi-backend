<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Event;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_calendar_api_returns_approved_events_before_login(): void
    {
        $pic = $this->userWithRole('PIC');
        $room = Room::create([
            'room_name' => 'Lab Komputer 1',
            'location' => 'Lantai 2 Gedung A',
            'capacity' => 36,
            'pic_id' => $pic->id,
        ]);

        $guru = $this->userWithRole('Guru');
        $date = Carbon::tomorrow()->toDateString();

        Event::create([
            'user_id' => $guru->id,
            'room_id' => $room->id,
            'title' => 'Workshop Coding',
            'event_date' => $date,
            'start_time' => '08:00',
            'end_time' => '09:30',
            'status' => 'Disetujui',
        ]);

        Event::create([
            'user_id' => $guru->id,
            'room_id' => $room->id,
            'title' => 'Rapat Tertutup',
            'event_date' => $date,
            'start_time' => '10:15',
            'end_time' => '11:45',
            'status' => 'Pending',
        ]);

        $response = $this->getJson('/api/calendar?month='.Carbon::parse($date)->format('Y-m'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_events', 1)
            ->assertJsonFragment(['title' => 'Workshop Coding'])
            ->assertJsonMissing(['title' => 'Rapat Tertutup']);
    }

    public function test_auth_api_login_me_update_profile_and_logout(): void
    {
        $user = $this->userWithRole('Guru', [
            'email' => 'guru@sekolah.sch.id',
            'password' => Hash::make('Password123!'),
            'name' => 'Budi Santoso',
            'whatsapp' => '081234567890',
        ]);

        // Login
        $loginResponse = $this->postJson('/api/login', [
            'email' => 'guru@sekolah.sch.id',
            'password' => 'Password123!',
        ]);

        $loginResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'token_type',
                    'user' => ['id', 'name', 'email', 'whatsapp', 'role'],
                ],
            ]);

        $token = $loginResponse->json('data.token');
        $this->assertNotEmpty($token);

        // Get Me
        $this->authenticateWithToken($token)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'guru@sekolah.sch.id')
            ->assertJsonPath('data.role.name', 'Guru');

        // Update Profile
        $this->authenticateWithToken($token)
            ->putJson('/api/me', [
                'name' => 'Budi Santoso, M.Pd',
                'whatsapp' => '081299998888',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Budi Santoso, M.Pd')
            ->assertJsonPath('data.whatsapp', '081299998888');

        // Logout
        $this->authenticateWithToken($token)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        // Accessing me after logout should fail
        $this->authenticateWithToken($token)
            ->getJson('/api/me')
            ->assertUnauthorized();
    }

    public function test_pending_bookings_do_not_block_but_approved_bookings_prevent_conflicts(): void
    {
        $admin = $this->userWithRole('Admin');
        $pic = $this->userWithRole('PIC');
        $room = Room::create([
            'room_name' => 'Aula Utama',
            'capacity' => 150,
            'pic_id' => $pic->id,
        ]);

        $organisasi = $this->userWithRole('Organisasi');
        $date = Carbon::tomorrow()->toDateString();

        $token = $organisasi->createToken('test')->plainTextToken;

        // Create booking with valid sessions (08:00 to 10:15)
        $createResponse = $this->authenticateWithToken($token)->postJson('/api/events', [
            'room_id' => $room->id,
            'title' => 'LDK OSIS 2026',
            'event_date' => $date,
            'start_time' => '08:00',
            'end_time' => '10:15',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'Pending')
            ->assertJsonPath('data.title', 'LDK OSIS 2026')
            ->assertJsonPath('message', 'Agenda berhasil diajukan dan menunggu persetujuan Admin.');

        $this->assertDatabaseHas('notifications', [
            'event_id' => data_get($createResponse->json(), 'data.id'),
            'user_id' => $admin->id,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'event_id' => data_get($createResponse->json(), 'data.id'),
            'user_id' => $pic->id,
        ]);

        // A pending booking does not block another proposal for the same time.
        $pendingResponse = $this->authenticateWithToken($token)->postJson('/api/events', [
            'room_id' => $room->id,
            'title' => 'Seminar PMR',
            'event_date' => $date,
            'start_time' => '09:30',
            'end_time' => '11:00',
        ]);

        $pendingResponse->assertCreated()
            ->assertJsonPath('data.status', 'Pending');

        Event::whereKey(data_get($createResponse->json(), 'data.id'))
            ->update(['status' => 'Disetujui']);

        $conflictResponse = $this->authenticateWithToken($token)->postJson('/api/events', [
            'room_id' => $room->id,
            'title' => 'Rapat Kesiswaan',
            'event_date' => $date,
            'start_time' => '09:30',
            'end_time' => '11:00',
        ]);

        $conflictResponse->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_pic_cannot_propose_or_decide_events(): void
    {
        $pic = $this->userWithRole('PIC');
        $room = Room::create([
            'room_name' => 'Lab Multimedia',
            'capacity' => 40,
            'pic_id' => $pic->id,
        ]);

        $guru = $this->userWithRole('Guru');
        $date = Carbon::tomorrow()->toDateString();

        $event = Event::create([
            'user_id' => $guru->id,
            'room_id' => $room->id,
            'title' => 'Ujian Praktik Desain',
            'event_date' => $date,
            'start_time' => '08:00',
            'end_time' => '10:15',
            'status' => 'Pending',
        ]);

        $picToken = $pic->createToken('pic_test')->plainTextToken;

        // PIC cannot propose event
        $this->authenticateWithToken($picToken)->postJson('/api/events', [
            'room_id' => $room->id,
            'title' => 'Agenda dari PIC',
            'event_date' => $date,
            'start_time' => '12:30',
            'end_time' => '13:15',
        ])->assertForbidden();

        $this->authenticateWithToken($picToken)
            ->patchJson("/api/events/{$event->id}/decide", [
                'status' => 'Disetujui',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'status' => 'Pending',
            'approved_by' => null,
        ]);
    }

    public function test_complaint_lifecycle_and_pic_report(): void
    {
        Storage::fake('public');

        $pic = $this->userWithRole('PIC');
        $room = Room::create([
            'room_name' => 'Ruang Teori 5',
            'capacity' => 32,
            'pic_id' => $pic->id,
        ]);

        $guru = $this->userWithRole('Guru');
        $guruToken = $guru->createToken('guru_test')->plainTextToken;

        // Guru reports a broken AC
        $photo = UploadedFile::fake()->image('broken_ac.jpg', 640, 480);
        $reportResponse = $this->authenticateWithToken($guruToken)->postJson('/api/complaints', [
            'room_id' => $room->id,
            'description' => 'AC tidak dingin dan mengeluarkan air',
            'photo' => $photo,
        ]);

        $reportResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status_repair', 'Belum Diperbaiki');

        $complaintId = $reportResponse->json('data.id');
        $this->assertNotNull($complaintId);

        // PIC receives notification
        $this->assertDatabaseHas('notifications', [
            'user_id' => $pic->id,
            'complaint_id' => $complaintId,
        ]);

        // PIC updates status to Menunggu Spare Part
        $picToken = $pic->createToken('pic_test')->plainTextToken;
        $statusResponse = $this->authenticateWithToken($picToken)->patchJson("/api/complaints/{$complaintId}/status", [
            'status_repair' => 'Menunggu Spare Part',
        ]);

        $statusResponse->assertOk()
            ->assertJsonPath('data.status_repair', 'Menunggu Spare Part');

        $this->authenticateWithToken($picToken)
            ->get("/api/complaints/{$complaintId}/photo")
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg');

        // Reporter receives notification
        $this->assertDatabaseHas('notifications', [
            'user_id' => $guru->id,
            'complaint_id' => $complaintId,
        ]);

        // PIC checks complaint reports
        $reportQueryResponse = $this->authenticateWithToken($picToken)->getJson('/api/pic/complaint-reports?periode=harian');
        $reportQueryResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.stats.total', 1)
            ->assertJsonPath('data.stats.menunggu_spare_part', 1);
    }

    public function test_dashboard_api_for_pic_and_guru(): void
    {
        $pic = $this->userWithRole('PIC');
        $room = Room::create([
            'room_name' => 'Lab Jaringan',
            'capacity' => 30,
            'pic_id' => $pic->id,
        ]);

        $guru = $this->userWithRole('Guru');
        $picToken = $pic->createToken('pic_token')->plainTextToken;
        $guruToken = $guru->createToken('guru_token')->plainTextToken;

        // PIC dashboard
        $this->authenticateWithToken($picToken)->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.role', 'PIC')
            ->assertJsonPath('data.stats.assigned_rooms_count', 1);

        // Guru dashboard
        $this->authenticateWithToken($guruToken)->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.role', 'Guru')
            ->assertJsonPath('data.stats.my_events_count', 0);
    }

    private function authenticateWithToken(string $token): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function userWithRole(string $roleName, array $attributes = []): User
    {
        $roleId = DB::table('roles')->where('role_name', $roleName)->value('id')
            ?? DB::table('roles')->insertGetId([
                'role_name' => $roleName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return User::factory()->create(array_merge([
            'role_id' => $roleId,
        ], $attributes));
    }
}
