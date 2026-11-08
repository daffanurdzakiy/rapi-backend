@extends('layouts.app')

@section('content')
<style>
    .complaints-filter-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        margin: 18px 0 16px;
    }
    .filter-tab {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 7px 14px;
        border-radius: 99px;
        border: 1px solid #d7deea;
        background: #fffdf8;
        color: #465a73;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        transition: all .18s ease;
    }
    .filter-tab:hover {
        background: #f1f3f6;
        color: #26364a;
        border-color: #bdc9da;
    }
    .filter-tab.active {
        background: #35465e;
        color: #fffdf8;
        border-color: #35465e;
        box-shadow: 0 3px 10px rgba(53, 70, 94, .18);
    }
    .filter-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 20px;
        height: 20px;
        padding: 0 6px;
        border-radius: 99px;
        font-size: 11px;
        font-weight: 700;
        background: #e8edf4;
        color: #35465e;
    }
    .filter-tab.active .filter-count {
        background: rgba(255, 255, 255, .25);
        color: #fff;
    }

    .badge-status-repaired {
        background: #dcfce7;
        border-color: #86efac;
        color: #166534;
    }
    .badge-status-waiting {
        background: #fef3c7;
        border-color: #fde68a;
        color: #92400e;
    }
    .badge-status-pending {
        background: #fee2e2;
        border-color: #fca5a5;
        color: #991b1b;
    }
</style>

    <div class="heading">
        <div>
            <h1>Pengaduan Fasilitas</h1>
            <p>PIC hanya melihat dan menangani pengaduan dari ruangan yang menjadi tanggung jawabnya.</p>
        </div>
        @if($canReport)
            <a class="btn" href="{{ route('complaints.create') }}">+ Buat Pengaduan</a>
        @endif
    </div>

    {{-- Filter Status Pengaduan: Semua, Belum Diperbaiki, Menunggu Spare Part, Sudah Diperbaiki --}}
    <div class="complaints-filter-bar">
        <a href="{{ route('complaints.index', ['status' => 'semua']) }}" 
           class="filter-tab {{ ($currentFilter ?? 'semua') === 'semua' ? 'active' : '' }}">
            Semua <span class="filter-count">{{ $counts['semua'] ?? 0 }}</span>
        </a>
        <a href="{{ route('complaints.index', ['status' => 'belum_diperbaiki']) }}" 
           class="filter-tab {{ ($currentFilter ?? '') === 'belum_diperbaiki' ? 'active' : '' }}">
            Belum Diperbaiki <span class="filter-count">{{ $counts['belum_diperbaiki'] ?? 0 }}</span>
        </a>
        <a href="{{ route('complaints.index', ['status' => 'menunggu_spare_part']) }}" 
           class="filter-tab {{ ($currentFilter ?? '') === 'menunggu_spare_part' ? 'active' : '' }}">
            Menunggu Spare Part <span class="filter-count">{{ $counts['menunggu_spare_part'] ?? 0 }}</span>
        </a>
        <a href="{{ route('complaints.index', ['status' => 'sudah_diperbaiki']) }}" 
           class="filter-tab {{ ($currentFilter ?? '') === 'sudah_diperbaiki' ? 'active' : '' }}"
           title="Pengaduan yang sudah diperbaiki akan tampil selama 24 jam sebelum otomatis diarsipkan">
            Sudah Diperbaiki <span class="filter-count">{{ $counts['sudah_diperbaiki'] ?? 0 }}</span>
        </a>
    </div>

    <div class="card">
        @forelse($complaints as $complaint)
            <div class="list complaint-item">
                <div class="heading complaint-heading">
                    <div style="flex:1 1 auto; min-width:0;">
                        <h3>{{ $complaint->room->room_name }}</h3>
                        <p>
                            <span style="font-size:14px; color:#26364a; font-weight:500;">{{ $complaint->description }}</span><br>
                            <span class="muted" style="display:inline-block; margin-top:4px; font-size:12px;">
                                Pelapor: <b>{{ $complaint->reporter->name }}</b> · PIC Ruangan: <b>{{ $complaint->room->pic->name }}</b> · Dilaporkan: {{ $complaint->created_at->format('d M Y, H:i') }}
                            </span>
                        </p>

                        {{-- Tampilan Keterangan Sudah Diperbaiki --}}
                        @if($complaint->repair_note)
                            <div style="margin-top:10px; padding:10px 14px; border-radius:8px; background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; font-size:13px; line-height:1.5;">
                                <div style="font-weight:700; display:flex; align-items:center; gap:6px; margin-bottom:3px;">
                                    Keterangan Sudah Diperbaiki:
                                </div>
                                <div style="color:#14532d; white-space:pre-wrap;">{{ $complaint->repair_note }}</div>
                            </div>
                        @endif
                    </div>
                    <div class="complaint-side" style="display:flex; flex-direction:column; align-items:flex-end; gap:8px;">
                        @php
                            $badgeClass = match($complaint->status_repair) {
                                'Sudah Diperbaiki' => 'badge-status-repaired',
                                'Menunggu Spare Part' => 'badge-status-waiting',
                                default => 'badge-status-pending',
                            };
                        @endphp
                        <span class="badge {{ $badgeClass }}">
                            {{ $complaint->status_repair }}
                        </span>
                        @if($complaint->status_repair === 'Sudah Diperbaiki')
                            <span style="display:inline-block; font-size:11px; color:#15803d; font-weight:600;">
                                Tampil 24 jam (diperbaiki {{ $complaint->updated_at->diffForHumans() }})
                            </span>
                        @endif

                        {{-- Tampilan Foto: Bukti Kerusakan di Kiri & Bukti Perbaikan di Kanan --}}
                        <div style="display:flex; flex-wrap:wrap; justify-content:flex-end; gap:12px; margin-top:4px;">
                            @if($complaint->photo_path)
                                @php($photoUrl = route('complaints.show-photo', $complaint, false))
                                <div style="display:flex; flex-direction:column; align-items:center; gap:4px;">
                                    <span style="font-size:10.5px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.4px;">Bukti Kerusakan</span>
                                    <a href="{{ $photoUrl }}" target="_blank" rel="noopener" aria-label="Buka foto bukti kerusakan">
                                        <img
                                            src="{{ $photoUrl }}"
                                            alt="Foto kerusakan {{ $complaint->room->room_name }}"
                                            style="display:block;width:150px;height:120px;object-fit:cover;border-radius:8px;border:1px solid #e8e1d3;"
                                        >
                                    </a>
                                </div>
                            @endif

                            @if($complaint->repair_photo_path)
                                @php($repairPhotoUrl = route('complaints.show-repair-photo', $complaint, false))
                                <div style="display:flex; flex-direction:column; align-items:center; gap:4px;">
                                    <span style="font-size:10.5px; font-weight:700; color:#166534; text-transform:uppercase; letter-spacing:0.4px;">Bukti Perbaikan</span>
                                    <a href="{{ $repairPhotoUrl }}" target="_blank" rel="noopener" aria-label="Buka foto bukti perbaikan">
                                        <img
                                            src="{{ $repairPhotoUrl }}"
                                            alt="Foto perbaikan {{ $complaint->room->room_name }}"
                                            style="display:block;width:150px;height:120px;object-fit:cover;border-radius:8px;border:2px solid #86efac;"
                                        >
                                    </a>
                                </div>
                            @endif

                            @if(!$complaint->photo_path && !$complaint->repair_photo_path)
                                <span class="muted" style="font-size:12px;">Foto belum dilampirkan</span>
                            @endif
                        </div>
                    </div>
                </div>

                @if(!$complaint->photo_path && (auth()->user()->role?->role_name === 'Admin' || (int) $complaint->reporter_id === (int) auth()->id()))
                    <form method="post" action="{{ route('complaints.photo', $complaint) }}" enctype="multipart/form-data" style="margin:12px 0">
                        @csrf
                        @method('PATCH')
                        <label for="photo-{{ $complaint->id }}">Tambahkan foto bukti (maksimal 5 MB)</label>
                        <input id="photo-{{ $complaint->id }}" type="file" name="photo" accept="image/jpeg,image/png,image/webp" required>
                        @error('photo')<p class="error">{{ $message }}</p>@enderror
                        <button class="btn" style="margin-top:10px">Simpan Foto</button>
                    </form>
                @endif

                {{-- Formulir Pembaruan Status oleh PIC --}}
                @if(auth()->user()->role?->role_name === 'PIC' && (int) $complaint->room->pic_id === (int) auth()->id())
                    @if($complaint->status_repair === 'Sudah Diperbaiki')
                        <div style="margin-top:14px; padding:10px 14px; border-radius:8px; background:#f0fdf4; border:1px solid #bbf7d0; display:flex; align-items:center; gap:8px;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                            <span style="font-size:12.5px; font-weight:600; color:#166534;">Perbaikan fasilitas telah selesai dan status telah terkunci permanen.</span>
                        </div>
                    @else
                        <form method="post" action="{{ route('complaints.status', $complaint) }}" enctype="multipart/form-data" style="margin-top:14px; padding:12px 14px; border-radius:8px; background:#f8fafc; border:1px solid #e2e8f0;">
                            @csrf
                            @method('PATCH')
                            <div style="font-size:12px; font-weight:700; color:#465a73; margin-bottom:8px;">
                                Perbarui Status Pengerjaan:
                            </div>
                            <div style="display:flex; flex-wrap:wrap; align-items:flex-start; gap:10px;">
                                <div style="flex:0 0 220px; min-width:180px;">
                                    <label for="status_repair_{{ $complaint->id }}" style="margin:0 0 4px; font-size:11.5px; color:#6d7788;">Pilih Status</label>
                                    <select id="status_repair_{{ $complaint->id }}" name="status_repair" class="status-repair-select" data-note-id="repair_note_wrap_{{ $complaint->id }}" data-photo-id="repair_photo_wrap_{{ $complaint->id }}" style="width:100%; min-height:36px;">
                                        @if($complaint->status_repair === 'Belum Diperbaiki')
                                            <option value="Belum Diperbaiki" selected>Belum Diperbaiki</option>
                                            <option value="Menunggu Spare Part">Menunggu Spare Part</option>
                                            <option value="Sudah Diperbaiki">Sudah Diperbaiki</option>
                                        @elseif($complaint->status_repair === 'Menunggu Spare Part')
                                            <option value="Menunggu Spare Part" selected>Menunggu Spare Part</option>
                                            <option value="Sudah Diperbaiki">Sudah Diperbaiki</option>
                                        @endif
                                    </select>
                                </div>

                                <div id="repair_note_wrap_{{ $complaint->id }}" class="repair-note-wrapper" style="flex:1 1 260px; min-width:220px; display:none;">
                                    <label for="repair_note_{{ $complaint->id }}" style="margin:0 0 4px; font-size:11.5px; color:#6d7788;">
                                        Keterangan Selesai <span style="font-size:11px; color:#64748b; font-weight:normal;">(Opsional)</span>
                                    </label>
                                    <textarea id="repair_note_{{ $complaint->id }}" name="repair_note" rows="2" placeholder="Tuliskan keterangan perbaikan..." style="width:100%; min-height:36px; resize:vertical; padding:6px 10px; font-size:12.5px;">{{ old('repair_note', $complaint->repair_note) }}</textarea>
                                </div>

                                <div id="repair_photo_wrap_{{ $complaint->id }}" class="repair-photo-wrapper" style="flex:1 1 240px; min-width:200px; display:none;">
                                    <label for="repair_photo_{{ $complaint->id }}" style="margin:0 0 4px; font-size:11.5px; color:#6d7788;">
                                        Bukti Foto Selesai <span style="font-size:11px; color:#64748b; font-weight:normal;">(Opsional, maks 5 MB)</span>
                                    </label>
                                    <input id="repair_photo_{{ $complaint->id }}" type="file" name="repair_photo" accept="image/jpeg,image/png,image/webp" style="width:100%; font-size:12px;">
                                </div>

                                <div style="align-self:flex-end;">
                                    <button class="btn" style="min-height:36px; font-size:12.5px;">Perbarui Status</button>
                                </div>
                            </div>
                            @error('status_repair')<p class="error" style="margin-top:6px;">{{ $message }}</p>@enderror
                            @error('repair_note')<p class="error" style="margin-top:6px;">{{ $message }}</p>@enderror
                            @error('repair_photo')<p class="error" style="margin-top:6px;">{{ $message }}</p>@enderror
                        </form>
                    @endif
                @endif

                @if(isset($whatsappWarnings[$complaint->id]))
                    <div style="margin-top:12px;">
                        <a
                            class="btn warn"
                            target="_blank"
                            rel="noopener"
                            href="{{ $whatsappWarnings[$complaint->id] }}"
                        >Kirim Peringatan WhatsApp ke PIC</a>
                    </div>
                @endif
            </div>
        @empty
            <div style="text-align:center; padding:32px 16px;">
                <p class="muted" style="margin:0; font-size:14px;">
                    @if(($currentFilter ?? 'semua') === 'sudah_diperbaiki')
                        Tidak ada pengaduan yang sudah diperbaiki dalam kurun waktu 24 jam terakhir.
                    @elseif(($currentFilter ?? 'semua') !== 'semua')
                        Tidak ada pengaduan dengan status <b>{{ $currentFilter === 'belum_diperbaiki' ? 'Belum Diperbaiki' : 'Menunggu Spare Part' }}</b>.
                    @else
                        Belum ada pengaduan fasilitas aktif.
                    @endif
                </p>
                @if(($currentFilter ?? 'semua') !== 'semua')
                    <div style="margin-top:10px;">
                        <a href="{{ route('complaints.index') }}" class="btn ghost" style="min-height:32px; font-size:12px;">Tampilkan Semua Pengaduan</a>
                    </div>
                @endif
            </div>
        @endforelse
    </div>

<script>
    (function () {
        // Toggle dinamis kotak input 'Keterangan Selesai' dan 'Bukti Foto Selesai' ketika PIC memilih status 'Sudah Diperbaiki'
        document.querySelectorAll('.status-repair-select').forEach(function (select) {
            select.addEventListener('change', function () {
                var noteWrap = document.getElementById(this.dataset.noteId);
                var photoWrap = document.getElementById(this.dataset.photoId);
                var isRepaired = this.value === 'Sudah Diperbaiki';

                if (noteWrap) {
                    noteWrap.style.display = isRepaired ? 'block' : 'none';
                    if (isRepaired) {
                        var textarea = noteWrap.querySelector('textarea');
                        if (textarea) textarea.focus();
                    }
                }
                if (photoWrap) {
                    photoWrap.style.display = isRepaired ? 'block' : 'none';
                }
            });
        });
    })();
</script>
@endsection
