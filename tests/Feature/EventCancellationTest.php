<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EventCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_decide_an_event_cancellation_request(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 11, 10, 0, 0, 'Asia/Jakarta'));
        [$admin, $pic, $owner, $event] = $this->makeApprovedEvent();

        $this->actingAs($owner)
            ->from(route('events.index'))
            ->post(route('events.cancellation.request', $event), [
                'cancellation_reason' => 'Kegiatan dibatalkan oleh penyelenggara.',
            ])
            ->assertRedirect(route('events.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'status' => 'Menunggu Pembatalan',
            'cancellation_reason' => 'Kegiatan dibatalkan oleh penyelenggara.',
        ]);
        $this->assertDatabaseHas('notifications', ['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->assertDatabaseMissing('notifications', ['event_id' => $event->id, 'user_id' => $pic->id]);

        $this->actingAs($pic)
            ->get(route('events.index'))
            ->assertOk()
            ->assertSee('Kegiatan dibatalkan oleh penyelenggara.')
            ->assertDontSee(route('events.index', ['status' => 'menunggu_persetujuan']), false)
            ->assertDontSee(route('events.index', ['status' => 'ditolak']), false)
            ->assertDontSee(route('events.index', ['status' => 'menunggu_pembatalan']), false)
            ->assertDontSee('Setujui Pembatalan')
            ->assertDontSee('Tolak Pembatalan');

        $this->actingAs($pic)
            ->patch(route('events.cancellation.decide', $event), [
                'status' => 'Disetujui',
                'cancellation_decision_reason' => 'Agenda tetap dibutuhkan sekolah.',
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->patch(route('events.cancellation.decide', $event), [
                'status' => 'Disetujui',
                'cancellation_decision_reason' => 'Agenda tetap dibutuhkan sekolah.',
            ])
            ->assertRedirect(route('events.index'))
            ->assertSessionHas('success', 'Permintaan pembatalan ditolak dan agenda tetap disetujui.');

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'status' => 'Disetujui',
            'cancellation_decision_reason' => 'Agenda tetap dibutuhkan sekolah.',
            'cancellation_reviewed_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'event_id' => $event->id,
            'user_id' => $owner->id,
            'message' => "Permintaan pembatalan agenda {$event->title} ditolak oleh {$admin->name}. Alasan: Agenda tetap dibutuhkan sekolah.",
        ]);
    }

    public function test_api_cancellation_approval_releases_room_only_after_decision(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 11, 10, 0, 0, 'Asia/Jakarta'));
        [$admin, $pic, $owner, $event] = $this->makeApprovedEvent();

        $this->apiAs($owner)
            ->postJson("/api/events/{$event->id}/cancellation-request", [
                'cancellation_reason' => 'Acara tidak jadi dilaksanakan.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'Menunggu Pembatalan')
            ->assertJsonPath('data.cancellation_reason', 'Acara tidak jadi dilaksanakan.');

        $availabilityUrl = '/api/events/check-availability?'.http_build_query([
            'room_id' => $event->room_id,
            'event_date' => '2026-11-12',
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]);
        $this->getJson($availabilityUrl)
            ->assertOk()
            ->assertJsonPath('data.available', false);

        $this->apiAs($pic)
            ->patchJson("/api/events/{$event->id}/cancellation-decision", [
                'status' => 'Dibatalkan',
            ])
            ->assertForbidden();

        $this->apiAs($admin)
            ->patchJson("/api/events/{$event->id}/cancellation-decision", [
                'status' => 'Dibatalkan',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'Dibatalkan')
            ->assertJsonPath('data.cancellation_reviewed_by', $admin->id);

        $this->getJson($availabilityUrl)
            ->assertOk()
            ->assertJsonPath('data.available', true);
        $this->assertDatabaseHas('notifications', [
            'event_id' => $event->id,
            'user_id' => $owner->id,
            'message' => "Permintaan pembatalan agenda {$event->title} disetujui oleh {$admin->name}.",
        ]);
    }

    public function test_only_owner_can_request_cancellation_and_started_agendas_are_rejected(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 11, 10, 0, 0, 'Asia/Jakarta'));
        [, , $owner, $event] = $this->makeApprovedEvent();
        $otherTeacher = $this->userWithRole('Guru');

        $this->apiAs($otherTeacher)
            ->postJson("/api/events/{$event->id}/cancellation-request", [
                'cancellation_reason' => 'Bukan agenda saya.',
            ])
            ->assertForbidden();

        $event->update([
            'event_date' => '2026-11-11',
            'start_time' => '09:00',
            'end_time' => '11:00',
        ]);

        $this->apiAs($owner)
            ->postJson("/api/events/{$event->id}/cancellation-request", [
                'cancellation_reason' => 'Kegiatan batal.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cancellation_reason');

        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 'Disetujui']);
    }

    public function test_cancellation_cannot_be_approved_after_the_agenda_has_started(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 11, 10, 0, 0, 'Asia/Jakarta'));
        [$admin, $pic, $owner, $event] = $this->makeApprovedEvent();

        $this->apiAs($owner)
            ->postJson("/api/events/{$event->id}/cancellation-request", [
                'cancellation_reason' => 'Jadwal berubah.',
            ])
            ->assertOk();

        $this->travelTo(Carbon::create(2026, 11, 12, 9, 0, 0, 'Asia/Jakarta'));
        $this->apiAs($pic)
            ->patchJson("/api/events/{$event->id}/cancellation-decision", [
                'status' => 'Dibatalkan',
            ])
            ->assertForbidden();

        $this->apiAs($admin)
            ->patchJson("/api/events/{$event->id}/cancellation-decision", [
                'status' => 'Dibatalkan',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'status' => 'Menunggu Pembatalan',
        ]);
    }

    /**
     * @return array{User, User, User, Event}
     */
    private function makeApprovedEvent(): array
    {
        $admin = $this->userWithRole('Admin');
        $pic = $this->userWithRole('PIC');
        $owner = $this->userWithRole('Guru');
        $room = Room::create([
            'room_name' => 'Ruang Uji',
            'capacity' => 24,
            'pic_id' => $pic->id,
        ]);
        $event = Event::create([
            'user_id' => $owner->id,
            'room_id' => $room->id,
            'title' => 'Pelatihan Guru',
            'event_date' => '2026-11-12',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'status' => 'Disetujui',
            'approved_by' => $admin->id,
        ]);

        return [$admin, $pic, $owner, $event];
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

    private function apiAs(User $user): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('event-cancellation-test')->plainTextToken);
    }
}
