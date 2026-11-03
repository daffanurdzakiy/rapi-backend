@extends('layouts.app')
@section('content')
@php($selectedRoom = $rooms->firstWhere('id', old('room_id')))
<style>
    .room-picker { position:relative; }
    .room-picker-list { position:absolute; z-index:5; top:100%; left:0; right:0; max-height:220px; overflow-y:auto; margin:4px 0 0; padding:4px; list-style:none; background:#fffdf8; border:1px solid #d7deea; border-radius:8px; box-shadow:0 8px 20px rgba(38, 54, 74, .12); }
    .room-picker-list[hidden] { display:none; }
    .room-picker-option { width:100%; padding:9px 10px; border:0; border-radius:5px; background:#fffdf8; color:#26364a; text-align:left; font:inherit; cursor:pointer; }
    .room-picker-option:hover, .room-picker-option:focus { background:#f1f3f6; outline:none; }
    .room-picker-empty { padding:9px 10px; color:#6d7788; }
</style>
<div class="heading">
    <div>
        <h1>Ajukan Agenda</h1>
        <p>
            @if($canUseFreeTime)
                Pilih sesi 1–14 atau tentukan sendiri waktu bebas setelah pukul 17.00.
            @else
                Guru dan Organisasi hanya dapat meminjam ruangan sampai sesi 14 (pukul 17.00).
            @endif
        </p>
    </div>
</div>
<form id="event-form" class="card" method="post" action="{{ route('events.store') }}">
    @csrf
    <div class="form-grid">
        <div class="full">
            <label for="title">Nama kegiatan</label>
            <input id="title" name="title" value="{{ old('title') }}" required>
        </div>
        <div>
            <label for="room_search">{{ $canUseFreeTime ? 'Pilih ruangan' : 'Ruangan' }}</label>
            <div class="room-picker">
                <input id="room_search" type="search" value="{{ $selectedRoom?->room_name }}" placeholder="Pilih atau cari ruangan..." autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="room-options" required>
                <input id="room_id" type="hidden" name="room_id" value="{{ old('room_id') }}">
                <ul id="room-options" class="room-picker-list" role="listbox" hidden>
                @foreach($rooms as $room)
                    <li role="option"><button class="room-picker-option" type="button" data-room-id="{{ $room->id }}" data-room-name="{{ $room->room_name }}">{{ $room->room_name }}</button></li>
                @endforeach
                <li class="room-picker-empty" data-room-empty hidden>Tidak ada ruangan yang cocok.</li>
                </ul>
            </div>
            @error('room_id')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="event_date">Tanggal</label>
            <input id="event_date" type="date" name="event_date" value="{{ old('event_date') }}" min="{{ $firstBookingDate }}" required>
            <p class="muted">Peminjaman ruang hanya dapat diajukan mulai H-1 (besok) dan seterusnya.</p>
            @error('event_date')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="start_time">Jam mulai</label>
            <select id="start_time" name="start_time" required>
                <option value="">Pilih sesi mulai</option>
                @foreach($sessions as $session)
                    <option value="{{ substr($session->time, 0, 5) }}" @selected(old('start_time') === substr($session->time, 0, 5))>{{ $session->session_name }} · {{ substr($session->time, 0, 5) }}</option>
                @endforeach
                @if($canUseFreeTime)
                    <option value="free" @selected(old('start_time') === 'free')>Waktu bebas (setelah 17.00)</option>
                @endif
            </select>
            @if($canUseFreeTime)
                <div id="start_time_free_container" @if(old('start_time') !== 'free') hidden @endif>
                    <label for="start_time_free">Tentukan jam mulai bebas</label>
                    <input id="start_time_free" type="time" name="start_time_free" min="17:01" value="{{ old('start_time_free') }}" @if(old('start_time') === 'free') required @endif>
                </div>
            @endif
            @error('start_time')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="end_time">Jam selesai</label>
            <select id="end_time" name="end_time" required>
                <option value="">Pilih sesi selesai</option>
                @foreach($sessions as $session)
                    <option value="{{ substr($session->time, 0, 5) }}" @selected(old('end_time') === substr($session->time, 0, 5))>{{ $session->session_name }} · {{ substr($session->time, 0, 5) }}</option>
                @endforeach
                @if($canUseFreeTime)
                    <option value="free" @selected(old('end_time') === 'free')>Waktu bebas (setelah 17.00)</option>
                @endif
            </select>
            @if($canUseFreeTime)
                <div id="end_time_free_container" @if(old('end_time') !== 'free') hidden @endif>
                    <label for="end_time_free">Tentukan jam selesai bebas</label>
                    <input id="end_time_free" type="time" name="end_time_free" min="17:01" value="{{ old('end_time_free') }}" @if(old('end_time') === 'free') required @endif>
                </div>
            @endif
            @error('end_time')<p class="error">{{ $message }}</p>@enderror
        </div>
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:22px">
        <button class="btn" type="submit">Kirim Pengajuan</button>
        <a class="btn ghost" href="{{ route('events.index') }}">Batal</a>
    </div>
</form>
<script>
    (function () {
        var search = document.getElementById('room_search');
        var roomId = document.getElementById('room_id');
        var list = document.getElementById('room-options');
        var options = Array.from(list.querySelectorAll('[data-room-id]'));
        var empty = list.querySelector('[data-room-empty]');
        var picker = search.parentElement;

        function openList() {
            list.hidden = false;
            search.setAttribute('aria-expanded', 'true');
            filterRooms();
        }

        function closeList() {
            list.hidden = true;
            search.setAttribute('aria-expanded', 'false');
        }

        function filterRooms() {
            var query = search.value.trim().toLocaleLowerCase();
            var visibleCount = 0;
            options.forEach(function (option) {
                var matches = option.dataset.roomName.toLocaleLowerCase().includes(query);
                option.parentElement.hidden = !matches;
                if (matches) visibleCount++;
            });
            empty.hidden = visibleCount > 0;
        }

        function chooseRoom(option) {
            roomId.value = option.dataset.roomId;
            search.value = option.dataset.roomName;
            search.setCustomValidity('');
            closeList();
        }

        search.addEventListener('focus', openList);
        search.addEventListener('input', function () {
            roomId.value = '';
            search.setCustomValidity('');
            openList();
        });
        options.forEach(function (option) {
            option.addEventListener('click', function () {
                chooseRoom(option);
            });
        });
        search.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeList();
            } else if (event.key === 'ArrowDown' && list.hidden) {
                event.preventDefault();
                openList();
                var first = list.querySelector('[data-room-id]:not([hidden])');
                if (first) first.focus();
            } else if (event.key === 'Enter' && !list.hidden) {
                var firstMatch = list.querySelector('li:not([hidden]) [data-room-id]');
                if (firstMatch) {
                    event.preventDefault();
                    chooseRoom(firstMatch);
                }
            }
        });
        list.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeList();
                search.focus();
            }
        });
        document.addEventListener('click', function (event) {
            if (!picker.contains(event.target)) closeList();
        });
        document.getElementById('event-form').addEventListener('submit', function (event) {
            if (!roomId.value) {
                event.preventDefault();
                search.setCustomValidity('Pilih ruangan dari daftar yang tersedia.');
                search.reportValidity();
            }
        });
    })();
</script>
@if($canUseFreeTime)
<script>
    document.getElementById('event-form').addEventListener('submit', function (event) {
        var room = document.getElementById('room_search').value;
        var title = document.getElementById('title').value.trim();
        var date = document.getElementById('event_date').value;
        var start = document.getElementById('start_time').value === 'free'
            ? document.getElementById('start_time_free').value
            : document.getElementById('start_time').value;
        var end = document.getElementById('end_time').value === 'free'
            ? document.getElementById('end_time_free').value
            : document.getElementById('end_time').value;
        var message = 'Kirim pengajuan agenda ini?\n\nKegiatan: ' + title
            + '\nRuangan: ' + room
            + '\nTanggal: ' + date
            + '\nWaktu: ' + start + ' - ' + end;
        if (!window.confirm(message)) event.preventDefault();
    });
</script>
@endif
@if($canUseFreeTime)
<script>
    ['start_time', 'end_time'].forEach(function (field) {
        var select = document.getElementById(field);
        var container = document.getElementById(field + '_free_container');
        var input = document.getElementById(field + '_free');
        function toggleFreeTime() {
            var isFree = select.value === 'free';
            container.hidden = !isFree;
            input.required = isFree;
            input.disabled = !isFree;
        }
        select.addEventListener('change', toggleFreeTime);
        toggleFreeTime();
    });
</script>
@endif
@endsection
