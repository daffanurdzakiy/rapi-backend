<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PicComplaintReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_pic_must_choose_a_period_before_complaint_report_is_shown(): void
    {
        $pic = $this->userWithRole('PIC');

        $this->actingAs($pic)
            ->get(route('pic.complaint-reports.index'))
            ->assertOk()
            ->assertSee('Laporan Kerusakan/Pengaduan')
            ->assertSee('Pilih jenis periode')
            ->assertSee('Harian')
            ->assertSee('Mingguan')
            ->assertSee('Bulanan')
            ->assertSee('Laporan')
            ->assertDontSee('Rincian Pengaduan')
            ->assertSee(route('pic.complaint-reports.index'));
    }

    public function test_pic_report_filters_by_selected_period_and_assigned_rooms(): void
    {
        $pic = $this->userWithRole('PIC');
        $otherPic = $this->userWithRole('PIC');
        $reporter = $this->userWithRole('Guru');
        $assignedRoom = $this->roomFor($pic, 'Ruang PIC');
        $otherRoom = $this->roomFor($otherPic, 'Ruang PIC Lain');

        $this->complaintFor($assignedRoom, $reporter, 'Kerusakan harian', '2026-10-21 09:15:00');
        $this->complaintFor($assignedRoom, $reporter, 'Kerusakan bulan ini', '2026-10-25 11:30:00');
        $this->complaintFor($assignedRoom, $reporter, 'Kerusakan minggu lalu', '2026-10-18 08:00:00');
        $this->complaintFor($assignedRoom, $reporter, 'Kerusakan bulan depan', '2026-11-01 08:00:00');
        $this->complaintFor($otherRoom, $reporter, 'Kerusakan PIC lain', '2026-10-21 10:00:00');

        $this->actingAs($pic)
            ->get(route('pic.complaint-reports.index', [
                'periode' => 'bulanan',
                'tanggal' => '2026-10-21',
            ]))
            ->assertOk()
            ->assertSee('Laporan Bulanan yang dipilih:')
            ->assertSee('Rincian Pengaduan')
            ->assertSee('Kerusakan harian')
            ->assertSee('Kerusakan bulan ini')
            ->assertDontSee('Kerusakan bulan depan')
            ->assertDontSee('Kerusakan PIC lain')
            ->assertSee('class="evidence-column">Bukti', false)
            ->assertSee('.evidence-column { display:none !important; }', false);

        $this->get(route('pic.complaint-reports.index', [
            'periode' => 'mingguan',
            'tanggal' => '2026-10-21',
        ]))
            ->assertOk()
            ->assertSee('Laporan Mingguan yang dipilih:')
            ->assertSee('Kerusakan harian')
            ->assertSee('Kerusakan bulan ini')
            ->assertDontSee('Kerusakan minggu lalu')
            ->assertDontSee('Kerusakan PIC lain');

        $this->get(route('pic.complaint-reports.index', [
            'periode' => 'harian',
            'tanggal' => '2026-10-21',
        ]))
            ->assertOk()
            ->assertSee('Laporan Harian yang dipilih:')
            ->assertSee('Kerusakan harian')
            ->assertDontSee('Kerusakan bulan ini')
            ->assertDontSee('Kerusakan PIC lain');
    }

    public function test_non_pic_cannot_access_pic_complaint_report(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin)
            ->get(route('pic.complaint-reports.index'))
            ->assertForbidden();
    }

    public function test_pic_can_view_evidence_photo_for_an_assigned_room(): void
    {
        Storage::fake('public');

        $pic = $this->userWithRole('PIC');
        $reporter = $this->userWithRole('Guru');
        $room = $this->roomFor($pic, 'Ruang Bukti');
        $complaint = $this->complaintFor($room, $reporter, 'Foto bukti kerusakan', now()->toDateTimeString());
        $complaint->update(['photo_path' => 'complaints/evidence.jpg']);
        Storage::disk('public')->put($complaint->photo_path, 'foto-bukti');

        $this->actingAs($pic)
            ->get(route('pic.complaint-reports.index', [
                'periode' => 'harian',
                'tanggal' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Lihat foto')
            ->assertSee(route('pic.complaint-reports.photo', $complaint, false));

        $this->get(route('pic.complaint-reports.photo', $complaint))
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg')
            ->assertStreamedContent('foto-bukti');
    }

    public function test_admin_can_also_view_evidence_photo(): void
    {
        Storage::fake('public');

        $pic = $this->userWithRole('PIC');
        $admin = $this->userWithRole('Admin');
        $reporter = $this->userWithRole('Guru');
        $room = $this->roomFor($pic, 'Ruang Bukti Admin');
        $complaint = $this->complaintFor($room, $reporter, 'Foto bukti untuk admin', now()->toDateTimeString());
        $complaint->update(['photo_path' => 'complaints/admin_evidence.jpg']);
        Storage::disk('public')->put($complaint->photo_path, 'foto-bukti-admin');

        $this->actingAs($admin)
            ->get(route('pic.complaint-reports.photo', $complaint))
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg')
            ->assertStreamedContent('foto-bukti-admin');
    }

    public function test_pic_cannot_view_evidence_photo_from_another_pic_room(): void
    {
        Storage::fake('public');

        $pic = $this->userWithRole('PIC');
        $otherPic = $this->userWithRole('PIC');
        $reporter = $this->userWithRole('Guru');
        $room = $this->roomFor($otherPic, 'Ruang PIC Lain');
        $complaint = $this->complaintFor($room, $reporter, 'Bukti privat', now()->toDateTimeString());
        $complaint->update(['photo_path' => 'complaints/private.jpg']);
        Storage::disk('public')->put($complaint->photo_path, 'foto-privat');

        $this->actingAs($pic)
            ->get(route('pic.complaint-reports.photo', $complaint))
            ->assertForbidden();
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
            'capacity' => 20,
            'pic_id' => $pic->id,
        ]);
    }

    private function complaintFor(Room $room, User $reporter, string $description, string $createdAt): Complaint
    {
        $complaint = Complaint::create([
            'room_id' => $room->id,
            'reporter_id' => $reporter->id,
            'description' => $description,
            'status_repair' => 'Belum Diperbaiki',
        ]);

        DB::table('complaints')
            ->where('id', $complaint->id)
            ->update(['created_at' => $createdAt]);

        return $complaint;
    }
}
