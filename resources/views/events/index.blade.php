@extends('layouts.app')

@section('content')
<style>
    .events-filter-bar {
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

    /* Badges untuk status pelaksanaan agenda otomatis */
    .badge-ongoing {
        background: #ecfeff !important;
        border-color: #67e8f9 !important;
        color: #0e7490 !important;
        font-weight: 700;
    }
    .badge-upcoming {
        background: #f0fdf4 !important;
        border-color: #86efac !important;
        color: #166534 !important;
        font-weight: 700;
    }
    .badge-completed {
        background: #f1f5f9 !important;
        border-color: #cbd5e1 !important;
        color: #475569 !important;
        font-weight: 600;
    }
    .badge-rejected {
        background: #fef2f2 !important;
        border-color: #fca5a5 !important;
        color: #991b1b !important;
        font-weight: 700;
    }
    .badge-pending {
        background: #fffbeb !important;
        border-color: #fde68a !important;
        color: #b45309 !important;
        font-weight: 700;
    }

    .pulse-dot {
        display: inline-block;
        width: 7px;
        height: 7px;
        margin-right: 5px;
        border-radius: 50%;
        background-color: #0891b2;
        box-shadow: 0 0 0 0 rgba(8, 145, 178, 0.7);
        animation: pulseDot 1.6s infinite;
    }
    @keyframes pulseDot {
        0% {
            transform: scale(0.95);
            box-shadow: 0 0 0 0 rgba(8, 145, 178, 0.7);
        }
        70% {
            transform: scale(1);
            box-shadow: 0 0 0 6px rgba(8, 145, 178, 0);
        }
        100% {
            transform: scale(0.95);
            box-shadow: 0 0 0 0 rgba(8, 145, 178, 0);
        }
    }
</style>

<div class="heading">
    <div>
        <h1>{{ auth()->user()->role?->role_name === 'PIC' ? 'Jadwal Ruangan' : 'Manajemen Agenda' }}</h1>
        <p>Agenda pending dan disetujui diperiksa untuk mencegah jadwal bentrok.</p>
    </div>
    <div style="display:flex;gap:10px">
        @if(in_array(auth()->user()->role?->role_name, ['Admin', 'Guru', 'Organisasi']))
            <a class="btn" href="{{ route('events.create') }}">+ Ajukan Agenda</a>
        @endif
    </div>
</div>

{{-- Filter Status Agenda / Peminjaman Ruangan --}}
<div class="events-filter-bar">
    @php
        $current = $currentFilter ?? 'semua';
        if ($current === 'pending') {
            $current = 'menunggu_persetujuan';
        }
    @endphp
    <a href="{{ route('events.index', ['status' => 'semua']) }}"
       class="filter-tab {{ $current === 'semua' ? 'active' : '' }}">
        Semua <span class="filter-count">{{ $counts['semua'] ?? 0 }}</span>
    </a>
    <a href="{{ route('events.index', ['status' => 'sedang_berlangsung']) }}"
       class="filter-tab {{ $current === 'sedang_berlangsung' ? 'active' : '' }}">
        Sedang Berlangsung <span class="filter-count">{{ $counts['sedang_berlangsung'] ?? 0 }}</span>
    </a>
    <a href="{{ route('events.index', ['status' => 'belum_terlaksana']) }}"
       class="filter-tab {{ $current === 'belum_terlaksana' ? 'active' : '' }}">
        Belum Terlaksana <span class="filter-count">{{ $counts['belum_terlaksana'] ?? 0 }}</span>
    </a>
    <a href="{{ route('events.index', ['status' => 'sudah_terlaksana']) }}"
       class="filter-tab {{ $current === 'sudah_terlaksana' ? 'active' : '' }}"
       title="Agenda yang sudah terlaksana hari ini akan tampil hingga pukul 23:59 sebelum otomatis diarsipkan">
        Sudah Terlaksana <span class="filter-count">{{ $counts['sudah_terlaksana'] ?? 0 }}</span>
    </a>
    <a href="{{ route('events.index', ['status' => 'menunggu_persetujuan']) }}"
       class="filter-tab {{ $current === 'menunggu_persetujuan' ? 'active' : '' }}">
        Menunggu Persetujuan <span class="filter-count">{{ $counts['menunggu_persetujuan'] ?? 0 }}</span>
    </a>
    <a href="{{ route('events.index', ['status' => 'ditolak']) }}"
       class="filter-tab {{ $current === 'ditolak' ? 'active' : '' }}"
       title="Pengajuan yang ditolak akan tampil selama 24 jam sebelum otomatis diarsipkan">
        Ditolak <span class="filter-count">{{ $counts['ditolak'] ?? 0 }}</span>
    </a>
</div>

<div class="card">
    @forelse($events as $event)
        <div id="event-{{ $event->id }}" class="list {{ (int) request('highlight') === (int) $event->id ? 'highlight-target' : '' }}">
            <div class="heading">
                <div>
                    <h3 style="margin:0">{{ $event->title }}</h3>
                    <p>
                        {{ $event->event_date->format('d M Y') }} · {{ substr($event->start_time, 0, 5) }}–{{ substr($event->end_time, 0, 5) }} ·
                        <b>{{ $event->room->room_name }}</b><br>
                        <span class="muted">Pemohon: {{ $event->user->name }}</span>
                        @if($event->move_reason)
                            <br><span class="muted">Alasan perubahan: {{ $event->move_reason }}</span>
                        @endif
                        @if($event->status === 'Ditolak' && $event->rejection_reason)
                            <br><span class="error"><b>Alasan ditolak:</b> {{ $event->rejection_reason }}</span>
                        @endif
                        @if($event->status === 'Disetujui' && $event->approver)
                            <br><span class="muted">Disetujui oleh: {{ $event->approver->name }} ({{ $event->approver->role?->role_name }})</span>
                        @endif
                    </p>
                </div>
                <div>
                    <span class="badge {{ $event->execution_status_badge_class }}" title="Status Persetujuan: {{ $event->status }}">
                        {{ $event->execution_status }}
                    </span>
                </div>
            </div>

            <div style="display:flex; flex-wrap:wrap; align-items:center; gap:8px; margin-top:4px;">
                @if(in_array(auth()->user()->role?->role_name, ['Admin', 'Guru', 'Organisasi']) && in_array($event->status, ['Pending', 'Disetujui']))
                    <a class="btn ghost" href="{{ route('complaints.create', ['room_id' => $event->room_id]) }}">Laporkan Kerusakan Ruangan</a>
                @endif

                @if(in_array(auth()->user()->role?->role_name, ['Admin', 'PIC']) && $event->status === 'Pending')
                    <form method="post" action="{{ route('events.decide', $event) }}" style="display:inline-flex; flex-wrap:wrap; align-items:center; gap:8px; margin:0; flex:1 1 auto;">
                        @csrf
                        @method('PATCH')
                        <button class="btn" name="status" value="Disetujui">Setujui</button>
                        <button class="btn danger" type="button" data-rejection-toggle="{{ $event->id }}">Tolak</button>
                        <div id="rejection-panel-{{ $event->id }}" @if(!$errors->has('rejection_reason')) hidden @endif style="flex:1 1 100%; width:100%; margin-top:8px;">
                            <label for="rejection_reason-{{ $event->id }}">Keterangan penolakan</label>
                            <textarea id="rejection_reason-{{ $event->id }}" name="rejection_reason" rows="2" maxlength="1000" @if(!$errors->has('rejection_reason')) disabled @else required @endif>{{ old('rejection_reason') }}</textarea>
                            @error('rejection_reason')<p class="error">{{ $message }}</p>@enderror
                            <input type="hidden" name="status" value="Ditolak" @if(!$errors->has('rejection_reason')) disabled @endif>
                            <button class="btn danger" type="submit" data-rejection-submit="{{ $event->id }}" style="margin-top:8px;" @if(!$errors->has('rejection_reason')) disabled @endif>Konfirmasi Tolak</button>
                        </div>
                    </form>
                @endif
                @if(auth()->user()->role?->role_name === 'Admin' && in_array($event->status, ['Pending', 'Disetujui']))
                    <a class="btn ghost" href="{{ route('events.edit', $event) }}">Edit</a>
                @endif
            </div>
        </div>
    @empty
        <div style="text-align:center; padding:32px 16px;">
            <p class="muted" style="margin:0; font-size:14px;">
                @if(($currentFilter ?? 'semua') !== 'semua')
                    Tidak ada agenda dengan status <b>{{ $currentFilter === 'sedang_berlangsung' ? 'Sedang Berlangsung' : ($currentFilter === 'belum_terlaksana' ? 'Belum Terlaksana' : ($currentFilter === 'sudah_terlaksana' ? 'Sudah Terlaksana' : ($currentFilter === 'menunggu_persetujuan' || $currentFilter === 'pending' ? 'Menunggu Persetujuan' : 'Ditolak'))) }}</b>.
                @else
                    Belum ada agenda peminjaman ruangan.
                @endif
            </p>
            @if(($currentFilter ?? 'semua') !== 'semua')
                <div style="margin-top:10px;">
                    <a href="{{ route('events.index') }}" class="btn ghost" style="min-height:32px; font-size:12px;">Tampilkan Semua Agenda</a>
                </div>
            @endif
        </div>
    @endforelse
</div>
<script>
    document.querySelectorAll('[data-rejection-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            var eventId = button.getAttribute('data-rejection-toggle');
            var panel = document.getElementById('rejection-panel-' + eventId);
            var textarea = document.getElementById('rejection_reason-' + eventId);
            var status = panel.querySelector('input[name="status"]');
            var submit = panel.querySelector('[data-rejection-submit]');
            panel.hidden = false;
            textarea.disabled = false;
            status.disabled = false;
            submit.disabled = false;
            textarea.required = true;
            textarea.focus();
        });
    });
</script>
@endsection
