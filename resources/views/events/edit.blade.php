@extends('layouts.app')

@section('content')
@php
    $selectedRoom = $rooms->firstWhere('id', old('room_id', $event->room_id));
    $approvedEventsOnDate = $approvedEventsOnDate ?? collect();
@endphp
<style>
    .room-picker { position: relative; }
    .room-picker-input-wrap { position: relative; display: flex; align-items: center; }
    .room-picker-input-wrap input { width: 100%; padding-right: 40px; }
    .room-picker-toggle-btn {
        position: absolute;
        right: 8px;
        background: transparent;
        border: none;
        color: #6d7788;
        cursor: pointer;
        padding: 8px 10px;
        font-size: 13px;
        line-height: 1;
        transition: color .15s ease;
    }
    .room-picker-toggle-btn:hover { color: #26364a; }
    .room-picker-list {
        position: absolute;
        z-index: 25;
        top: 100%;
        left: 0;
        right: 0;
        max-height: 260px;
        overflow-y: auto;
        margin: 4px 0 0;
        padding: 6px;
        list-style: none;
        background: #fffdf8;
        border: 1px solid #d7deea;
        border-radius: 8px;
        box-shadow: 0 10px 25px rgba(38, 54, 74, .16);
    }
    .room-picker-list[hidden] { display: none; }
    .room-picker-option {
        width: 100%;
        padding: 10px 12px;
        border: 0;
        border-radius: 6px;
        background: #fffdf8;
        color: #26364a;
        text-align: left;
        font: inherit;
        cursor: pointer;
        transition: background .15s ease;
    }
    .room-picker-option:hover, .room-picker-option:focus { background: #f1f3f6; outline: none; }
    .room-picker-option.is-selected { background: #e8edf4; font-weight: 600; }
    .room-picker-option-row { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
    .room-picker-empty { padding: 14px 10px; color: #6d7788; text-align: center; }
    
    .status-badge {
        display: inline-flex;
        align-items: center;
        padding: 3px 9px;
        border-radius: 99px;
        font-size: 11px;
        font-weight: 700;
        white-space: nowrap;
    }
    .status-badge.available { background: #eef7ee; color: #24682c; border: 1px solid #c8e6c9; }
    .status-badge.conflict { background: #fdeeee; color: #b71c1c; border: 1px solid #ffcdd2; }
    .status-badge.current { background: #e8edf4; color: #35465e; border: 1px solid #d7deea; }
    
    .anti-bentrok-box {
        margin-top: 10px;
        padding: 12px 14px;
        border-radius: 8px;
        font-size: 13px;
        line-height: 1.5;
        display: none;
    }
    .anti-bentrok-box.is-available {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        background: #f2f9f3;
        border: 1px solid #c3e6cb;
        color: #1e5e29;
    }
    .anti-bentrok-box.is-conflict {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        background: #fdf2f2;
        border: 1px solid #f5c6cb;
        color: #842029;
    }
    .anti-bentrok-box.is-info {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        background: #f0f4f9;
        border: 1px solid #d0dcea;
        color: #26364a;
    }
    .move-hint {
        display: none;
        margin-top: 6px;
        font-size: 12px;
        color: #35465e;
        background: #f1f5f9;
        padding: 6px 10px;
        border-radius: 6px;
        border-left: 3px solid #667b99;
    }
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
    <p class="muted">
        {{ $event->event_date->format('d M Y') }} · Ruangan Saat Ini: <b>{{ $event->room->room_name }}</b> · Status: <b>{{ $event->status }}</b>
    </p>

    <form id="edit-event-form" method="post" action="{{ route('events.update', $event) }}">
        @csrf
        @method('PUT')
        <div class="form-grid">
            <div class="full">
                <label for="room_search">Ruangan Pilihan / Tujuan</label>
                <div class="room-picker">
                    <div class="room-picker-input-wrap">
                        <input
                            id="room_search"
                            type="search"
                            value="{{ $selectedRoom?->room_name }}"
                            placeholder="Pilih atau cari ruangan..."
                            autocomplete="off"
                            role="combobox"
                            aria-autocomplete="list"
                            aria-expanded="false"
                            aria-controls="room-options"
                            required
                        >
                        <button type="button" id="room_picker_toggle" class="room-picker-toggle-btn" aria-label="Buka pilihan ruangan">▼</button>
                    </div>
                    <input id="room_id" type="hidden" name="room_id" value="{{ old('room_id', $event->room_id) }}">
                    <ul id="room-options" class="room-picker-list" role="listbox" hidden>
                    @foreach($rooms as $room)
                        <li role="option">
                            <button
                                class="room-picker-option {{ $room->id == old('room_id', $event->room_id) ? 'is-selected' : '' }}"
                                type="button"
                                data-room-id="{{ $room->id }}"
                                data-room-name="{{ $room->room_name }}"
                            >
                                <div class="room-picker-option-row">
                                    <span style="font-weight:600">{{ $room->room_name }}</span>
                                    <span class="status-badge" data-status-for="{{ $room->id }}">-</span>
                                </div>
                                <div style="font-size:11px;color:#6d7788;margin-top:3px">
                                    {{ $room->location ? $room->location . ' · ' : '' }}Kapasitas {{ $room->capacity }} orang
                                    @if($room->id === $event->room_id)
                                        <b style="color:#465a73">· (Ruangan Saat Ini)</b>
                                    @endif
                                </div>
                            </button>
                        </li>
                    @endforeach
                    <li class="room-picker-empty" data-room-empty hidden>Tidak ada ruangan yang cocok.</li>
                    </ul>
                </div>

                {{-- Status Card Real-Time Anti-Bentrok --}}
                <div id="anti-bentrok-alert" class="anti-bentrok-box"></div>
                <div id="room-move-hint" class="move-hint"></div>

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
                <label for="move_reason">
                    Alasan perubahan / pemindahan ruangan
                    <span id="move_reason_required_badge" style="color:#b71c1c;font-weight:700;display:none;">(Wajib diisi karena ruangan dipindahkan)</span>
                </label>
                <textarea id="move_reason" name="move_reason" rows="3">{{ old('move_reason', $event->move_reason) }}</textarea>
                <p class="muted">Pilih sesi 1–14, atau pilih waktu bebas untuk menentukan jam sendiri setelah pukul 17.00. Alasan wajib diisi jika ruangan diganti.</p>
                @error('move_reason')<p class="error">{{ $message }}</p>@enderror
            </div>
        </div>

        <button id="submit_btn" class="btn" style="margin-top:20px">Simpan Perubahan</button>
    </form>
</div>

<script>
    (function () {
        var approvedEvents = @json($approvedEventsOnDate);
        var originalRoomId = {{ (int) $event->room_id }};
        var originalRoomName = @json($event->room->room_name);
        var eventDateStr = @json($event->event_date->format('d M Y'));

        var search = document.getElementById('room_search');
        var roomId = document.getElementById('room_id');
        var list = document.getElementById('room-options');
        var options = Array.from(list.querySelectorAll('[data-room-id]'));
        var empty = list.querySelector('[data-room-empty]');
        var picker = search.closest('.room-picker');
        var toggleBtn = document.getElementById('room_picker_toggle');
        var alertBox = document.getElementById('anti-bentrok-alert');
        var moveHint = document.getElementById('room-move-hint');
        var moveReasonBadge = document.getElementById('move_reason_required_badge');
        var moveReasonTextarea = document.getElementById('move_reason');
        var submitBtn = document.getElementById('submit_btn');

        var startSelect = document.getElementById('start_time');
        var endSelect = document.getElementById('end_time');
        var startFreeInput = document.getElementById('start_time_free');
        var endFreeInput = document.getElementById('end_time_free');

        var selectedRoomName = search.value.trim();
        var currentConflict = null;

        function getCurrentTimes() {
            var start = startSelect.value === 'free' ? (startFreeInput.value || '') : startSelect.value;
            var end = endSelect.value === 'free' ? (endFreeInput.value || '') : endSelect.value;
            return {
                start: start ? start.substring(0, 5) : '',
                end: end ? end.substring(0, 5) : ''
            };
        }

        function findConflictForRoom(targetRoomId, start, end) {
            if (!targetRoomId || !start || !end) return null;
            for (var i = 0; i < approvedEvents.length; i++) {
                var ev = approvedEvents[i];
                if (String(ev.room_id) === String(targetRoomId)) {
                    var evStart = (ev.start_time || '').substring(0, 5);
                    var evEnd = (ev.end_time || '').substring(0, 5);
                    // Time range overlap check: (start < evEnd && end > evStart)
                    if (start < evEnd && end > evStart) {
                        return ev;
                    }
                }
            }
            return null;
        }

        function updateAllRoomBadges() {
            var times = getCurrentTimes();
            options.forEach(function (option) {
                var rId = option.dataset.roomId;
                var badge = option.querySelector('[data-status-for]');
                if (!badge) return;

                var conflict = findConflictForRoom(rId, times.start, times.end);
                if (conflict) {
                    var cStart = (conflict.start_time || '').substring(0, 5);
                    var cEnd = (conflict.end_time || '').substring(0, 5);
                    badge.textContent = 'Bentrok (' + cStart + '–' + cEnd + ')';
                    badge.className = 'status-badge conflict';
                } else if (String(rId) === String(originalRoomId)) {
                    badge.textContent = 'Tersedia (Ruang Asal)';
                    badge.className = 'status-badge available';
                } else {
                    badge.textContent = 'Tersedia';
                    badge.className = 'status-badge available';
                }
            });
        }

        function checkAvailabilityStatus() {
            updateAllRoomBadges();

            var currentRoomId = roomId.value;
            var times = getCurrentTimes();

            if (!currentRoomId || !times.start || !times.end) {
                alertBox.style.display = 'none';
                moveHint.style.display = 'none';
                currentConflict = null;
                submitBtn.disabled = false;
                return;
            }

            var roomName = search.value.trim() || 'Ruangan terpilih';
            var conflict = findConflictForRoom(currentRoomId, times.start, times.end);
            currentConflict = conflict;

            if (conflict) {
                var cStart = (conflict.start_time || '').substring(0, 5);
                var cEnd = (conflict.end_time || '').substring(0, 5);
                alertBox.className = 'anti-bentrok-box is-conflict';
                alertBox.innerHTML = '<div>' +
                    '<strong>⚠ Peringatan Anti-Bentrok:</strong> Ruangan <b>' + roomName + '</b> sudah digunakan oleh agenda <b>"' + (conflict.title || 'Agenda lain') + '"</b> pada pukul <b>' + cStart + ' – ' + cEnd + '</b>.<br>' +
                    '<span style="font-size:12px;opacity:0.9">Pemindahan ke ruangan ini tidak diizinkan untuk menghindari bentrok jadwal. Silakan pilih ruangan lain atau ubah jam agenda.</span>' +
                    '</div>';
                alertBox.style.display = 'flex';
                submitBtn.title = 'Tidak dapat menyimpan: Jadwal ruangan bentrok.';
            } else {
                if (String(currentRoomId) !== String(originalRoomId)) {
                    alertBox.className = 'anti-bentrok-box is-available';
                    alertBox.innerHTML = '<div>' +
                        '<strong>✓ Anti-Bentrok:</strong> Ruangan <b>' + roomName + '</b> <b>TERSEDIA</b> pada pukul ' + times.start + ' – ' + times.end + '. Siap dipindahkan dari ' + originalRoomName + '.' +
                        '</div>';
                    alertBox.style.display = 'flex';

                    moveHint.innerHTML = 'Ruangan dipindahkan dari <b>' + originalRoomName + '</b> ke <b>' + roomName + '</b>. Mohon cantumkan alasan pemindahan ruangan di bawah ini.';
                    moveHint.style.display = 'block';

                    moveReasonBadge.style.display = 'inline';
                    moveReasonTextarea.setAttribute('required', 'required');
                } else {
                    alertBox.className = 'anti-bentrok-box is-info';
                    alertBox.innerHTML = '<div>' +
                        'Ruangan saat ini: <b>' + roomName + '</b> (Tersedia pada pukul ' + times.start + ' – ' + times.end + ').' +
                        '</div>';
                    alertBox.style.display = 'flex';

                    moveHint.style.display = 'none';
                    moveReasonBadge.style.display = 'none';
                    moveReasonTextarea.removeAttribute('required');
                }
                submitBtn.title = '';
            }
        }

        function openList(forceShowAll) {
            list.hidden = false;
            search.setAttribute('aria-expanded', 'true');
            filterRooms(forceShowAll);
        }

        function closeList() {
            list.hidden = true;
            search.setAttribute('aria-expanded', 'false');
        }

        function filterRooms(forceShowAll) {
            var query = (search.value || '').trim().toLocaleLowerCase();
            var currentSelected = (selectedRoomName || '').trim().toLocaleLowerCase();
            // Jika forceShowAll aktif, atau query persis sama dengan nama ruangan yang sedang terpilih,
            // tampilkan SEMUA ruangan agar admin bisa memilih ruangan lain untuk dipindahkan.
            var showAll = forceShowAll || (query === currentSelected) || !query;

            var visibleCount = 0;
            options.forEach(function (option) {
                var name = option.dataset.roomName.toLocaleLowerCase();
                var matches = showAll || name.indexOf(query) !== -1;
                option.parentElement.hidden = !matches;
                if (matches) visibleCount++;
            });
            empty.hidden = visibleCount > 0;
        }

        function chooseRoom(option) {
            roomId.value = option.dataset.roomId;
            selectedRoomName = option.dataset.roomName;
            search.value = option.dataset.roomName;
            search.setCustomValidity('');

            options.forEach(function (opt) {
                opt.classList.toggle('is-selected', opt === option);
            });

            closeList();
            checkAvailabilityStatus();
        }

        // Event listener pada input ruangan
        search.addEventListener('focus', function () {
            openList(true);
            search.select();
        });

        search.addEventListener('click', function () {
            if (list.hidden) {
                openList(true);
            }
        });

        search.addEventListener('input', function () {
            search.setCustomValidity('');
            openList(false);
            // Jika user mengetik manual, cari apakah cocok persis dengan salah satu ruangan
            var match = options.find(function (opt) {
                return opt.dataset.roomName.toLocaleLowerCase() === search.value.trim().toLocaleLowerCase();
            });
            if (match) {
                roomId.value = match.dataset.roomId;
                selectedRoomName = match.dataset.roomName;
            } else {
                roomId.value = '';
            }
            checkAvailabilityStatus();
        });

        // Tangani tombol 'x' (clear) bawaan input type="search"
        search.addEventListener('search', function () {
            roomId.value = '';
            selectedRoomName = '';
            openList(true);
            checkAvailabilityStatus();
        });

        // Tombol toggle panah ▼
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                if (list.hidden) {
                    openList(true);
                    search.focus();
                } else {
                    closeList();
                }
            });
        }

        options.forEach(function (option) {
            option.addEventListener('click', function (e) {
                e.preventDefault();
                chooseRoom(option);
            });
        });

        search.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeList();
            } else if (event.key === 'ArrowDown') {
                event.preventDefault();
                if (list.hidden) openList(true);
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
            if (picker && !picker.contains(event.target)) {
                closeList();
                // Jika input dikosongkan tanpa memilih, kembalikan nama ruangan terpilih sebelumnya jika ada
                if (!roomId.value && selectedRoomName) {
                    var prevOpt = options.find(function (opt) { return opt.dataset.roomName === selectedRoomName; });
                    if (prevOpt) {
                        roomId.value = prevOpt.dataset.roomId;
                        search.value = selectedRoomName;
                        checkAvailabilityStatus();
                    }
                }
            }
        });

        // Saat jam mulai atau selesai diubah, update status anti-bentrok secara otomatis
        [startSelect, endSelect, startFreeInput, endFreeInput].forEach(function (el) {
            if (el) {
                el.addEventListener('change', checkAvailabilityStatus);
                el.addEventListener('input', checkAvailabilityStatus);
            }
        });

        // Validasi saat form di-submit
        document.getElementById('edit-event-form').addEventListener('submit', function (event) {
            if (!roomId.value) {
                event.preventDefault();
                search.setCustomValidity('Pilih ruangan dari daftar yang tersedia.');
                search.reportValidity();
                return;
            }

            if (currentConflict) {
                event.preventDefault();
                var cStart = (currentConflict.start_time || '').substring(0, 5);
                var cEnd = (currentConflict.end_time || '').substring(0, 5);
                alert('Peringatan Anti-Bentrok:\nRuangan "' + search.value.trim() + '" tidak dapat dipilih karena sudah digunakan oleh agenda "' + currentConflict.title + '" (' + cStart + ' - ' + cEnd + ').\n\nSilakan pilih ruangan lain yang tersedia.');
                search.focus();
                return;
            }

            if (String(roomId.value) !== String(originalRoomId) && !moveReasonTextarea.value.trim()) {
                event.preventDefault();
                alert('Alasan pemindahan ruangan wajib diisi.');
                moveReasonTextarea.focus();
                return;
            }
        });

        // Inisialisasi awal saat halaman dibuka
        checkAvailabilityStatus();
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
