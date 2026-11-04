@extends('layouts.app')
@section('content')
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
    .dashboard-page .stat b,
    .dashboard-page .stat-alert b { color: var(--dashboard-navy-deep); }
    .dashboard-page .stat b {
        display: block;
        font-size: 70px;
        font-weight: 800;
        line-height: 1;
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
    @media (max-width: 760px) {
        .dashboard-page { padding: 16px; }
        .dashboard-page .stat b { font-size: 36px; }
    }
</style>
<div class="dashboard-page">
<div class="heading dashboard-heading"><div><h1>Selamat datang, {{ auth()->user()->name }}!</h1><p>Ringkasan aktivitas dan infrastruktur sekolah hari ini.</p></div><b class="dashboard-date">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</b></div>
<div class="stats"><div class="card stat"><span class="muted">{{ auth()->user()->role?->role_name === 'PIC' ? 'RUANGAN TANGGUNG JAWAB' : 'TOTAL RUANGAN' }}</span><b>{{ $roomCount }}</b><span class="muted">{{ auth()->user()->role?->role_name === 'PIC' ? 'Ruangan yang ditugaskan kepada Anda' : 'Ruang terdaftar' }}</span></div><div class="card stat"><span class="muted">AGENDA HARI INI</span><b>{{ $todayEvents }}</b><span class="muted">Pending dan disetujui</span></div><div class="card stat stat-alert"><span class="muted">ADUAN PENDING</span><b>{{ $pendingComplaints }}</b><span class="muted">Perlu tindak lanjut PIC</span></div></div>
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
                <span class="badge {{ $event->status === 'Pending' ? 'pending' : '' }}" style="float:right">{{ $event->status }}</span>
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
            <h2>Aksi Cepat</h2>
            @if(in_array(auth()->user()->role?->role_name, ['Admin', 'Guru', 'Organisasi']))
                <p><a class="btn quick-action" href="{{ route('events.create') }}">＋ Ajukan agenda</a></p>
            @endif
            @if(auth()->user()->role?->role_name === 'Admin')
                <p><a class="btn ghost quick-action" href="{{ route('rooms.create') }}">＋ Tambah ruangan</a></p>
            @endif
        @endif
        @if(auth()->user()->role?->role_name === 'Admin' && $notifications->isNotEmpty())
            <h3>Notifikasi</h3>
            @foreach($notifications as $notice)
                <div class="notice">
                    @if($notice->complaint)
                        <a href="{{ route('complaints.index') }}">{{ $notice->message }}</a>
                    @elseif($notice->event)
                        <a href="{{ route('events.index') }}">{{ $notice->message }}</a>
                    @else
                        {{ $notice->message }}
                    @endif
                </div>
            @endforeach
        @endif
    </div>
</div>
</div>
@endsection
