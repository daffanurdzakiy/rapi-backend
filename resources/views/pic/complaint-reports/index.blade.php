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
        .side, .top, .report-filter-card, .no-print, .success { display:none !important; }
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
                        <td class="evidence-column">
                            @if($complaint->photo_path)
                                <a href="{{ route('pic.complaint-reports.photo', $complaint, false) }}" target="_blank" rel="noopener">Lihat foto</a>
                            @else
                                <span class="muted">Tidak ada foto</span>
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
@endif
@endsection
