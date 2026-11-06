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
                    <div class="complaint-side">
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
                            <span style="display:inline-block; margin-top:4px; font-size:11px; color:#15803d; font-weight:600;">
                                Tampil 24 jam (diperbaiki {{ $complaint->updated_at->diffForHumans() }})
                            </span>
                        @endif
                        @if($complaint->photo_path)
                            @php($photoUrl = route('complaints.show-photo', $complaint, false))
                            <a href="{{ $photoUrl }}" target="_blank" rel="noopener" aria-label="Buka foto pengaduan ukuran penuh">
                                <img
                                    src="{{ $photoUrl }}"
                                    alt="Foto pengaduan {{ $complaint->room->room_name }}"
                                    style="display:block;max-width:220px;max-height:180px;object-fit:contain;border-radius:8px;border:1px solid #e8e1d3"
                                >
                            </a>
                        @else
                            <span class="muted" style="font-size:12px;">Foto belum dilampirkan</span>
                        @endif
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
                @if(auth()->user()->role?->role_name === 'PIC')
                    <form method="post" action="{{ route('complaints.status', $complaint) }}" style="margin-top:14px; padding:12px 14px; border-radius:8px; background:#f8fafc; border:1px solid #e2e8f0;">
                        @csrf
                        @method('PATCH')
                        <div style="font-size:12px; font-weight:700; color:#465a73; margin-bottom:8px;">
                            Perbarui Status Pengerjaan:
                        </div>
                        <div style="display:flex; flex-wrap:wrap; align-items:flex-start; gap:10px;">
                            <div style="flex:0 0 220px; min-width:180px;">
                                <label for="status_repair_{{ $complaint->id }}" style="margin:0 0 4px; font-size:11.5px; color:#6d7788;">Pilih Status</label>
                                <select id="status_repair_{{ $complaint->id }}" name="status_repair" class="status-repair-select" data-note-id="repair_note_wrap_{{ $complaint->id }}" style="width:100%; min-height:36px;">
                                    @foreach(['Belum Diperbaiki', 'Menunggu Spare Part', 'Sudah Diperbaiki'] as $status)
                                        <option value="{{ $status }}" @selected($status === $complaint->status_repair)>{{ $status }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div id="repair_note_wrap_{{ $complaint->id }}" class="repair-note-wrapper" style="flex:1 1 300px; min-width:240px; {{ $complaint->status_repair === 'Sudah Diperbaiki' ? '' : 'display:none;' }}">
                                <label for="repair_note_{{ $complaint->id }}" style="margin:0 0 4px; font-size:11.5px; color:#6d7788;">
                                    Keterangan Sudah Diperbaiki <span style="font-size:11px; color:#64748b; font-weight:normal;">(Opsional)</span>
                                </label>
                                <textarea id="repair_note_{{ $complaint->id }}" name="repair_note" rows="2" placeholder="Tuliskan keterangan perbaikan yang telah selesai dilakukan (opsional)..." style="width:100%; min-height:36px; resize:vertical; padding:6px 10px; font-size:12.5px;">{{ old('repair_note', $complaint->repair_note) }}</textarea>
                            </div>

                            <div style="align-self:flex-end;">
                                <button class="btn" style="min-height:36px; font-size:12.5px;">Perbarui Status</button>
                            </div>
                        </div>
                        @error('status_repair')<p class="error" style="margin-top:6px;">{{ $message }}</p>@enderror
                        @error('repair_note')<p class="error" style="margin-top:6px;">{{ $message }}</p>@enderror
                    </form>
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
        // Toggle dinamis kotak input 'Keterangan Sudah Diperbaiki' ketika PIC memilih status 'Sudah Diperbaiki'
        document.querySelectorAll('.status-repair-select').forEach(function (select) {
            select.addEventListener('change', function () {
                var noteWrapId = this.dataset.noteId;
                var noteWrap = document.getElementById(noteWrapId);
                if (!noteWrap) return;

                if (this.value === 'Sudah Diperbaiki') {
                    noteWrap.style.display = 'block';
                    var textarea = noteWrap.querySelector('textarea');
                    if (textarea) {
                        textarea.focus();
                    }
                } else {
                    noteWrap.style.display = 'none';
                }
            });
        });
    })();
</script>
@endsection
