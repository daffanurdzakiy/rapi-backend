<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Services\SpreadsheetReaderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /**
     * Download template CSV/Spreadsheet untuk impor data pengguna massal.
     */
    public function downloadTemplate(): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="template-import-pengguna.csv"',
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');
            // Tulis UTF-8 BOM agar Excel membukanya dengan format encoding yang benar
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Baris Header
            fputcsv($handle, ['Nama Lengkap', 'Email', 'Peran', 'WhatsApp', 'Password']);

            // Baris Contoh Data
            fputcsv($handle, ['Budi Santoso', 'budi.santoso@sekolah.sch.id', 'Guru', '081234567890', 'rapi123']);
            fputcsv($handle, ['Siti Aminah', 'siti.aminah@sekolah.sch.id', 'Guru', '081234567891', 'rapi123']);
            fputcsv($handle, ['OSIS SMK', 'osis@sekolah.sch.id', 'Organisasi', '081234567892', 'rapi123']);
            fputcsv($handle, ['Ahmad Fauzi', '', 'Guru', '081234567893', '']);

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Mengimpor pengguna secara massal dari file Excel (.xlsx) atau Spreadsheet/CSV (.csv).
     */
    public function import(Request $request): RedirectResponse
    {
        $data = $request->validate([
                'import_file' => ['required', 'file', 'max:5120', 'extensions:csv,txt,xlsx'],
                'default_role_id' => ['nullable', 'exists:roles,id'],
                'default_password' => ['nullable', 'string', 'min:6'],
            ], [
                'import_file.required' => 'Pilih file Excel (.xlsx) atau CSV yang ingin diimpor.',
                'import_file.max' => 'Ukuran file maksimal 5 MB.',
                'import_file.mimes' => 'Format file harus berupa Excel (.xlsx) atau CSV (.csv).',
            ]);

            $file = $request->file('import_file');
            $realPath = $file->getRealPath();
            $originalName = $file->getClientOriginalName();

            try {
                $rows = app(SpreadsheetReaderService::class)->read($realPath, $originalName);
            } catch (\Throwable $e) {
                return back()->withErrors(['import_file' => 'Gagal membaca isi file: '.$e->getMessage()]);
            }

            if (empty($rows)) {
                return back()->withErrors(['import_file' => 'File kosong atau tidak ada data pengguna yang valid untuk diimpor.']);
            }

            // Ambil semua role dari database untuk pemetaan nama peran
            $roles = Role::all();
            $roleMap = [];
            foreach ($roles as $r) {
                $roleMap[strtolower(trim($r->role_name))] = $r->id;
            }

            $fallbackRoleId = $data['default_role_id'] ?? ($roleMap['guru'] ?? $roles->first()?->id);
            $defaultPassword = ! empty($data['default_password']) ? $data['default_password'] : 'rapi123';

            $importedCount = 0;
            $skipped = [];
            $existingEmails = User::pluck('email')->map(fn ($e) => strtolower($e))->flip()->toArray();

            foreach ($rows as $index => $row) {
                $name = trim($row['name'] ?? '');
                $email = strtolower(trim($row['email'] ?? ''));
                $roleInput = strtolower(trim($row['role'] ?? ''));
                $whatsapp = trim($row['whatsapp'] ?? '');
                $password = trim($row['password'] ?? '');

                // Jika nama kosong tetapi email ada, gunakan bagian sebelum @ sebagai nama
                if ($name === '' && $email !== '') {
                    $name = ucwords(str_replace(['.', '_', '-'], ' ', strstr($email, '@', true) ?: $email));
                }

                // Jika email kosong, buat email otomatis dari nama
                if ($email === '') {
                    $slug = Str::slug($name, '.');
                    $email = ($slug !== '' ? $slug : 'user.'.($index + 1)).'@rapi.sch.id';
                }

                // Cek duplikasi email
                if (isset($existingEmails[$email])) {
                    $skipped[] = "{$name} ({$email})";
                    continue;
                }

                // Tentukan peran (role)
                $roleId = $roleMap[$roleInput] ?? $fallbackRoleId;

                // Password
                $rawPass = $password !== '' ? $password : $defaultPassword;
                if (strlen($rawPass) < 6) {
                    $rawPass = 'rapi123';
                }

                User::create([
                    'name' => $name,
                    'email' => $email,
                    'whatsapp' => $whatsapp !== '' ? $whatsapp : null,
                    'role_id' => $roleId,
                    'password' => Hash::make($rawPass),
                ]);

                $existingEmails[$email] = true;
                $importedCount++;
            }

            $message = "Berhasil mengimpor {$importedCount} akun pengguna.";
            if (! empty($skipped)) {
                $skippedCount = count($skipped);
                $previewSkipped = implode(', ', array_slice($skipped, 0, 5));
                if ($skippedCount > 5) {
                    $previewSkipped .= ' dan '.($skippedCount - 5).' lainnya';
                }
                $message .= " ({$skippedCount} akun dilewati karena email sudah terdaftar: {$previewSkipped})";
            }

            return redirect()->route('admin.users.index')->with('success', $message);
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

