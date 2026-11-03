<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

// Controller khusus Admin untuk mengelola akun pengguna (CRUD User: Admin, PIC, Organisasi, Guru).
class AdminUserController extends Controller
{
    // Menampilkan halaman daftar akun pengguna beserta fitur pencarian dan pengelompokan per Role.
    public function index(Request $request): View
    {
        // Mengambil kata kunci pencarian dari parameter URL ?q=...
        $search = trim((string) $request->query('q', ''));
        $users = User::with('role')
            // Jika ada kata kunci pencarian, filter berdasarkan nama, email, nomor WhatsApp, atau nama role.
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($userQuery) use ($search): void {
                    $userQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('whatsapp', 'like', "%{$search}%")
                        ->orWhereHas('role', fn ($roleQuery) => $roleQuery->where('role_name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('name')
            ->get()
            // Mengelompokkan daftar user berdasarkan nama Role-nya.
            ->groupBy(fn (User $user): string => $user->role?->role_name ?? 'Tanpa Peran')
            // Mengatur urutan tampil kelompok Role di halaman Manajemen Akun.
            ->sortKeysUsing(function (string $first, string $second): int {
                $priority = ['Admin' => 0, 'PIC' => 1, 'Organisasi' => 2, 'Guru' => 3];
                $firstPriority = $priority[$first] ?? 4;
                $secondPriority = $priority[$second] ?? 4;

                return $firstPriority <=> $secondPriority ?: strcasecmp($first, $second);
            });

        return view('admin.users', [
            'roles' => Role::orderBy('id')->get(),
            'users' => $users,
            'search' => $search,
        ]);
    }

    /** Create a new account and assign one of the defined UKK roles. */
    // Menyimpan akun pengguna baru yang dibuat oleh Admin.
    public function store(Request $request): RedirectResponse
    {
        // Validasi input pembuatan akun baru (ubah min:8 jika panjang minimal password ingin diganti).
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'whatsapp' => ['nullable', 'string', 'max:25'],
            'role_id' => ['required', 'exists:roles,id'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Menyimpan data user ke tabel users dengan password yang di-hash (dienkripsi).
        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'whatsapp' => $data['whatsapp'] ?? null,
            'role_id' => $data['role_id'],
            'password' => Hash::make($data['password']),
        ]);

        return redirect()->route('admin.users.index')->with('success', 'Akun berhasil dibuat.');
    }

    // Memperbarui data akun pengguna yang sudah ada.
    public function update(Request $request, User $user): RedirectResponse
    {
        // Validasi input edit user; email harus unik kecuali milik user yang sedang diedit sendiri.
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'whatsapp' => ['nullable', 'string', 'max:25'],
            'role_id' => ['required', 'exists:roles,id'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        // Jika kolom password dikosongkan saat edit, jangan ubah password lama.
        if (blank($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }
        $user->update($data);

        return redirect()->route('admin.users.index')->with('success', 'Akun berhasil diperbarui.');
    }

    // Menghapus akun pengguna (Admin tidak bisa menghapus akunnya sendiri yang sedang login).
    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user), 422, 'Akun yang sedang digunakan tidak dapat dihapus.');
        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'Akun berhasil dihapus.');
    }
}
