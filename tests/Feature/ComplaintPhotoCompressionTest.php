<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Room;
use App\Models\User;
use App\Services\ImageCompressionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ComplaintPhotoCompressionTest extends TestCase
{
    use RefreshDatabase;

    protected User $guru;
    protected User $admin;
    protected User $pic;
    protected Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->guru = $this->userWithRole('Guru', ['name' => 'Guru Pengadu']);
        $this->admin = $this->userWithRole('Admin', ['name' => 'Admin Utama']);
        $this->pic = $this->userWithRole('PIC', ['name' => 'PIC Teknisi']);

        $this->room = Room::create([
            'room_name' => 'Laboratorium Komputer 1',
            'capacity' => 30,
            'pic_id' => $this->pic->id,
        ]);
    }

    private function userWithRole(string $roleName, array $attributes = []): User
    {
        $roleId = DB::table('roles')->where('role_name', $roleName)->value('id')
            ?? DB::table('roles')->insertGetId([
                'role_name' => $roleName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return User::factory()->create(array_merge(['role_id' => $roleId], $attributes));
    }

    public function test_image_compression_service_resizes_and_compresses_image(): void
    {
        $service = new ImageCompressionService();

        // Buat file gambar tiruan berukuran besar (2000 x 1500 px)
        $photo = UploadedFile::fake()->image('bukti_rusak.jpg', 2000, 1500);

        $storedPath = $service->compressAndStore($photo, 'complaints', 1200, 75);

        $this->assertNotNull($storedPath);
        $this->assertStringStartsWith('complaints/', $storedPath);
        $this->assertStringEndsWith('.jpg', $storedPath);

        Storage::disk('public')->assertExists($storedPath);

        // Periksa resolusi gambar hasil kompresi di storage tidak melebihi 1200px
        $realPath = Storage::disk('public')->path($storedPath);
        $dimensions = @getimagesize($realPath);

        $this->assertNotFalse($dimensions);
        $this->assertLessThanOrEqual(1200, $dimensions[0]);
        $this->assertLessThanOrEqual(1200, $dimensions[1]);
        $this->assertEquals(IMAGETYPE_JPEG, $dimensions[2]);
    }

    public function test_web_complaint_store_automatically_compresses_photo(): void
    {
        $largePhoto = UploadedFile::fake()->image('foto_kamera_hp.jpg', 2400, 1800);

        $response = $this->actingAs($this->guru)
            ->post(route('complaints.store'), [
                'room_id' => $this->room->id,
                'description' => 'AC ruangan tidak menyala sama sekali',
                'photo' => $largePhoto,
            ]);

        $response->assertRedirect(route('complaints.index'));
        $response->assertSessionHas('success');

        $complaint = Complaint::where('room_id', $this->room->id)->first();
        $this->assertNotNull($complaint);
        $this->assertNotNull($complaint->photo_path);

        Storage::disk('public')->assertExists($complaint->photo_path);

        $realPath = Storage::disk('public')->path($complaint->photo_path);
        $dimensions = @getimagesize($realPath);
        $this->assertNotFalse($dimensions);
        $this->assertLessThanOrEqual(1200, max($dimensions[0], $dimensions[1]));
        $this->assertEquals(IMAGETYPE_JPEG, $dimensions[2]);
    }

    public function test_web_complaint_update_photo_compresses_new_photo_and_removes_old(): void
    {
        $oldPhoto = UploadedFile::fake()->image('lama.jpg', 800, 600);
        $oldPath = app(ImageCompressionService::class)->compressAndStore($oldPhoto, 'complaints');

        $complaint = Complaint::create([
            'room_id' => $this->room->id,
            'reporter_id' => $this->guru->id,
            'description' => 'Lampu kedip-kedip',
            'photo_path' => $oldPath,
            'status_repair' => 'Belum Diperbaiki',
        ]);

        Storage::disk('public')->assertExists($oldPath);

        $newPhoto = UploadedFile::fake()->image('baru.png', 1920, 1080);

        $response = $this->actingAs($this->guru)
            ->patch(route('complaints.photo', $complaint), [
                'photo' => $newPhoto,
            ]);

        $response->assertSessionHas('success');

        // Foto lama harus sudah terhapus
        Storage::disk('public')->assertMissing($oldPath);

        // Foto baru harus ada dan terkompresi
        $complaint->refresh();
        Storage::disk('public')->assertExists($complaint->photo_path);
        $this->assertNotEquals($oldPath, $complaint->photo_path);
    }

    public function test_api_complaint_store_automatically_compresses_photo(): void
    {
        $photo = UploadedFile::fake()->image('bukti_rusak_api.jpg', 2200, 1650);

        $response = $this->actingAs($this->guru, 'sanctum')
            ->postJson('/api/complaints', [
                'room_id' => $this->room->id,
                'description' => 'Proyektor mati mendadak saat presentasi',
                'photo' => $photo,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);

        $photoPath = $response->json('data.photo_path');
        $this->assertNotNull($photoPath);

        Storage::disk('public')->assertExists($photoPath);
        $realPath = Storage::disk('public')->path($photoPath);
        $dimensions = @getimagesize($realPath);
        $this->assertNotFalse($dimensions);
        $this->assertLessThanOrEqual(1200, max($dimensions[0], $dimensions[1]));
    }

    public function test_artisan_command_compresses_existing_uncompressed_photos(): void
    {
        // Buat file foto dummy beresolusi tinggi di folder public/complaints
        $largePhoto = UploadedFile::fake()->image('uncompressed_evidence.jpg', 2500, 2000);
        $filename = 'complaints/uncompressed_evidence.jpg';
        Storage::disk('public')->put($filename, $largePhoto->getContent());

        $originalSize = Storage::disk('public')->size($filename);

        $this->artisan('complaints:compress-photos', ['--force' => true])
            ->assertExitCode(0);

        $compressedSize = Storage::disk('public')->size($filename);
        $this->assertLessThanOrEqual($originalSize, $compressedSize);

        $realPath = Storage::disk('public')->path($filename);
        $dimensions = @getimagesize($realPath);
        $this->assertNotFalse($dimensions);
        $this->assertLessThanOrEqual(1200, max($dimensions[0], $dimensions[1]));
    }
}
