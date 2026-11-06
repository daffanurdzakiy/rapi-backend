<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
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

    public function test_admin_can_download_user_import_csv_template(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get(route('admin.users.template'));
        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition', 'attachment; filename="template-import-pengguna.csv"');

        $content = $response->streamedContent();
        $this->assertStringContainsString('Nama Lengkap', $content);
        $this->assertStringContainsString('Budi Santoso', $content);
    }

    public function test_admin_can_import_users_from_csv_file(): void
    {
        $admin = $this->createAdmin();
        $guruRole = $this->createRole('Guru');
        $orgRole = $this->createRole('Organisasi');

        $csvContent = implode("\n", [
            'Nama Lengkap,Email,Peran,WhatsApp,Password',
            'Pak Guru Satu,guru1@sekolah.id,Guru,0811111111,password123',
            'Pramuka SMK,pramuka@sekolah.id,Organisasi,0822222222,password123',
            'Guru Tanpa Email,,Guru,0833333333,password123',
        ]);

        $file = UploadedFile::fake()->createWithContent('daftar_guru.csv', $csvContent);

        $this->actingAs($admin)
            ->post(route('admin.users.import'), [
                'import_file' => $file,
            ])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'name' => 'Pak Guru Satu',
            'email' => 'guru1@sekolah.id',
            'whatsapp' => '0811111111',
            'role_id' => $guruRole,
        ]);

        $this->assertDatabaseHas('users', [
            'name' => 'Pramuka SMK',
            'email' => 'pramuka@sekolah.id',
            'role_id' => $orgRole,
        ]);

        $this->assertDatabaseHas('users', [
            'name' => 'Guru Tanpa Email',
            'email' => 'guru.tanpa.email@rapi.sch.id',
        ]);
    }

    public function test_admin_sees_import_button_on_users_page(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Import File')
            ->assertSee(route('admin.users.import'))
            ->assertSee(route('admin.users.template'));
    }

    public function test_admin_can_import_users_from_xlsx_file(): void
    {
        $admin = $this->createAdmin();
        $guruRole = $this->createRole('Guru');

        $zipPath = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheets><sheet name="Sheet1" sheetId="1" r:id="rId1" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"/></sheets></workbook>');
        $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="6" uniqueCount="6"><si><t>Nama</t></si><si><t>Email</t></si><si><t>Peran</t></si><si><t>WhatsApp</t></si><si><t>Guru Excel</t></si><si><t>excel@sekolah.id</t></si></sst>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c><c r="D1" t="s"><v>3</v></c></row><row r="2"><c r="A2" t="s"><v>4</v></c><c r="B2" t="s"><v>5</v></c><c r="C2" t="s"><v>2</v></c><c r="D2"><v>0899999999</v></c></row></sheetData></worksheet>');
        $zip->close();

        $file = new UploadedFile($zipPath, 'data_guru.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $this->actingAs($admin)
            ->post(route('admin.users.import'), [
                'import_file' => $file,
            ])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'name' => 'Guru Excel',
            'email' => 'excel@sekolah.id',
            'whatsapp' => '0899999999',
            'role_id' => $guruRole,
        ]);

        @unlink($zipPath);
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
