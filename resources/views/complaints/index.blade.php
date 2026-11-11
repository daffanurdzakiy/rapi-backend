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

    .complaint-side-panel {
        display: flex;
        flex: 0 0 auto;
        flex-direction: column;
        align-items: flex-end;
        gap: 8px;
    }
    .complaint-photos-wrap {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 12px;
        margin-top: 4px;
    }
    .complaint-photo-thumb {
        display: block;
        width: 150px;
        height: 120px;
        object-fit: cover;
        border-radius: 8px;
        border: 1px solid #e8e1d3;
    }
    .complaint-photo-thumb.repaired {
        border: 2px solid #86efac;
    }

    @media (max-width: 800px) {
        .complaint-side-panel {
            align-items: flex-start;
            width: 100%;
        }
        .complaint-photos-wrap {
            justify-content: flex-start;
            width: 100%;
        }
        .complaint-photo-thumb {
            width: min(148px, calc(50vw - 38px));
            height: 112px;
        }
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
            <div id="complaint-{{ $complaint->id }}" class="list complaint-item {{ (int) request('highlight') === (int) $complaint->id ? 'highlight-target' : '' }}">
                <div class="heading complaint-heading">
                    <div style="flex:1 1 auto; min-width:0;">
                        <h3>{{ $complaint->room->room_name }}</h3>
                        <p>
                            <span style="font-size:14px; color:#26364a; font-weight:500;">{{ $complaint->description }}</span><br>
                            <span class="muted" style="display:inline-block; margin-top:4px; font-size:12px;">
                                Pelapor: <b>{{ $complaint->reporter->name }}</b> · PIC Ruangan: <b>{{ $complaint->room->pic?->name ?? 'Belum ditugaskan' }}</b> · Dilaporkan: {{ $complaint->created_at->format('d M Y, H:i') }}
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
                    <div class="complaint-side complaint-side-panel">
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
                        <div class="complaint-photos-wrap">
                            @if($complaint->photo_path)
                                @php($photoUrl = route('complaints.show-photo', $complaint, false))
                                <div style="display:flex; flex-direction:column; align-items:center; gap:4px;">
                                    <span style="font-size:10.5px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.4px;">Bukti Kerusakan</span>
                                    <a href="{{ $photoUrl }}" target="_blank" rel="noopener" aria-label="Buka foto bukti kerusakan">
                                        <img
                                            src="{{ $photoUrl }}"
                                            alt="Foto kerusakan {{ $complaint->room->room_name }}"
                                            class="complaint-photo-thumb"
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
                                            class="complaint-photo-thumb repaired"
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
                @if(auth()->user()->role?->role_name === 'PIC' && (int) $complaint->room->pic_id === (int) auth()->id() && $complaint->status_repair !== 'Sudah Diperbaiki')
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

                            <div id="repair_photo_wrap_{{ $complaint->id }}" class="repair-photo-wrapper" style="flex:1 1 100%; display:none; margin-top:8px;">
                                <label style="margin:0 0 6px; font-size:12px; font-weight:600; color:#465a73;">
                                    Bukti Foto Selesai <span style="font-size:11px; color:#64748b; font-weight:normal;">(Opsional, otomatis dikompres)</span>
                                </label>
                                <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                                    <button type="button" class="btn ghost btn-choose-repair-file" data-id="{{ $complaint->id }}" style="min-height:34px; font-size:12px; padding:4px 12px;">
                                        📁 Pilih dari File
                                    </button>
                                    <button type="button" class="btn btn-open-repair-camera" data-id="{{ $complaint->id }}" style="min-height:34px; font-size:12px; padding:4px 12px; background:#1e293b; color:#fff;">
                                        📷 Ambil dari Kamera
                                    </button>
                                </div>
                                <input id="repair_photo_{{ $complaint->id }}" type="file" name="repair_photo" accept="image/*" capture="environment" hidden class="repair-photo-input" data-id="{{ $complaint->id }}">

                                <div id="repair_camera_panel_{{ $complaint->id }}" hidden style="margin-top:10px; padding:10px; background:#0f172a; border-radius:10px; max-width:440px;">
                                    <video id="repair_camera_preview_{{ $complaint->id }}" autoplay playsinline style="display:block; width:100%; max-height:280px; background:#000; border-radius:6px; object-fit:contain;"></video>
                                    <div style="display:flex; gap:8px; margin-top:8px;">
                                        <button type="button" class="btn btn-take-repair-photo" data-id="{{ $complaint->id }}" style="flex:1; background:#16a34a; color:#fff; min-height:34px; font-size:12px;">📸 Jepret Foto</button>
                                        <button type="button" class="btn ghost btn-close-repair-camera" data-id="{{ $complaint->id }}" style="color:#f8fafc; border-color:#475569; min-height:34px; font-size:12px;">Tutup Kamera</button>
                                    </div>
                                    <p id="repair_camera_error_{{ $complaint->id }}" class="error" style="margin-top:6px; font-size:11.5px; color:#fca5a5;" hidden></p>
                                </div>

                                <div id="repair_preview_wrap_{{ $complaint->id }}" hidden style="margin-top:8px; display:flex; align-items:center; gap:10px;">
                                    <img id="repair_photo_preview_{{ $complaint->id }}" alt="Pratinjau foto perbaikan" style="width:72px; height:72px; object-fit:cover; border-radius:8px; border:2px solid #86efac;">
                                    <div>
                                        <p id="repair_photo_status_{{ $complaint->id }}" style="margin:0; font-size:11.5px; color:#166534; font-weight:600;"></p>
                                        <button type="button" class="btn ghost btn-remove-repair-photo" data-id="{{ $complaint->id }}" style="min-height:26px; padding:2px 8px; font-size:11px; color:#dc2626; border-color:#fca5a5; margin-top:4px;">Hapus Foto</button>
                                    </div>
                                </div>
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

        // ========================================================
        // KAMERA LANGSUNG & KOMPRESI OTOMATIS BUKTI PERBAIKAN (DESKTOP)
        // ========================================================
        var activeStreams = {};

        function formatBytes(bytes) {
            if (!bytes || bytes <= 0) return '0 B';
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / 1048576).toFixed(2) + ' MB';
        }

        function compressImageFile(file, onComplete) {
            if (!window.FileReader || !window.HTMLCanvasElement || !file.type.startsWith('image/')) {
                onComplete(file, file.size, file.size);
                return;
            }

            var reader = new FileReader();
            reader.onerror = function () { onComplete(file, file.size, file.size); };
            reader.onload = function (e) {
                var img = new Image();
                img.onerror = function () { onComplete(file, file.size, file.size); };
                img.onload = function () {
                    try {
                        var maxDim = 1200;
                        var width = img.naturalWidth || img.width;
                        var height = img.naturalHeight || img.height;
                        var scale = Math.min(1, maxDim / width, maxDim / height);
                        var targetWidth = Math.max(1, Math.round(width * scale));
                        var targetHeight = Math.max(1, Math.round(height * scale));

                        var canvas = document.createElement('canvas');
                        canvas.width = targetWidth;
                        canvas.height = targetHeight;
                        var ctx = canvas.getContext('2d');
                        ctx.fillStyle = '#ffffff';
                        ctx.fillRect(0, 0, targetWidth, targetHeight);
                        ctx.drawImage(img, 0, 0, targetWidth, targetHeight);

                        canvas.toBlob(function (blob) {
                            if (!blob) {
                                onComplete(file, file.size, file.size);
                                return;
                            }
                            var baseName = file.name ? file.name.replace(/\.[^/.]+$/, '') : 'bukti_perbaikan';
                            var compressedFile = new File([blob], baseName + '.jpg', { type: 'image/jpeg' });
                            onComplete(compressedFile, file.size, blob.size);
                        }, 'image/jpeg', 0.75);
                    } catch (err) {
                        onComplete(file, file.size, file.size);
                    }
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }

        function setRepairPhoto(id, file, origSize, compSize) {
            var input = document.getElementById('repair_photo_' + id);
            var previewWrap = document.getElementById('repair_preview_wrap_' + id);
            var previewImg = document.getElementById('repair_photo_preview_' + id);
            var statusTxt = document.getElementById('repair_photo_status_' + id);

            if (!input || !previewWrap || !previewImg || !statusTxt) return;

            try {
                var dt = new DataTransfer();
                dt.items.add(file);
                input.files = dt.files;
            } catch (e) {
                // Fallback jika DataTransfer tidak didukung
            }

            if (previewImg.dataset.objectUrl) {
                URL.revokeObjectURL(previewImg.dataset.objectUrl);
            }
            var objUrl = URL.createObjectURL(file);
            previewImg.src = objUrl;
            previewImg.dataset.objectUrl = objUrl;

            if (origSize && compSize && origSize > compSize) {
                var pct = Math.round(((origSize - compSize) / origSize) * 100);
                statusTxt.innerHTML = '⚡ Foto siap (' + formatBytes(compSize) + ') <span style="display:inline-block; padding:1px 6px; border-radius:4px; background:#ecfdf5; color:#065f46; font-size:10.5px;">Hemat ' + pct + '%</span>';
            } else {
                statusTxt.textContent = 'Foto siap (' + formatBytes(file.size) + ')';
            }

            previewWrap.hidden = false;
        }

        function stopRepairCamera(id) {
            if (activeStreams[id]) {
                activeStreams[id].getTracks().forEach(function (t) { t.stop(); });
                delete activeStreams[id];
            }
            var panel = document.getElementById('repair_camera_panel_' + id);
            if (panel) panel.hidden = true;
        }

        // Tombol Pilih File
        document.querySelectorAll('.btn-choose-repair-file').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = this.dataset.id;
                stopRepairCamera(id);
                var input = document.getElementById('repair_photo_' + id);
                if (input) input.click();
            });
        });

        // Input file change -> otomatis kompres
        document.querySelectorAll('.repair-photo-input').forEach(function (input) {
            input.addEventListener('change', function () {
                var id = this.dataset.id;
                if (!this.files || !this.files.length) return;
                var file = this.files[0];
                var statusTxt = document.getElementById('repair_photo_status_' + id);
                var previewWrap = document.getElementById('repair_preview_wrap_' + id);
                if (statusTxt && previewWrap) {
                    statusTxt.textContent = 'Mengompres foto otomatis...';
                    previewWrap.hidden = false;
                }
                compressImageFile(file, function (compFile, origSize, compSize) {
                    setRepairPhoto(id, compFile, origSize, compSize);
                });
            });
        });

        // Tombol Buka Kamera
        document.querySelectorAll('.btn-open-repair-camera').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                var id = this.dataset.id;
                var errEl = document.getElementById('repair_camera_error_' + id);
                var panel = document.getElementById('repair_camera_panel_' + id);
                var video = document.getElementById('repair_camera_preview_' + id);
                if (errEl) errEl.hidden = true;

                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    if (errEl) {
                        errEl.textContent = 'Akses kamera langsung membutuhkan HTTPS atau localhost. Silakan gunakan tombol "Pilih dari File".';
                        errEl.hidden = false;
                    }
                    return;
                }

                stopRepairCamera(id);

                try {
                    var stream = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: { ideal: 'environment' } },
                        audio: false
                    });
                    activeStreams[id] = stream;
                    if (video) video.srcObject = stream;
                    if (panel) panel.hidden = false;
                } catch (err) {
                    if (errEl) {
                        errEl.textContent = err.name === 'NotAllowedError'
                            ? 'Izin kamera ditolak. Berikan izin kamera di browser untuk melanjutkan.'
                            : 'Kamera tidak dapat diakses. Pastikan kamera tidak sedang dipakai aplikasi lain.';
                        errEl.hidden = false;
                    }
                    if (panel) panel.hidden = false;
                }
            });
        });

        // Tombol Jepret Foto Kamera
        document.querySelectorAll('.btn-take-repair-photo').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = this.dataset.id;
                var stream = activeStreams[id];
                var video = document.getElementById('repair_camera_preview_' + id);
                var errEl = document.getElementById('repair_camera_error_' + id);

                if (!stream || !video || !video.videoWidth) {
                    if (errEl) {
                        errEl.textContent = 'Kamera belum siap, tunggu sebentar lalu coba lagi.';
                        errEl.hidden = false;
                    }
                    return;
                }

                var canvas = document.createElement('canvas');
                var maxDim = 1200;
                var scale = Math.min(1, maxDim / video.videoWidth, maxDim / video.videoHeight);
                canvas.width = Math.round(video.videoWidth * scale);
                canvas.height = Math.round(video.videoHeight * scale);
                canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);

                canvas.toBlob(function (blob) {
                    if (!blob) return;
                    var file = new File([blob], 'bukti_perbaikan_kamera.jpg', { type: 'image/jpeg' });
                    setRepairPhoto(id, file, blob.size * 2, blob.size);
                    stopRepairCamera(id);
                }, 'image/jpeg', 0.75);
            });
        });

        // Tombol Tutup Kamera
        document.querySelectorAll('.btn-close-repair-camera').forEach(function (btn) {
            btn.addEventListener('click', function () {
                stopRepairCamera(this.dataset.id);
            });
        });

        // Tombol Hapus Foto
        document.querySelectorAll('.btn-remove-repair-photo').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = this.dataset.id;
                var input = document.getElementById('repair_photo_' + id);
                var previewWrap = document.getElementById('repair_preview_wrap_' + id);
                if (input) input.value = '';
                if (previewWrap) previewWrap.hidden = true;
            });
        });
    })();
</script>
@endsection
