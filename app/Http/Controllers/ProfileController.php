<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

// Controller untuk menampilkan halaman Profil Pengguna yang sedang login di Web.
class ProfileController extends Controller
{
    // Menampilkan view resources/views/profile.blade.php.
    public function show(): View
    {
        return view('profile');
    }
}
