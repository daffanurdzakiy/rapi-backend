<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FlashAlertDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_layout_renders_styled_flash_alerts_for_all_variants(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin)
            ->withSession([
                'success' => 'Agenda berhasil dibuat dan langsung disetujui.',
                'error' => 'Ruangan tidak dapat dihapus karena masih digunakan.',
                'warning' => 'Periksa kembali jadwal yang berdekatan.',
                'status' => 'Data telah disinkronkan.',
            ])
            ->get(route('events.index'))
            ->assertOk()
            ->assertSee('flash-alert--success', false)
            ->assertSee('Berhasil')
            ->assertSee('Agenda berhasil dibuat dan langsung disetujui.')
            ->assertSee('flash-alert--error', false)
            ->assertSee('Terjadi Kesalahan')
            ->assertSee('Ruangan tidak dapat dihapus karena masih digunakan.')
            ->assertSee('flash-alert--warning', false)
            ->assertSee('Perhatian')
            ->assertSee('Periksa kembali jadwal yang berdekatan.')
            ->assertSee('flash-alert--info', false)
            ->assertSee('Informasi')
            ->assertSee('Data telah disinkronkan.')
            ->assertSee('data-flash-close', false);
    }

    public function test_auth_pages_render_compact_status_and_mail_error_alerts(): void
    {
        $this->withSession(['status' => 'Kata sandi Anda berhasil diperbarui.'])
            ->get(route('login'))
            ->assertOk()
            ->assertSee('flash-alert--info flash-alert--compact', false)
            ->assertSee('Kata sandi Anda berhasil diperbarui.');

        $this->withSession(['mail_error' => 'Server email tidak dapat dihubungi.'])
            ->get(route('password.request'))
            ->assertOk()
            ->assertSee('flash-alert--error flash-alert--compact', false)
            ->assertSee('Pengiriman Email Gagal')
            ->assertSee('Server email tidak dapat dihubungi.');
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

