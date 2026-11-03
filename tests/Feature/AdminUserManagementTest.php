<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    public function test_admin_can_create_a_user_and_see_it_in_the_user_list(): void
    {
        $admin = $this->createAdmin();
        $roleId = $this->createRole('Guru');

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Guru Baru',
                'email' => 'guru.baru@example.com',
                'whatsapp' => '08123456789',
                'role_id' => $roleId,
                'password' => 'password-baru',
                'password_confirmation' => 'password-baru',
            ])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success', 'Akun berhasil dibuat.');

        $this->assertDatabaseHas('users', [
            'name' => 'Guru Baru',
            'email' => 'guru.baru@example.com',
            'whatsapp' => '08123456789',
            'role_id' => $roleId,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Guru Baru')
            ->assertSee('guru.baru@example.com')
            ->assertSee('NO. HP')
            ->assertSee('08123456789')
            ->assertSee('class="card users-toolbar"', false)
            ->assertSee('<table>', false);
    }

    public function test_admin_can_edit_a_user_and_see_the_updated_values_in_the_list(): void
    {
        $admin = $this->createAdmin();
        $roleId = $this->createRole('Organisasi');
        $user = User::factory()->create([
            'name' => 'Nama Lama',
            'email' => 'lama@example.com',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $user), [
                'user_id' => $user->id,
                'name' => 'Nama Baru',
                'email' => 'baru@example.com',
                'whatsapp' => '08987654321',
                'role_id' => $roleId,
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success', 'Akun berhasil diperbarui.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Nama Baru',
            'email' => 'baru@example.com',
            'whatsapp' => '08987654321',
            'role_id' => $roleId,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Nama Baru')
            ->assertSee('baru@example.com')
            ->assertSee('08987654321');
    }

    public function test_user_form_displays_validation_errors_when_save_is_rejected(): void
    {
        $admin = $this->createAdmin();
        $roleId = $this->createRole('Guru');

        $this->followingRedirects()
            ->actingAs($admin)
            ->from(route('admin.users.index'))
            ->post(route('admin.users.store'), [
                'name' => 'Guru Baru',
                'email' => 'guru.baru@example.com',
                'role_id' => $roleId,
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertOk()
            ->assertSee('Perubahan belum disimpan. Periksa kembali data berikut:')
            ->assertSee('password');
    }

    public function test_users_are_grouped_by_role_in_the_requested_order(): void
    {
        $admin = $this->createAdmin();

        foreach (['Guru', 'Organisasi', 'PIC', 'Admin'] as $roleName) {
            User::factory()->create([
                'name' => $roleName.' Pengguna',
                'role_id' => $this->createRole($roleName),
            ]);
        }

        $response = $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('ADMIN')
            ->assertSee('Admin Pengguna')
            ->assertSee('PIC')
            ->assertSee('PIC Pengguna')
            ->assertSee('ORGANISASI')
            ->assertSee('Organisasi Pengguna')
            ->assertSee('GURU')
            ->assertSee('Guru Pengguna');

        preg_match_all('/<tr class="role-divider"><th colspan="5">([A-Z]+)/', $response->getContent(), $roleHeadings);
        $this->assertSame(['ADMIN', 'PIC', 'ORGANISASI', 'GURU'], $roleHeadings[1]);
    }

    public function test_admin_can_search_users_by_name_email_phone_or_role(): void
    {
        $admin = $this->createAdmin();
        $picRole = $this->createRole('PIC');
        User::factory()->create([
            'name' => 'Daffa PIC',
            'email' => 'daffa@example.com',
            'whatsapp' => '08123456789',
            'role_id' => $picRole,
        ]);
        User::factory()->create([
            'name' => 'Guru Lain',
            'email' => 'guru@example.com',
            'role_id' => $this->createRole('Guru'),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['q' => '08123456789']))
            ->assertOk()
            ->assertSee('Daffa PIC')
            ->assertDontSee('Guru Lain')
            ->assertSee('name="q"', false)
            ->assertSee('value="08123456789"', false);

        $this->get(route('admin.users.index', ['q' => 'PIC']))
            ->assertOk()
            ->assertSee('Daffa PIC')
            ->assertDontSee('Guru Lain');
    }

    private function createAdmin(): User
    {
        $roleId = $this->createRole('Admin');

        return User::factory()->create(['role_id' => $roleId]);
    }

    private function createRole(string $name): int
    {
        return DB::table('roles')->insertGetId([
            'role_name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
