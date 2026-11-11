@extends('layouts.app')
@section('content')
@php
    $featuredRoomSchedules = $featuredRoomSchedules ?? collect();
    $complaintsList = $recentComplaints ?? collect();
@endphp
<style>
    .dashboard-page {
        --dashboard-navy: #35465e;
        --dashboard-navy-deep: #26364a;
        --dashboard-ivory: #fffdf8;
        --dashboard-ivory-soft: #f7f4eb;
        --dashboard-border: #e8e1d3;
        --dashboard-muted: #6d7788;
        --panel: #fffdf8;
        --panel-raised: #fffdf8;
        --panel-hover: #f3efe4;
        --border: var(--dashboard-border);
        --text: var(--dashboard-navy-deep);
        --muted: var(--dashboard-muted);
        --accent: var(--dashboard-navy);
        --accent-hover: #465a73;
        --danger: var(--dashboard-navy);
        padding: 24px;
        border: 1px solid var(--dashboard-border);
        border-radius: 18px;
        background: var(--dashboard-ivory-soft);
        color: var(--dashboard-navy-deep);
    }
    .dashboard-page .card {
        border-color: var(--dashboard-border);
        background: var(--dashboard-ivory);
        box-shadow: 0 8px 20px rgba(38, 54, 74, .06);
    }
    .dashboard-page .heading h1,
    .dashboard-page h2,
    .dashboard-page h3 { color: var(--dashboard-navy-deep); }
    .dashboard-page .heading p,
    .dashboard-page .muted { color: var(--dashboard-muted); }
    .dashboard-page .dashboard-date { color: #46566d; }
    .dashboard-page .stat-value {
        display: block;
        font-size: 72px !important;
        font-weight: 800;
        line-height: 1;
        color: var(--dashboard-navy-deep);
    }
    .dashboard-page .stat-room {
        border-color: #dce5ef;
        border-left: 4px solid #8da7c3;
        background: #f7f9fc;
    }
    .dashboard-page .stat-room .stat-value,
    .dashboard-page .stat-room .muted:first-child { color: #496785; }
    .dashboard-page .stat-agenda {
        border-color: #dce8df;
        border-left: 4px solid #8eae98;
        background: #f7faf7;
    }
    .dashboard-page .stat-agenda .stat-value,
    .dashboard-page .stat-agenda .muted:first-child { color: #52745c; }
    .dashboard-page .stat-pending {
        border-color: #eadfdd;
        border-left: 4px solid #c39791;
        background: #fbf8f7;
    }
    .dashboard-page .stat-pending .stat-value,
    .dashboard-page .stat-pending .muted:first-child { color: #8a5e59; }
    .dashboard-page .room-schedules {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
        margin: 22px 0;
    }
    .dashboard-page .room-schedule {
        margin: 0;
        padding: 20px;
        border-color: #dce5ef;
        border-top: 3px solid #8da7c3;
        background: #fbfcfe;
    }
    .dashboard-page .room-schedule h2 {
        margin: 0;
        font-size: 18px;
    }
    .dashboard-page .room-schedule-subtitle {
        margin: 5px 0 16px;
        color: var(--dashboard-muted);
        font-size: 13px;
    }
    .dashboard-page .room-schedule-item {
        padding: 14px 0;
        border-top: 1px solid #e4e9ef;
    }
    .dashboard-page .room-schedule-item:first-of-type { border-top: 0; }
    .dashboard-page .room-schedule-title {
        display: block;
        margin-bottom: 7px;
        color: var(--dashboard-navy-deep);
        font-size: 14px;
        font-weight: 700;
    }
    .dashboard-page .room-schedule-meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        color: var(--dashboard-muted);
        font-size: 12px;
    }
    .dashboard-page .room-schedule-status {
        display: inline-flex;
        align-items: center;
        padding: 3px 8px;
        border: 1px solid #dce5ef;
        border-radius: 999px;
        background: #f1f5f9;
        color: #496785;
        font-size: 11px;
        font-weight: 700;
    }
    .dashboard-page .room-schedule-status.pending {
        border-color: #e8dfc9;
        background: #faf6ea;
        color: #806b3e;
    }
    .dashboard-page .room-schedule-empty {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px;
        border: 1px solid #dce8df;
        border-radius: 10px;
        background: #f7faf7;
    }
    .dashboard-page .room-schedule-empty strong {
        color: #52745c;
        font-size: 13px;
        letter-spacing: .04em;
    }
    .dashboard-page .room-schedule-empty span {
        color: var(--dashboard-muted);
        font-size: 12px;
    }
    .dashboard-page .stat::after {
        display: none;
    }
    .dashboard-page .list { border-top-color: var(--dashboard-border); }
    .dashboard-page .badge {
        border-color: #d7deea;
        background: #f1f3f6;
        color: var(--dashboard-navy);
    }
    .dashboard-page .badge.pending {
        border-color: #d9d1bf;
        background: #f7f4eb;
        color: var(--dashboard-navy-deep);
    }
    .dashboard-page .badge.badge-ongoing {
        background: #ecfeff !important;
        border-color: #67e8f9 !important;
        color: #0e7490 !important;
        font-weight: 700;
    }
    .dashboard-page .badge.badge-upcoming {
        background: #f0fdf4 !important;
        border-color: #86efac !important;
        color: #166534 !important;
        font-weight: 700;
    }
    .dashboard-page .badge.badge-completed {
        background: #f1f5f9 !important;
        border-color: #cbd5e1 !important;
        color: #475569 !important;
        font-weight: 600;
    }
    .dashboard-page .badge.badge-rejected {
        background: #fef2f2 !important;
        border-color: #fca5a5 !important;
        color: #991b1b !important;
        font-weight: 700;
    }
    .dashboard-page .badge.bad {
        border-color: #cbd4e1;
        background: #e8edf4;
        color: var(--dashboard-navy-deep);
    }
    .dashboard-page .btn { background: var(--dashboard-navy); color: var(--dashboard-ivory); }
    .dashboard-page .btn:hover { background: #465a73; color: var(--dashboard-ivory); }
    .dashboard-page .btn.ghost {
        border-color: #d7deea;
        background: #f1f3f6;
        color: var(--dashboard-navy);
    }
    .dashboard-page .btn.ghost:hover {
        border-color: #bdc9da;
        background: #e8edf4;
        color: var(--dashboard-navy-deep);
    }
    .dashboard-page a:not(.btn) { color: #465a73; }
    .dashboard-page a:not(.btn):hover { color: var(--dashboard-navy-deep); }
    .dashboard-page .notice {
        border-color: var(--dashboard-border);
        background: #f7f4eb;
        color: #34445d;
    }
    .dashboard-page .notice a { color: #465a73; }
    .dashboard-header-right {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 8px;
    }
    .dashboard-header-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }
    .quick-action-btn {
        min-height: 36px;
        padding: 0 14px;
        font-size: 13px;
        font-weight: 600;
        white-space: nowrap;
    }
    .badge-status-repaired {
        background: #dcfce7 !important;
        border-color: #86efac !important;
        color: #166534 !important;
        font-weight: 700;
    }
    .badge-status-waiting {
        background: #fef3c7 !important;
        border-color: #fde68a !important;
        color: #92400e !important;
        font-weight: 700;
    }
    .badge-status-pending {
        background: #fee2e2 !important;
        border-color: #fca5a5 !important;
        color: #991b1b !important;
        font-weight: 700;
    }
    @media (max-width: 760px) {
        .dashboard-page { padding: 16px; }
        .dashboard-page .stat-value { font-size: 56px !important; }
        .dashboard-page .room-schedules { grid-template-columns: 1fr; }
        .dashboard-header-right {
            align-items: flex-start;
            width: 100%;
        }
        .dashboard-header-actions {
            justify-content: flex-start;
            width: 100%;
        }
    }
</style>
<div class="dashboard-page">
<div class="heading dashboard-heading">
    <div>
        <h1>Selamat datang, {{ auth()->user()->name }}!</h1>
        <p>Ringkasan aktivitas dan infrastruktur sekolah hari ini.</p>
    </div>
    <div class="dashboard-header-right">
        <b class="dashboard-date">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</b>
        <div class="dashboard-header-actions">
            @if(in_array(auth()->user()->role?->role_name, ['Admin', 'Guru', 'Organisasi']))
                <a class="btn quick-action-btn" href="{{ route('events.create') }}">＋ Ajukan agenda</a>
            @endif
            @if(auth()->user()->role?->role_name === 'Admin')
                <a class="btn ghost quick-action-btn" href="{{ route('rooms.create') }}">＋ Tambah ruangan</a>
            @endif
        </div>
    </div>
</div>
<div class="stats"><div class="card stat stat-room"><span class="muted">{{ auth()->user()->role?->role_name === 'PIC' ? 'RUANGAN TANGGUNG JAWAB' : 'TOTAL RUANGAN' }}</span><b class="stat-value">{{ $roomCount }}</b><span class="muted">{{ auth()->user()->role?->role_name === 'PIC' ? 'Ruangan yang ditugaskan kepada Anda' : 'Ruang terdaftar' }}</span></div><div class="card stat stat-agenda"><span class="muted">AGENDA HARI INI</span><b class="stat-value">{{ $todayEvents }}</b><span class="muted">Jumlah agenda pada hari ini</span></div><div class="card stat stat-pending"><span class="muted">ADUAN PENDING</span><b class="stat-value">{{ $pendingComplaints }}</b><span class="muted">Perlu tindak lanjut PIC</span></div></div>
<section class="room-schedules" aria-label="Jadwal ruangan hari ini">
    @foreach($featuredRoomSchedules as $roomSchedule)
        <article class="card room-schedule">
            <h2>{{ $roomSchedule['name'] }}</h2>
            <p class="room-schedule-subtitle">Jadwal hari ini</p>
            @if(! $roomSchedule['exists'])
                <div class="room-schedule-empty">
                    <strong>Ruangan belum terdaftar</strong>
                </div>
            @elseif($roomSchedule['events']->isEmpty())
                <div class="room-schedule-empty">
                    <strong>KOSONG</strong>
                    <span>Belum ada agenda untuk hari ini.</span>
                </div>
            @else
                @foreach($roomSchedule['events'] as $roomEvent)
                    <div class="room-schedule-item">
                        <strong class="room-schedule-title">{{ $roomEvent->title }}</strong>
                        <div class="room-schedule-meta">
                            <span>{{ substr($roomEvent->start_time, 0, 5) }}–{{ substr($roomEvent->end_time, 0, 5) }}</span>
                            <span class="room-schedule-status {{ $roomEvent->status === 'Pending' ? 'pending' : '' }}">{{ $roomEvent->execution_status }}</span>
                        </div>
                    </div>
                @endforeach
            @endif
        </article>
    @endforeach
</section>
<div class="grid2">
    <div class="card">
        <div class="heading">
            <h2>Agenda Terkini</h2>
            <a href="{{ route('events.index') }}">Lihat semua</a>
        </div>
        @forelse($recentEvents as $event)
            <div class="list">
                <b>{{ $event->title }}</b><br>
                <span class="muted">{{ $event->room->room_name }} · {{ $event->event_date->locale('id')->translatedFormat('d M') }} {{ substr($event->start_time, 0, 5) }}–{{ substr($event->end_time, 0, 5) }}</span>
                <span class="badge {{ $event->execution_status_badge_class }}" style="float:right">{{ $event->execution_status }}</span>
            </div>
        @empty
            <p class="muted">Belum ada agenda.</p>
        @endforelse
    </div>

    <div class="card">
        @if(auth()->user()->role?->role_name === 'PIC')
            <h2>Daftar Ruangan Tanggung Jawab</h2>
            @forelse($assignedRooms as $room)
                <div class="list">
                    <b>{{ $room->room_name }}</b>
                    <span class="muted">
                        @if($room->location) · {{ $room->location }}@endif
                        · Kapasitas {{ $room->capacity }} orang
                    </span>
                </div>
            @empty
                <p class="muted">Belum ada ruangan yang ditugaskan kepada Anda.</p>
            @endforelse
        @else
            <div class="heading">
                <h2>Laporan Kerusakan Terbaru</h2>
                <a href="{{ route('complaints.index') }}">Lihat semua</a>
            </div>
            @forelse($complaintsList as $complaint)
                @php
                    $badgeClass = match($complaint->status_repair) {
                        'Sudah Diperbaiki' => 'badge-status-repaired',
                        'Menunggu Spare Part' => 'badge-status-waiting',
                        default => 'badge-status-pending',
                    };
                @endphp
                <div class="list">
                    <span class="badge {{ $badgeClass }}" style="float:right; margin-left:8px;">{{ $complaint->status_repair }}</span>
                    <b>{{ $complaint->room->room_name }}</b><br>
                    <span style="font-size:13px; color:#26364a; font-weight:500;">{{ \Illuminate\Support\Str::limit($complaint->description, 70) }}</span><br>
                    <span class="muted" style="font-size:12px;">
                        Pelapor: <b>{{ $complaint->reporter->name }}</b> · {{ $complaint->created_at->locale('id')->translatedFormat('d M Y, H:i') }}
                    </span>
                </div>
            @empty
                <p class="muted">Belum ada laporan kerusakan.</p>
            @endforelse
        @endif
    </div>
</div>
</div>
@endsection
