<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

// =========================================================================
// MODEL ELOQUENT: USER (PENGGUNA SISTEM)
// =========================================================================
// Merepresentasikan tabel `users`. Menggunakan PHP Attributes #[Fillable] dan #[Hidden] bawaan Laravel modern.
// Jika menambah kolom baru pada tabel `users` (misal: 'nip' atau 'phone'), tambahkan ke dalam #[Fillable([...])].
/**
 * @property int $id
 * @property string $name
 */
#[Fillable(['name', 'email', 'whatsapp', 'password', 'role_id'])]
// Menyembunyikan atribut sensitif (`password` dan `remember_token`) agar tidak ikut terekspos saat objek User diubah menjadi JSON
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    // HasApiTokens: Trait dari Laravel Sanctum agar user bisa membuat Bearer Token untuk login di aplikasi Mobile/API
    // HasFactory: Untuk pembuatan data dummy (testing/seeder)
    // Notifiable: Agar user dapat dikirimi notifikasi email (seperti email OTP reset password)
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    // Mengatur konversi tipe data otomatis: `password` otomatis di-hash (enkripsi satu arah) saat disimpan
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Relasi BelongsTo ke model Role: Menentukan peran pengguna (Admin, PIC, Guru, atau Organisasi)
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** Return rooms delegated to this user as PIC. */
    // Relasi HasMany ke model Room: Mengambil daftar ruangan yang ditugaskan kepada user ini sebagai PIC (foreign key: pic_id)
    public function assignedRooms(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Room::class, 'pic_id');
    }

    /** Get notifications sent to this user. */
    // Relasi HasMany ke model Notification: Mengambil daftar notifikasi in-app milik user ini
    public function notifications(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Notification::class);
    }
}
