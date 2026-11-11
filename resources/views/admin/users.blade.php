@extends('layouts.app')
@section('content')
<style>
    .users-toolbar { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:14px; margin:4px 0 18px; }
    .users-toolbar .card { margin:0; }
    .users-actions-group { display:flex; align-items:center; gap:8px; flex-wrap:wrap; flex:1 1 auto; }
    .add-user-card, .import-user-card { flex:0 0 auto; padding:0; margin:0; border:none; }
    .add-user-card[open], .import-user-card[open] { flex:1 1 100%; width:100%; }
    .add-user-card > summary, .import-user-card > summary { list-style:none; width:fit-content; }
    .add-user-card > summary::-webkit-details-marker, .import-user-card > summary::-webkit-details-marker { display:none; }
    .add-user-card form, .import-user-card form { width:100%; max-width:720px; margin-top:14px; }
    .user-search { display:flex; flex:0 1 380px; align-items:center; gap:8px; }
    .user-search input { min-width:0; }
    .user-search .btn { flex:0 0 auto; }
    .role-divider th { padding:11px 12px; border-bottom:1px solid #d7deea; background:linear-gradient(90deg, #f1f3f6, #fffdf8); color:#35465e; font-size:11px; letter-spacing:.08em; }
    .role-divider:not(:first-child) th { border-top:10px solid #f7f4eb; }
    .user-actions { display:flex; flex-wrap:wrap; align-items:flex-start; gap:8px; min-width:132px; }
    .user-actions > form { margin:0; }
    .user-actions .btn { min-height:36px; padding:8px 13px; }
    .user-edit-dialog { width:min(560px, calc(100% - 32px)); max-height:min(88vh, 760px); overflow:auto; margin:auto; padding:0; border:1px solid #e8e1d3; border-radius:18px; background:#fffdf8; color:#26364a; box-shadow:0 24px 80px rgba(38, 54, 74, .18); }
    .user-edit-dialog::backdrop { background:rgba(38, 54, 74, .32); backdrop-filter:blur(5px); }
    .user-edit-heading { display:flex; align-items:start; justify-content:space-between; gap:16px; padding:22px 23px 16px; border-bottom:1px solid #e8e1d3; }
    .user-edit-heading h2 { margin:0; color:#26364a; font-size:20px; }
    .user-edit-heading p { margin:5px 0 0; color:#6d7788; font-size:13px; }
    .user-edit-form { padding:0 23px 23px; }
    .user-edit-form .form-grid { gap:0 16px; }
    .user-edit-actions { display:flex; justify-content:flex-end; gap:9px; margin-top:20px; padding-top:15px; border-top:1px solid #e8e1d3; }
    @media (max-width:760px) {
        .users-toolbar { align-items:stretch; flex-direction:column; }
        .users-actions-group { width:100%; gap:6px; }
        .add-user-card form, .import-user-card form { min-width:0; }
        .user-search { flex:1 1 auto; }
        .user-actions { min-width:120px; gap:6px; }
        .user-actions .btn { min-height:34px; padding:7px 10px; font-size:12px; }
        .user-edit-form .form-grid { grid-template-columns:1fr; }
        .user-edit-heading, .user-edit-form { padding-right:18px; padding-left:18px; }
    }
</style>
<div class="heading">
    <div>
        <h1>Pengguna & Peran</h1>
        <p>Kelola akun, peran, dan akses pengguna.</p>
    </div>
</div>

@if ($errors->any())
    <div class="flash-alert flash-alert--error no-print" role="alert" data-flash-alert>
        <span class="flash-alert-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
        </span>
        <div class="flash-alert-body">
            <strong class="flash-alert-title">Perubahan belum disimpan. Periksa kembali data berikut:</strong>
            <ul class="flash-alert-list">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        <button type="button" class="flash-alert-close" aria-label="Tutup pemberitahuan" title="Tutup" data-flash-close>&times;</button>
    </div>
@endif

<div class="card users-toolbar">
    <div class="users-actions-group">
        <details class="add-user-card" @if ($errors->any() && !old('user_id') && !old('is_import')) open @endif>
            <summary class="btn">+ Tambah Pengguna</summary>
            <form method="post" action="{{ route('admin.users.store') }}">
            @csrf
            <div class="form-grid">
                <div>
                    <label>Nama lengkap</label>
                    <input name="name" value="{{ old('name') }}" required>
                </div>
                <div>
                    <label>Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" required>
                </div>
                <div>
                    <label>WhatsApp</label>
                    <input name="whatsapp" value="{{ old('whatsapp') }}">
                </div>
                <div>
                    <label>Peran</label>
                    <select name="role_id" required>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected((string) old('role_id') === (string) $role->id)>
                                {{ $role->role_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>Password</label>
                    <div class="password-field">
                        <input type="password" name="password" required>
                        <button type="button" class="password-toggle" aria-label="Tampilkan password">◉</button>
                    </div>
                </div>
                <div>
                    <label>Konfirmasi password</label>
                    <div class="password-field">
                        <input type="password" name="password_confirmation" required>
                        <button type="button" class="password-toggle" aria-label="Tampilkan password">◉</button>
                    </div>
                </div>
            </div>
            <div style="display:flex; align-items:center; gap:10px; margin-top:20px;">
                <button class="btn" type="submit">Simpan Pengguna</button>
                <button type="button" class="btn ghost" id="btn-cancel-add">Batal</button>
            </div>
            </form>
        </details>

        <details class="import-user-card" @if ($errors->has('import_file') || old('is_import')) open @endif>
            <summary class="btn ghost">Import File</summary>
            <form method="post" action="{{ route('admin.users.import') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="is_import" value="1">
                <div style="margin-bottom:14px; padding:12px 14px; border-radius:8px; background:#f0f7ff; border:1px solid #c7dffd; color:#1e3a8a; font-size:13px; line-height:1.5;">
                    <div style="font-weight:700; margin-bottom:4px; display:flex; align-items:center; gap:6px;">
                        <span>Upload File Excel / Spreadsheet</span>
                    </div>
                    <div>
                        Unggah file <b>Excel (.xlsx)</b> atau <b>CSV (.csv)</b>.
                    </div>
                    <div style="margin-top:8px;">
                        <a href="{{ route('admin.users.template') }}" class="btn ghost" style="min-height:30px; padding:3px 12px; font-size:12px; text-decoration:none; display:inline-flex; align-items:center; gap:5px;">
                            📄 Download Template CSV
                        </a>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="full">
                        <label for="import_file">Pilih File (.xlsx / .csv) <span style="color:#e11d48">*</span></label>
                        <input id="import_file" type="file" name="import_file" accept=".csv,.xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" required>
                        <span class="muted" style="font-size:12px; display:block; margin-top:4px;">Kolom yang didukung: <b>Nama Lengkap</b>, <b>Email</b>, <b>Peran</b> (Guru/Organisasi/PIC), <b>WhatsApp</b>, <b>Password</b></span>
                    </div>
                </div>

                <div style="display:flex; align-items:center; gap:10px; margin-top:18px;">
                    <button class="btn" type="submit">Unggah & Import Pengguna</button>
                    <button type="button" class="btn ghost" id="btn-cancel-import">Batal</button>
                </div>
            </form>
        </details>
    </div>
    <form class="user-search" method="GET" action="{{ route('admin.users.index') }}" role="search">
        <input type="search" name="q" value="{{ $search }}" placeholder="Cari nama, email, no. HP, atau peran..." aria-label="Cari pengguna">
        <button class="btn" type="submit">Cari</button>
        @if($search !== '')
            <a class="btn ghost" href="{{ route('admin.users.index') }}">Reset</a>
        @endif
    </form>
</div>

<div class="card">
    <div class="report-table-wrap">
    <table>
        <thead>
            <tr>
                <th>NAMA</th>
                <th>EMAIL</th>
                <th>NO. HP</th>
                <th>PERAN</th>
                <th>AKSI</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($users as $roleName => $roleUsers)
                <tr class="role-divider"><th colspan="5">{{ mb_strtoupper($roleName) }} <span class="muted">· {{ $roleUsers->count() }} pengguna</span></th></tr>
                @foreach ($roleUsers as $user)
                    @php($editingUser = (string) old('user_id') === (string) $user->id)
                    <tr>
                        <td><b>{{ $user->name }}</b></td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->whatsapp ?: '—' }}</td>
                        <td><span class="badge">{{ $user->role?->role_name }}</span></td>
                        <td>
                            <div class="user-actions">
                            <button class="btn ghost" type="button" data-user-dialog="user-edit-{{ $user->id }}">Edit</button>
                            <dialog class="user-edit-dialog" id="user-edit-{{ $user->id }}" aria-labelledby="user-edit-{{ $user->id }}-title">
                                <div class="user-edit-heading">
                                    <div>
                                        <h2 id="user-edit-{{ $user->id }}-title">Edit Pengguna</h2>
                                        <p>Perbarui informasi dan peran pengguna.</p>
                                    </div>
                                </div>
                                <form class="user-edit-form" method="post" action="{{ route('admin.users.update', $user) }}">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="user_id" value="{{ $user->id }}">
                                    <div class="form-grid">
                                        <div>
                                            <label>Nama lengkap</label>
                                            <input name="name" value="{{ $editingUser ? old('name', $user->name) : $user->name }}" required>
                                        </div>
                                        <div>
                                            <label>Email</label>
                                            <input type="email" name="email" value="{{ $editingUser ? old('email', $user->email) : $user->email }}" required>
                                        </div>
                                        <div>
                                            <label>WhatsApp</label>
                                            <input name="whatsapp" value="{{ $editingUser ? old('whatsapp', $user->whatsapp) : $user->whatsapp }}">
                                        </div>
                                        <div>
                                            <label>Peran</label>
                                            <select name="role_id" required>
                                                @foreach ($roles as $role)
                                                    <option value="{{ $role->id }}" @selected((string) ($editingUser ? old('role_id', $user->role_id) : $user->role_id) === (string) $role->id)>
                                                        {{ $role->role_name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label>Password baru</label>
                                            <div class="password-field">
                                                <input type="password" name="password">
                                                <button type="button" class="password-toggle" aria-label="Tampilkan password">◉</button>
                                            </div>
                                        </div>
                                        <div>
                                            <label>Konfirmasi password baru</label>
                                            <div class="password-field">
                                                <input type="password" name="password_confirmation">
                                                <button type="button" class="password-toggle" aria-label="Tampilkan password">◉</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="user-edit-actions">
                                        <button class="btn ghost" type="button" data-close-user-dialog>Batal</button>
                                        <button class="btn" type="submit">Simpan Perubahan</button>
                                    </div>
                                </form>
                            </dialog>
                            <form method="post" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Hapus akun ini?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn danger">Hapus</button>
                            </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            @empty
                <tr>
                    <td colspan="5">Belum ada pengguna.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    </div>
</div>
<script>
    document.querySelectorAll('[data-user-dialog]').forEach(function (button) {
        button.addEventListener('click', function () {
            var dialog = document.getElementById(button.dataset.userDialog);
            if (dialog) {
                dialog.showModal();
            }
        });
    });

    document.querySelectorAll('.user-edit-dialog').forEach(function (dialog) {
        dialog.addEventListener('click', function (event) {
            if (event.target === dialog || event.target.closest('[data-close-user-dialog]')) {
                dialog.close();
            }
        });
    });

    @if ($errors->any() && old('user_id'))
        document.getElementById('user-edit-{{ old('user_id') }}')?.showModal();
    @endif

    (function () {
        var addCard = document.querySelector('.add-user-card');
        var importCard = document.querySelector('.import-user-card');
        var searchForm = document.querySelector('.user-search');
        var btnCancelAdd = document.getElementById('btn-cancel-add');
        var btnCancelImport = document.getElementById('btn-cancel-import');

        function updateToolbarVisibility() {
            if (!addCard || !importCard || !searchForm) return;

            if (addCard.open) {
                importCard.style.display = 'none';
                searchForm.style.display = 'none';
            } else if (importCard.open) {
                addCard.style.display = 'none';
                searchForm.style.display = 'none';
            } else {
                addCard.style.display = '';
                importCard.style.display = '';
                searchForm.style.display = '';
            }
        }

        if (addCard) {
            addCard.addEventListener('toggle', updateToolbarVisibility);
        }
        if (importCard) {
            importCard.addEventListener('toggle', updateToolbarVisibility);
        }

        if (btnCancelAdd && addCard) {
            btnCancelAdd.addEventListener('click', function () {
                addCard.open = false;
                updateToolbarVisibility();
            });
        }

        if (btnCancelImport && importCard) {
            btnCancelImport.addEventListener('click', function () {
                importCard.open = false;
                updateToolbarVisibility();
            });
        }

        updateToolbarVisibility();
    })();
</script>
@endsection
