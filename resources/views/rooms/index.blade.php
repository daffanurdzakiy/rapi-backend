@extends('layouts.app')

@section('content')
    <style>
        .rooms-toolbar { display:flex; align-items:center; justify-content:space-between; gap:16px; margin:4px 0 20px; }
        .rooms-search { display:flex; flex:1 1 420px; max-width:620px; align-items:center; gap:9px; }
        .rooms-search input { min-width:0; }
        .rooms-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(min(100%, 310px), 1fr)); gap:16px; }
        .room-card { min-width:0; overflow:hidden; border:1px solid var(--border); border-radius:14px; background:var(--panel); box-shadow:0 8px 20px rgba(38, 54, 74, .06); transition:border-color .18s, transform .18s, box-shadow .18s; }
        .room-card:hover { transform:translateY(-2px); border-color:#bdc9da; box-shadow:0 12px 26px rgba(38, 54, 74, .1); }
        .room-summary { display:flex; width:100%; min-height:78px; align-items:center; justify-content:space-between; gap:12px; padding:16px 18px; border:0; background:transparent; color:inherit; cursor:pointer; text-align:left; }
        .room-summary:focus-visible { outline:2px solid #667b99; outline-offset:-3px; }
        .room-title { margin:0; color:var(--text); font-size:17px; font-weight:700; }
        .room-summary-meta { display:flex; flex:0 0 auto; align-items:center; gap:8px; color:var(--muted); font-size:12px; }
        .room-chevron { display:inline-block; color:#667b99; font-size:17px; }
        .room-dialog { width:min(480px, calc(100% - 32px)); max-height:min(84vh, 640px); overflow:auto; margin:auto; padding:0; border:1px solid #e8e1d3; border-radius:18px; background:#fffdf8; color:#26364a; box-shadow:0 24px 80px rgba(38, 54, 74, .18); }
        .room-dialog::backdrop { background:rgba(38, 54, 74, .32); backdrop-filter:blur(5px); }
        .room-dialog-heading { display:flex; align-items:start; justify-content:space-between; gap:16px; padding:22px 23px 16px; border-bottom:1px solid #e8e1d3; }
        .room-dialog-heading h2 { margin:0; color:#26364a; font-size:20px; }
        .room-dialog-heading p { margin:5px 0 0; color:#6d7788; font-size:13px; }
        .room-dialog-close { display:grid; flex:0 0 34px; width:34px; height:34px; place-items:center; border:0; border-radius:50%; background:#f1f3f6; color:#35465e; cursor:pointer; font-size:21px; line-height:1; }
        .room-dialog-close:hover { background:#e8edf4; color:#26364a; }
        .room-details { padding:0 18px 18px; border-top:1px solid #e8e1d3; }
        .room-info { display:grid; grid-template-columns:1fr 1fr; gap:12px; padding:15px 0; }
        .room-info-item { min-width:0; }
        .room-info-label { display:block; margin-bottom:5px; color:#667386; font-size:10px; font-weight:700; letter-spacing:.07em; text-transform:uppercase; }
        .room-info-value { color:#26364a; font-size:13px; overflow-wrap:anywhere; }
        .room-description { padding-top:12px; border-top:1px solid #e8e1d3; }
        .room-actions { display:flex; flex-wrap:wrap; align-items:center; gap:9px; padding-top:14px; border-top:1px solid #e8e1d3; }
        .room-actions form { margin:0; }
        .rooms-empty { grid-column:1 / -1; margin:0; padding:24px; color:var(--muted); text-align:center; }
        .room-dialog .room-details { padding:0 23px 23px; border-top:0; }
        .room-blockers-dialog { width:min(680px, calc(100% - 32px)); max-height:min(84vh, 720px); overflow:auto; margin:auto; padding:22px; border:1px solid #e8e1d3; border-radius:18px; background:#fffdf8; color:#26364a; box-shadow:0 24px 80px rgba(38, 54, 74, .18); }
        .room-blockers-dialog::backdrop { background:rgba(38, 54, 74, .42); backdrop-filter:blur(5px); }
        .room-blockers-dialog h2 { margin:0 0 8px; font-size:20px; }
        .room-blockers-dialog > p { margin:0 0 16px; color:#6d7788; font-size:13px; line-height:1.5; }
        .room-blocker-list { display:grid; gap:12px; margin:0; padding:0; list-style:none; }
        .room-blocker-item { display:flex; align-items:center; justify-content:space-between; gap:14px; padding:14px; border:1px solid #e8e1d3; border-radius:12px; background:#fff; }
        .room-blocker-item p { margin:4px 0 0; color:#6d7788; font-size:12px; line-height:1.5; }
        .room-blocker-actions { display:flex; flex:0 0 auto; flex-wrap:wrap; gap:8px; }
        .room-blocker-actions form { margin:0; }
        .room-blockers-close { display:flex; justify-content:flex-end; margin-top:18px; }
        @media (max-width:760px) {
            .rooms-toolbar { align-items:stretch; flex-direction:column; }
            .rooms-search { flex:1 1 auto; }
            .rooms-search .btn { flex:0 0 auto; }
            .room-blocker-item { align-items:flex-start; flex-direction:column; }
        }
    </style>

    <div class="heading">
        <div>
            <h1>Ruangan</h1>
            <p>Daftar ruang sekolah dan PIC yang bertanggung jawab.</p>
        </div>
    </div>

    <div class="rooms-toolbar">
        <form class="rooms-search" method="GET" action="{{ route('rooms.index') }}" role="search">
            <input type="search" name="q" value="{{ $search }}" placeholder="Cari nama, lokasi, deskripsi, atau PIC..." aria-label="Cari ruangan">
            <button class="btn" type="submit">Cari</button>
            @if($search !== '')
                <a class="btn ghost" href="{{ route('rooms.index') }}">Reset</a>
            @endif
        </form>
        <a class="btn" href="{{ route('rooms.create') }}">+ Tambah Ruangan</a>
    </div>

    @if(session('room_blocking_events'))
        <dialog class="room-blockers-dialog" id="room-blockers-dialog" aria-labelledby="room-blockers-title">
            <h2 id="room-blockers-title">Agenda masih menggunakan ruangan</h2>
            <p>Pindahkan agenda mendatang atau batalkan agenda yang tidak jadi dilaksanakan. Agenda yang sedang berlangsung hanya dapat dibatalkan. Setelah semua agenda aktif ditangani, coba hapus ruangan kembali.</p>
            <ul class="room-blocker-list">
                @foreach(session('room_blocking_events') as $event)
                    <li class="room-blocker-item">
                        <div>
                            <strong>{{ $event['title'] }}</strong>
                            <p>{{ \Illuminate\Support\Carbon::parse($event['event_date'])->translatedFormat('d M Y') }} · {{ $event['start_time'] }}–{{ $event['end_time'] }} · {{ $event['user'] }}</p>
                            <p>{{ $event['execution_status'] }} · {{ $event['status'] }}</p>
                        </div>
                        <div class="room-blocker-actions">
                            @if($event['execution_status'] !== 'Sedang Berlangsung')
                                <a class="btn ghost" href="{{ route('events.edit', $event['id']) }}">Pindahkan</a>
                            @endif
                            <form method="post" action="{{ route('events.destroy', $event['id']) }}" onsubmit="return confirm('Batalkan agenda ini?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn danger" type="submit">Batalkan</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="room-blockers-close">
                <button class="btn ghost" type="button" data-close-blockers-dialog>Tutup</button>
            </div>
        </dialog>
    @endif

    <div class="rooms-grid">
        @forelse($rooms as $room)
            @php($dialogId = 'room-dialog-'.$room->id)
            <article class="room-card">
                <button class="room-summary" type="button" data-room-dialog="{{ $dialogId }}" aria-haspopup="dialog" aria-controls="{{ $dialogId }}" aria-label="Lihat informasi {{ $room->room_name }}">
                    <span class="room-title">{{ $room->room_name }}</span>
                    <span class="room-summary-meta">
                        {{ $room->capacity }} orang
                    </span>
                </button>
            </article>
            <dialog class="room-dialog" id="{{ $dialogId }}" aria-labelledby="{{ $dialogId }}-title">
                <div class="room-dialog-heading">
                    <div>
                        <h2 id="{{ $dialogId }}-title">{{ $room->room_name }}</h2>
                        <p>Informasi ruang sekolah</p>
                    </div>
                    <button class="room-dialog-close" type="button" data-close-room-dialog aria-label="Tutup">×</button>
                </div>
                <div class="room-details">
                    <div class="room-info">
                        <div class="room-info-item">
                            <span class="room-info-label">Kapasitas Ruang</span>
                            <span class="room-info-value">{{ $room->capacity }} orang</span>
                        </div>
                        <div class="room-info-item">
                            <span class="room-info-label">PIC Bertanggung Jawab</span>
                            <span class="room-info-value">{{ $room->pic->name }}</span>
                        </div>
                        <div class="room-info-item">
                            <span class="room-info-label">Lokasi</span>
                            <span class="room-info-value">{{ $room->location ?: 'Belum diatur' }}</span>
                        </div>
                    </div>
                    @if($room->description)
                        <div class="room-description">
                            <span class="room-info-label">Deskripsi</span>
                            <span class="room-info-value">{{ $room->description }}</span>
                        </div>
                    @endif
                    <div class="room-actions">
                        <a class="btn ghost" href="{{ route('rooms.edit', $room) }}">Edit</a>
                        <a class="btn ghost" href="{{ route('complaints.create', ['room_id' => $room->id]) }}">Pengaduan Kerusakan</a>
                        <form method="post" action="{{ route('rooms.destroy', $room) }}" onsubmit="return confirm('Hapus ruangan ini?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn danger" type="submit">Hapus</button>
                        </form>
                    </div>
                </div>
            </dialog>
        @empty
            <p class="rooms-empty">
                {{ $search !== '' ? 'Tidak ada ruangan yang cocok dengan pencarian.' : 'Belum ada ruangan.' }}
            </p>
        @endforelse
    </div>

    <script>
        document.querySelectorAll('[data-room-dialog]').forEach(function (button) {
            button.addEventListener('click', function () {
                var dialog = document.getElementById(button.dataset.roomDialog);
                if (dialog) {
                    dialog.showModal();
                }
            });
        });

        document.querySelectorAll('.room-dialog').forEach(function (dialog) {
            dialog.addEventListener('click', function (event) {
                if (event.target === dialog || event.target.closest('[data-close-room-dialog]')) {
                    dialog.close();
                }
            });
        });

        var blockersDialog = document.getElementById('room-blockers-dialog');
        if (blockersDialog) {
            blockersDialog.showModal();
            blockersDialog.addEventListener('click', function (event) {
                if (event.target === blockersDialog || event.target.closest('[data-close-blockers-dialog]')) {
                    blockersDialog.close();
                }
            });
        }
    </script>
@endsection
