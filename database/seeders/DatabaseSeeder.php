<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

// Seeder awal untuk mengisi data Role wajib UKK dan akun Administrator default saat menjalankan `php artisan db:seed`.
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Membuat 4 Role utama sistem RAPI-Sekolah jika belum ada di tabel roles.
        $roles = collect(['Admin', 'Guru', 'Organisasi', 'PIC'])
            ->mapWithKeys(fn (string $roleName) => [
                $roleName => Role::firstOrCreate(['role_name' => $roleName]),
            ]);

        // Membuat akun Admin default (Email: admin@example.com | Password: Admin12345!).
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('Admin12345!'),
                'role_id' => $roles['Admin']->id,
            ],
        );
    }
}
