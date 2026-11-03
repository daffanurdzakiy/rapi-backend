@extends('layouts.app')

@section('content')
@php($selectedRoom = $rooms->firstWhere('id', old('room_id', $event->room_id)))
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
        <h1>Edit Agenda</h1>
        <p>Ubah waktu peminjaman untuk agenda ini atau pindahkan ke ruangan lain. Pemohon: {{ $event->user->name }}.</p>
    </div>
    <a class="btn ghost" href="{{ route('events.index') }}">Kembali</a>
</div>

<div class="card">
    <h2>{{ $event->title }}</h2>
    <p class="muted">{{ $event->event_date->format('d M Y') }} · {{ $event->room->room_name }} · {{ $event->status }}</p>
    <form method="post" action="{{ route('events.update', $event) }}">
        @csrf
        @method('PUT')
        <div class="form-grid">
            <div class="full">
                <label for="room_search">Ruangan</label>
                <div class="room-picker">
                    <input id="room_search" type="search" value="{{ $selectedRoom?->room_name }}" placeholder="Pilih atau cari ruangan..." autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="room-options" required>
                    <input id="room_id" type="hidden" name="room_id" value="{{ old('room_id', $event->room_id) }}">
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
                <label for="start_time">Jam mulai</label>
                <select id="start_time" name="start_time" required>
                    @foreach($sessions as $session)
                        <option value="{{ substr($session->time, 0, 5) }}" @selected(old('start_time', substr($event->start_time, 0, 5)) === substr($session->time, 0, 5))>{{ $session->session_name }} · {{ substr($session->time, 0, 5) }}</option>
                    @endforeach
                    <option value="free" @selected(old('start_time') === 'free' || (!old('start_time') && substr($event->start_time, 0, 5) > '17:00'))>Waktu bebas (setelah 17.00)</option>
                </select>
                <div id="start_time_free_container" @if(old('start_time') !== 'free' && (old('start_time') || substr($event->start_time, 0, 5) <= '17:00')) hidden @endif>
                    <label for="start_time_free">Tentukan jam mulai bebas</label>
                    <input id="start_time_free" type="time" name="start_time_free" min="17:01" value="{{ old('start_time_free', substr($event->start_time, 0, 5) > '17:00' ? substr($event->start_time, 0, 5) : '') }}">
                </div>
                @error('start_time')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="end_time">Jam selesai</label>
                <select id="end_time" name="end_time" required>
                    @foreach($sessions as $session)
                        <option value="{{ substr($session->time, 0, 5) }}" @selected(old('end_time', substr($event->end_time, 0, 5)) === substr($session->time, 0, 5))>{{ $session->session_name }} · {{ substr($session->time, 0, 5) }}</option>
                    @endforeach
                    <option value="free" @selected(old('end_time') === 'free' || (!old('end_time') && substr($event->end_time, 0, 5) > '17:00'))>Waktu bebas (setelah 17.00)</option>
                </select>
                <div id="end_time_free_container" @if(old('end_time') !== 'free' && (old('end_time') || substr($event->end_time, 0, 5) <= '17:00')) hidden @endif>
                    <label for="end_time_free">Tentukan jam selesai bebas</label>
                    <input id="end_time_free" type="time" name="end_time_free" min="17:01" value="{{ old('end_time_free', substr($event->end_time, 0, 5) > '17:00' ? substr($event->end_time, 0, 5) : '') }}">
                </div>
                @error('end_time')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div class="full">
                <label for="move_reason">Alasan perubahan / pemindahan ruangan</label>
                <textarea id="move_reason" name="move_reason" rows="3">{{ old('move_reason') }}</textarea>
                <p class="muted">Pilih sesi 1–14, atau pilih waktu bebas untuk menentukan jam sendiri setelah pukul 17.00. Alasan wajib diisi jika ruangan diganti.</p>
                @error('move_reason')<p class="error">{{ $message }}</p>@enderror
            </div>
        </div>
        <button class="btn" style="margin-top:20px">Simpan Perubahan</button>
    </form>
</div>
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
            options.forEach(function (option) {
                option.parentElement.hidden = !option.dataset.roomName.toLocaleLowerCase().includes(query);
            });
            empty.hidden = options.every(function (option) {
                return option.parentElement.hidden;
            });
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
                var first = list.querySelector('li:not([hidden]) [data-room-id]');
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
        document.querySelector('form').addEventListener('submit', function (event) {
            if (!roomId.value) {
                event.preventDefault();
                search.setCustomValidity('Pilih ruangan dari daftar yang tersedia.');
                search.reportValidity();
            }
        });
    })();
</script>
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
@endsection
