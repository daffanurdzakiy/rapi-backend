<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// =========================================================================
// MODEL ELOQUENT: ROLE (HAK AKSES / PERAN PENGGUNA)
// =========================================================================
// Merepresentasikan tabel `roles` di database (contoh: Admin, PIC, Guru, Organisasi).
class Role extends Model
{
    // Relasi One-to-Many (HasMany): Satu Role dapat dimiliki oleh banyak User
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
