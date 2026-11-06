@extends('layouts.app')

@section('content')
<style>
    .report-filter { display:flex; flex-wrap:wrap; align-items:end; gap:14px; }
    .report-filter label { margin-top:0; }
    .report-filter .filter-field { min-width:190px; }
    .report-table-wrap { overflow-x:auto; }
    .report-table { min-width:760px; }
    .report-table th, .report-table td { padding:12px 10px; }
    .report-section-title { margin:0 0 14px; }
    .report-count { margin-left:8px; color:var(--muted); font-size:14px; font-weight:normal; }
    .report-period-caption { margin:0 0 14px; }
    @media print {
        @page { size:landscape; margin:14mm; }
        :root, body { background:#fff !important; color:#111 !important; }
        .side, .top, .report-filter-card, .no-print, .success, #photo-modal { display:none !important; }
        .layout, .main, .content { display:block !important; width:100% !important; max-width:none !important; margin:0 !important; padding:0 !important; }
        .report-heading { margin-bottom:16px; }
        .report-heading h1 { font-size:22px; }
        .report-heading p { margin:5px 0; font-size:12px; }
        .card { margin:0 0 14px !important; padding:14px !important; border:1px solid #bbb !important; border-radius:0 !important; box-shadow:none !important; break-inside:avoid; }
        .report-table-wrap { overflow:visible; }
        .report-table { min-width:0; font-size:10px; }
        .report-table th, .report-table td { padding:6px 5px; border-bottom:1px solid #ccc; }
        .report-section-title { font-size:15px; }
        .evidence-column { display:none !important; }
    }
</style>

<div class="heading report-heading">
    <div>
        <h1>Laporan Kerusakan/Pengaduan</h1>
        <p>Ringkasan pengaduan fasilitas pada ruangan yang menjadi tanggung jawab Anda.</p>
        @if($periode)
            <p class="muted">Periode: <b>{{ $awal->format('d/m/Y') }} – {{ $akhir->format('d/m/Y') }}</b></p>
        @else
            <p class="muted">Pilih jenis periode dan tanggal untuk menampilkan laporan.</p>
        @endif
    </div>
    @if($periode)
        <button class="btn no-print" type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    @endif
</div>

<div class="card report-filter-card">
    <form class="report-filter" method="GET" action="{{ route('pic.complaint-reports.index') }}">
        <div class="filter-field">
            <label for="periode">Jenis Periode</label>
            <select id="periode" name="periode" required>
                <option value="" disabled @selected(!$periode)>Pilih jenis periode</option>
                <option value="harian" @selected($periode === 'harian')>Harian</option>
                <option value="mingguan" @selected($periode === 'mingguan')>Mingguan</option>
                <option value="bulanan" @selected($periode === 'bulanan')>Bulanan</option>
            </select>
        </div>
        <div class="filter-field">
            <label for="tanggal">Pilih tanggal acuan</label>
            <input id="tanggal" name="tanggal" type="date" value="{{ $tanggal->toDateString() }}" required>
        </div>
        <button class="btn" type="submit">Tampilkan Laporan</button>
    </form>
</div>

@if($periode)
<div class="card">
    <h2 class="report-section-title">Rincian Pengaduan <span class="report-count">{{ $complaints->count() }} pengaduan</span></h2>
    <p class="muted report-period-caption">
        Laporan {{ ucfirst($periode) }} yang dipilih:
        <b>{{ $awal->format('d/m/Y') }} – {{ $akhir->format('d/m/Y') }}</b>
    </p>

    <div class="report-table-wrap">
        <table class="report-table">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Waktu</th>
                    <th>Ruangan</th>
                    <th>Kerusakan/Pengaduan</th>
                    <th>Pelapor</th>
                    <th>Status</th>
                    <th class="evidence-column">Bukti</th>
                </tr>
            </thead>
            <tbody>
                @forelse($complaints as $complaint)
                    <tr>
                        <td>{{ $complaint->created_at->format('d/m/Y') }}</td>
                        <td>{{ $complaint->created_at->format('H:i') }}</td>
                        <td>{{ $complaint->room->room_name }}</td>
                        <td>
                            <div>{{ $complaint->description }}</div>
                            @if($complaint->repair_note)
                                <div style="margin-top:4px; font-size:11.5px; color:#166534; font-weight:600;">
                                    Keterangan Perbaikan: {{ $complaint->repair_note }}
                                </div>
                            @endif
                        </td>
                        <td>{{ $complaint->reporter->name }}</td>
                        <td>{{ $complaint->status_repair }}</td>
                        <td class="evidence-column" style="vertical-align:middle;">
                            @if($complaint->photo_path)
                                @php($photoUrl = route('pic.complaint-reports.photo', $complaint, false))
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <button
                                        type="button"
                                        class="open-photo-modal"
                                        data-photo-url="{{ $photoUrl }}"
                                        data-room="{{ $complaint->room->room_name }}"
                                        data-desc="{{ $complaint->description }}"
                                        title="Klik untuk melihat foto lebih besar"
                                        style="border:none; background:none; padding:0; cursor:pointer; display:block;"
                                    >
                                        <img
                                            src="{{ $photoUrl }}"
                                            alt="Foto bukti {{ $complaint->room->room_name }}"
                                            style="width:50px; height:50px; object-fit:cover; border-radius:6px; border:1px solid #cbd5e1; box-shadow:0 1px 3px rgba(0,0,0,0.08); transition:transform 0.15s ease;"
                                            onmouseover="this.style.transform='scale(1.08)'"
                                            onmouseout="this.style.transform='scale(1)'"
                                        >
                                    </button>
                                    <div>
                                        <button
                                            type="button"
                                            class="open-photo-modal"
                                            data-photo-url="{{ $photoUrl }}"
                                            data-room="{{ $complaint->room->room_name }}"
                                            data-desc="{{ $complaint->description }}"
                                            style="border:none; background:none; padding:0; font-size:12px; color:var(--accent, #1d4ed8); cursor:pointer; text-decoration:underline; font-weight:600; display:block; text-align:left;"
                                        >
                                            Lihat foto
                                        </button>
                                        <a
                                            href="{{ $photoUrl }}"
                                            target="_blank"
                                            rel="noopener"
                                            style="font-size:11px; color:#64748b; text-decoration:none; display:inline-block; margin-top:2px;"
                                            title="Buka foto ukuran penuh di tab baru"
                                        >
                                            Tab baru ↗
                                        </a>
                                    </div>
                                </div>
                            @else
                                <span class="muted" style="font-size:12px;">Tidak ada foto</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">Tidak ada pengaduan pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Modal Pratinjau Foto Bukti Pengaduan Langsung di Layar --}}
<div id="photo-modal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.7); z-index:9999; align-items:center; justify-content:center; padding:16px;" role="dialog" aria-modal="true" aria-labelledby="modal-room-title">
    <div style="background:#fff; border-radius:12px; max-width:620px; width:100%; max-height:90vh; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 20px 25px -5px rgba(0,0,0,0.3);">
        <div style="padding:14px 18px; border-bottom:1px solid #e2e8f0; display:flex; align-items:flex-start; justify-content:space-between; gap:12px;">
            <div>
                <h3 id="modal-room-title" style="margin:0; font-size:15px; font-weight:700; color:#1e293b;">Foto Bukti Pengaduan</h3>
                <p id="modal-desc" style="margin:3px 0 0; font-size:12.5px; color:#64748b; line-height:1.4;"></p>
            </div>
            <button id="close-photo-modal" type="button" aria-label="Tutup pratinjau foto" style="background:none; border:none; font-size:22px; line-height:1; cursor:pointer; color:#64748b; padding:4px 8px; border-radius:6px;">✕</button>
        </div>
        <div style="padding:16px; display:flex; align-items:center; justify-content:center; background:#f8fafc; flex:1; min-height:260px; overflow:auto;">
            <img id="modal-photo-img" src="" alt="Pratinjau foto bukti kerusakan fasilitas" style="max-width:100%; max-height:65vh; object-fit:contain; border-radius:8px; border:1px solid #cbd5e1; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);">
        </div>
        <div style="padding:12px 18px; border-top:1px solid #e2e8f0; display:flex; justify-content:flex-end; gap:10px; background:#fff;">
            <a id="modal-open-tab" href="#" target="_blank" rel="noopener" class="btn ghost" style="font-size:12px; padding:6px 14px;">Buka di Tab Baru ↗</a>
            <button id="modal-close-btn" type="button" class="btn" style="font-size:12px; padding:6px 14px;">Tutup</button>
        </div>
    </div>
</div>

<script>
    (function () {
        var modal = document.getElementById('photo-modal');
        var modalImg = document.getElementById('modal-photo-img');
        var modalTitle = document.getElementById('modal-room-title');
        var modalDesc = document.getElementById('modal-desc');
        var modalOpenTab = document.getElementById('modal-open-tab');
        var closeBtn = document.getElementById('close-photo-modal');
        var closeActionBtn = document.getElementById('modal-close-btn');

        function closeModal() {
            if (modal) {
                modal.style.display = 'none';
                if (modalImg) modalImg.src = '';
            }
        }

        document.querySelectorAll('.open-photo-modal').forEach(function (button) {
            button.addEventListener('click', function () {
                var url = this.getAttribute('data-photo-url');
                var room = this.getAttribute('data-room');
                var desc = this.getAttribute('data-desc');

                if (modal && modalImg) {
                    modalImg.src = url;
                    if (modalTitle) modalTitle.textContent = 'Foto Bukti: ' + room;
                    if (modalDesc) modalDesc.textContent = desc;
                    if (modalOpenTab) modalOpenTab.href = url;
                    modal.style.display = 'flex';
                }
            });
        });

        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        if (closeActionBtn) closeActionBtn.addEventListener('click', closeModal);
        if (modal) {
            modal.addEventListener('click', function (e) {
                if (e.target === modal) closeModal();
            });
        }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeModal();
        });
    })();
</script>
@endif
@endsection
