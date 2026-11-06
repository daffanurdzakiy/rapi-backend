@extends('layouts.app')
@section('content')
@php($selectedRoom = $rooms->firstWhere('id', old('room_id')))
<style>
    .room-picker { position:relative; }
    .room-picker-input-wrap { position:relative; display:flex; align-items:center; }
    .room-picker-input-wrap input { width:100%; padding-right:40px; }
    .room-picker-toggle-btn { position:absolute; right:8px; background:transparent; border:none; color:#6d7788; cursor:pointer; padding:8px 10px; font-size:13px; line-height:1; }
    .room-picker-toggle-btn:hover { color:#26364a; }
    .room-picker-list { position:absolute; z-index:25; top:100%; left:0; right:0; max-height:240px; overflow-y:auto; margin:4px 0 0; padding:6px; list-style:none; background:#fffdf8; border:1px solid #d7deea; border-radius:8px; box-shadow:0 10px 25px rgba(38, 54, 74, .16); }
    .room-picker-list[hidden] { display:none; }
    .room-picker-option { width:100%; padding:10px 12px; border:0; border-radius:6px; background:#fffdf8; color:#26364a; text-align:left; font:inherit; cursor:pointer; transition:background .15s ease; }
    .room-picker-option:hover, .room-picker-option:focus { background:#f1f3f6; outline:none; }
    .room-picker-empty { padding:14px 10px; color:#6d7788; text-align:center; }
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
            <div style="display:flex; justify-content:space-between; align-items:flex-end;">
                <label for="room_search" id="room_select_label">{{ $canUseFreeTime ? 'Pilih ruangan' : 'Ruangan' }}</label>
                <button type="button" id="btn_toggle_multi_room" class="btn ghost" style="min-height:26px; padding:2px 10px; font-size:11.5px; border-radius:6px; margin-bottom:7px; cursor:pointer;" title="Klik untuk meminjam lebih dari 1 ruangan sekaligus">
                    + Peminjaman lebih dari 1 ruangan
                </button>
            </div>
            <div class="room-picker">
                <div class="room-picker-input-wrap">
                    <input id="room_search" type="search" value="{{ $selectedRoom?->room_name }}" placeholder="Pilih atau cari ruangan..." autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="room-options" required>
                    <button type="button" id="room_picker_toggle" class="room-picker-toggle-btn" aria-label="Buka pilihan ruangan">▼</button>
                </div>
                <input id="room_id" type="hidden" name="room_id" value="{{ old('room_id') }}">
                <div id="multi_room_chips" style="display:none; flex-wrap:wrap; gap:6px; margin-top:8px;"></div>
                <ul id="room-options" class="room-picker-list" role="listbox" hidden>
                @foreach($rooms as $room)
                    <li role="option">
                        <button class="room-picker-option" type="button" data-room-id="{{ $room->id }}" data-room-name="{{ $room->room_name }}">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    <b>{{ $room->room_name }}</b>
                                    @if($room->location || $room->capacity)
                                        <div style="font-size:11px;color:#6d7788;margin-top:2px">
                                            {{ $room->location ? $room->location . ' · ' : '' }}Kapasitas {{ $room->capacity }} orang
                                        </div>
                                    @endif
                                </div>
                                <span class="room-check-badge" style="display:none; font-size:11.5px; font-weight:700; color:#166534; background:#dcfce7; padding:2px 8px; border-radius:999px;">✓ Terpilih</span>
                            </div>
                        </button>
                    </li>
                @endforeach
                <li class="room-picker-empty" data-room-empty hidden>Tidak ada ruangan yang cocok.</li>
                </ul>
            </div>
            @error('room_id')<p class="error">{{ $message }}</p>@enderror
            @error('room_ids')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div>
            <div style="display:flex; justify-content:space-between; align-items:flex-end;">
                <label for="event_date" id="event_date_label" style="margin:15px 0 7px;">Tanggal</label>
                <button type="button" id="btn_toggle_multi_day" class="btn ghost" style="min-height:26px; padding:2px 10px; font-size:11.5px; border-radius:6px; margin-bottom:7px; cursor:pointer;" title="Klik untuk meminjam ruangan lebih dari 1 hari">
                    + Peminjaman lebih dari 1 hari
                </button>
            </div>
            <input type="hidden" name="booking_type" id="booking_type" value="{{ old('booking_type', old('end_date') ? 'range' : 'single') }}">
            <input id="event_date" type="date" name="event_date" value="{{ old('event_date') }}" min="{{ $firstBookingDate }}" required>
            @if(!($isAdmin ?? (auth()->user()?->role?->role_name === 'Admin')))
                <p id="single_date_hint" class="muted">Peminjaman ruang hanya dapat diajukan mulai H-1 (besok) dan seterusnya.</p>
            @endif
            @error('event_date')<p class="error">{{ $message }}</p>@enderror

            {{-- Kolom Tanggal Selesai (Hanya muncul jika tombol peminjaman lebih dari 1 hari ditekan) --}}
            <div id="end_date_wrapper" style="display:none; margin-top:12px;">
                <label for="end_date" style="margin:0 0 7px;">Tanggal Selesai</label>
                <input id="end_date" type="date" name="end_date" value="{{ old('end_date') }}" min="{{ $firstBookingDate }}" disabled>
                @error('end_date')<p class="error">{{ $message }}</p>@enderror
            </div>

            {{-- Kotak Keterangan Tambahan Saat Mode Peminjaman Lebih Dari 1 Hari Aktif --}}
            <div id="multi_day_info" style="display:none; margin-top:12px; padding:10px 12px; border-radius:8px; background:#f1f5f9; border:1px solid #dce5ef; font-size:12px; line-height:1.45;">
                <div style="font-weight:600; color:#35465e; margin-bottom:2px;">ℹ️ Keterangan Peminjaman Lebih dari 1 Hari</div>
                <div style="color:#6d7788;">Peminjaman berturut-turut pada jam sesi yang sama setiap harinya (maksimal 30 hari).</div>
                <div id="multi_day_summary_text" style="margin-top:6px; font-weight:700; color:#166534; display:none;"></div>
            </div>
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
    <div id="availability-alert" style="display:none; margin-top:16px; padding:12px 16px; border-radius:8px; font-size:13px; line-height:1.5;"></div>
    <div style="display:flex; justify-content:flex-end; align-items:center; flex-wrap:wrap; gap:10px; margin-top:20px;">
        <button type="button" id="btn-validate-rooms" class="btn ghost" style="border:1.5px solid #2563eb; color:#1d4ed8; font-weight:600; padding:8px 14px; border-radius:8px; cursor:pointer;" title="Periksa apakah seluruh ruangan yang dipilih sedang dipakai atau tersedia">
            🔍 Validasi Ketersediaan Ruangan
        </button>
        <a class="btn ghost" href="{{ route('events.index') }}">Batal</a>
        <button class="btn" type="submit">Ajukan</button>
    </div>
</form>

<script>
    (function () {
        var search = document.getElementById('room_search');
        var roomId = document.getElementById('room_id');
        var list = document.getElementById('room-options');
        var options = Array.from(list.querySelectorAll('[data-room-id]'));
        var empty = list.querySelector('[data-room-empty]');
        var picker = search.closest('.room-picker');
        var toggleBtn = document.getElementById('room_picker_toggle');
        var selectedRoomName = search.value.trim();

        // Multi-ruangan state
        var btnToggleMultiRoom = document.getElementById('btn_toggle_multi_room');
        var roomSelectLabel = document.getElementById('room_select_label');
        var multiRoomChips = document.getElementById('multi_room_chips');
        var isMultiRoom = false;
        var selectedRooms = [];

        if (roomId.value && selectedRoomName) {
            selectedRooms.push({ id: roomId.value, name: selectedRoomName });
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
            var query = search.value.trim().toLocaleLowerCase();
            var currentSelected = (selectedRoomName || '').trim().toLocaleLowerCase();
            var showAll = forceShowAll || (query === currentSelected) || !query;

            var visibleCount = 0;
            options.forEach(function (option) {
                var matches = showAll || option.dataset.roomName.toLocaleLowerCase().includes(query);
                option.parentElement.hidden = !matches;
                if (matches) visibleCount++;

                // Sync badge
                var badge = option.querySelector('.room-check-badge');
                if (badge) {
                    var isSelected = selectedRooms.some(function (r) { return String(r.id) === String(option.dataset.roomId); });
                    badge.style.display = isSelected ? 'inline-block' : 'none';
                }
            });
            empty.hidden = visibleCount > 0;
        }

        function renderMultiRoomChips() {
            multiRoomChips.innerHTML = '';
            if (!isMultiRoom || selectedRooms.length === 0) {
                multiRoomChips.style.display = 'none';
                return;
            }
            multiRoomChips.style.display = 'flex';
            selectedRooms.forEach(function (r) {
                var chip = document.createElement('span');
                chip.style.cssText = 'background:#e0f2fe; color:#0369a1; padding:4px 10px; border-radius:16px; font-size:12px; font-weight:600; display:inline-flex; align-items:center; gap:6px; border:1px solid #bae6fd;';
                chip.innerHTML = '<span>' + r.name + '</span><button type="button" data-remove-room-id="' + r.id + '" style="background:transparent; border:none; color:#0284c7; cursor:pointer; font-weight:bold; font-size:13px; line-height:1; padding:0 2px;">✕</button><input type="hidden" name="room_ids[]" value="' + r.id + '">';
                multiRoomChips.appendChild(chip);
            });

            // Update main roomId to first selected
            if (selectedRooms.length > 0) {
                roomId.value = selectedRooms[0].id;
                search.removeAttribute('required');
                search.setCustomValidity('');
            } else {
                roomId.value = '';
                search.setAttribute('required', 'required');
            }
        }

        multiRoomChips.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-remove-room-id]');
            if (btn) {
                var rid = btn.dataset.removeRoomId;
                selectedRooms = selectedRooms.filter(function (r) { return String(r.id) !== String(rid); });
                renderMultiRoomChips();
                filterRooms(false);
            }
        });

        function toggleMultiRoomMode() {
            isMultiRoom = !isMultiRoom;
            if (isMultiRoom) {
                btnToggleMultiRoom.textContent = '✕ Batal (1 Ruangan)';
                btnToggleMultiRoom.style.color = '#35465e';
                btnToggleMultiRoom.style.background = '#e8edf4';
                roomSelectLabel.textContent = 'Pilih Ruangan (Bisa lebih dari 1)';
                search.placeholder = 'Pilih atau cari ruangan lainnya...';
                if (roomId.value && selectedRoomName && selectedRooms.length === 0) {
                    selectedRooms.push({ id: roomId.value, name: selectedRoomName });
                }
                search.value = '';
                renderMultiRoomChips();
            } else {
                btnToggleMultiRoom.textContent = '+ Peminjaman lebih dari 1 ruangan';
                btnToggleMultiRoom.style.color = '';
                btnToggleMultiRoom.style.background = '';
                roomSelectLabel.textContent = '{{ $canUseFreeTime ? "Pilih ruangan" : "Ruangan" }}';
                search.placeholder = 'Pilih atau cari ruangan...';
                multiRoomChips.style.display = 'none';
                multiRoomChips.innerHTML = '';
                if (selectedRooms.length > 0) {
                    var first = selectedRooms[0];
                    selectedRooms = [first];
                    roomId.value = first.id;
                    selectedRoomName = first.name;
                    search.value = first.name;
                }
                search.setAttribute('required', 'required');
            }
            filterRooms(true);
        }

        btnToggleMultiRoom.addEventListener('click', toggleMultiRoomMode);

        function chooseRoom(option) {
            var optId = option.dataset.roomId;
            var optName = option.dataset.roomName;

            if (isMultiRoom) {
                var existsIdx = selectedRooms.findIndex(function (r) { return String(r.id) === String(optId); });
                if (existsIdx >= 0) {
                    selectedRooms.splice(existsIdx, 1);
                } else {
                    selectedRooms.push({ id: optId, name: optName });
                }
                renderMultiRoomChips();
                filterRooms(false);
            } else {
                roomId.value = optId;
                selectedRoomName = optName;
                search.value = optName;
                selectedRooms = [{ id: optId, name: optName }];
                search.setCustomValidity('');
                closeList();
            }
        }

        search.addEventListener('focus', function () {
            openList(true);
            search.select();
        });
        search.addEventListener('click', function () {
            if (list.hidden) openList(true);
        });
        search.addEventListener('input', function () {
            search.setCustomValidity('');
            openList(false);
            var match = options.find(function (opt) {
                return opt.dataset.roomName.toLocaleLowerCase() === search.value.trim().toLocaleLowerCase();
            });
            if (match) {
                if (!isMultiRoom) {
                    roomId.value = match.dataset.roomId;
                    selectedRoomName = match.dataset.roomName;
                    selectedRooms = [{ id: match.dataset.roomId, name: match.dataset.roomName }];
                }
            } else if (!isMultiRoom) {
                roomId.value = '';
                selectedRooms = [];
            }
        });
        search.addEventListener('search', function () {
            if (!isMultiRoom) {
                roomId.value = '';
                selectedRoomName = '';
                selectedRooms = [];
            }
            openList(true);
        });

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
            } else if (event.key === 'ArrowDown' && list.hidden) {
                event.preventDefault();
                openList(true);
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
            if (isMultiRoom) {
                if (selectedRooms.length === 0) {
                    event.preventDefault();
                    search.setCustomValidity('Pilih minimal satu ruangan.');
                    search.reportValidity();
                }
            } else if (!roomId.value) {
                event.preventDefault();
                search.setCustomValidity('Pilih ruangan dari daftar yang tersedia.');
                search.reportValidity();
            }
        });

        // ==========================================
        // TOGGLE PEMINJAMAN LEBIH DARI 1 HARI
        // ==========================================
        var btnToggle = document.getElementById('btn_toggle_multi_day');
        var bookingTypeInput = document.getElementById('booking_type');
        var eventDateLabel = document.getElementById('event_date_label');
        var eventDateInput = document.getElementById('event_date');
        var endDateWrapper = document.getElementById('end_date_wrapper');
        var endDateInput = document.getElementById('end_date');
        var multiDayInfo = document.getElementById('multi_day_info');
        var singleDateHint = document.getElementById('single_date_hint');
        var summaryText = document.getElementById('multi_day_summary_text');

        var isMultiDay = bookingTypeInput.value === 'range';

        function updateMultiDayUI() {
            if (isMultiDay) {
                btnToggle.textContent = '✕ Batal (1 Hari)';
                btnToggle.style.color = '#35465e';
                btnToggle.style.background = '#e8edf4';
                eventDateLabel.textContent = 'Tanggal Mulai';
                endDateWrapper.style.display = 'block';
                endDateInput.disabled = false;
                endDateInput.required = true;
                multiDayInfo.style.display = 'block';
                if (singleDateHint) singleDateHint.style.display = 'none';
                bookingTypeInput.value = 'range';

                if (eventDateInput.value) {
                    endDateInput.min = eventDateInput.value;
                    if (!endDateInput.value || endDateInput.value < eventDateInput.value) {
                        endDateInput.value = eventDateInput.value;
                    }
                }
            } else {
                btnToggle.textContent = '+ Peminjaman lebih dari 1 hari';
                btnToggle.style.color = '';
                btnToggle.style.background = '';
                eventDateLabel.textContent = 'Tanggal';
                endDateWrapper.style.display = 'none';
                endDateInput.disabled = true;
                endDateInput.required = false;
                endDateInput.value = '';
                multiDayInfo.style.display = 'none';
                if (singleDateHint) singleDateHint.style.display = 'block';
                bookingTypeInput.value = 'single';
            }
            updateSummary();
        }

        function updateSummary() {
            if (!isMultiDay) {
                summaryText.style.display = 'none';
                return;
            }

            var sVal = eventDateInput.value;
            var eVal = endDateInput.value;
            if (!sVal || !eVal) {
                summaryText.style.display = 'none';
                return;
            }

            var s = new Date(sVal + 'T00:00:00');
            var e = new Date(eVal + 'T00:00:00');
            if (e < s) {
                summaryText.style.display = 'block';
                summaryText.style.color = '#b91c1c';
                summaryText.textContent = '⚠️ Tanggal selesai harus sama atau setelah tanggal mulai.';
                return;
            }

            var days = Math.round((e.getTime() - s.getTime()) / (1000 * 3600 * 24)) + 1;
            summaryText.style.display = 'block';
            summaryText.style.color = '#166534';
            summaryText.textContent = '✨ Total Durasi: ' + days + ' Hari berturut-turut (' + sVal + ' s/d ' + eVal + ')';
        }

        btnToggle.addEventListener('click', function () {
            isMultiDay = !isMultiDay;
            updateMultiDayUI();
        });

        eventDateInput.addEventListener('change', function () {
            if (isMultiDay && this.value) {
                endDateInput.min = this.value;
                if (!endDateInput.value || endDateInput.value < this.value) {
                    endDateInput.value = this.value;
                }
            }
            updateSummary();
        });

        endDateInput.addEventListener('change', updateSummary);

        // Inisialisasi tampilan awal
        updateMultiDayUI();

        // ==========================================
        // VALIDASI KETERSEDIAAN RUANGAN (TOMBOL DI DEKAT BATAL)
        // ==========================================
        var btnValidate = document.getElementById('btn-validate-rooms');
        var availabilityAlert = document.getElementById('availability-alert');

        btnValidate.addEventListener('click', function () {
            // Ambil daftar ruangan
            var roomIds = [];
            if (isMultiRoom && selectedRooms.length > 0) {
                roomIds = selectedRooms.map(function (r) { return r.id; });
            } else if (roomId.value) {
                roomIds = [roomId.value];
            }

            var sDate = eventDateInput.value;
            var eDate = (isMultiDay && endDateInput.value) ? endDateInput.value : '';
            var startTimeSelect = document.getElementById('start_time');
            var endTimeSelect = document.getElementById('end_time');
            var sTime = startTimeSelect ? startTimeSelect.value : '';
            var eTime = endTimeSelect ? endTimeSelect.value : '';

            if (sTime === 'free') {
                var sFree = document.getElementById('start_time_free');
                sTime = sFree ? sFree.value : '';
            }
            if (eTime === 'free') {
                var eFree = document.getElementById('end_time_free');
                eTime = eFree ? eFree.value : '';
            }

            if (roomIds.length === 0 || !sDate || !sTime || !eTime) {
                availabilityAlert.style.display = 'block';
                availabilityAlert.style.background = '#fef2f2';
                availabilityAlert.style.border = '1px solid #fecaca';
                availabilityAlert.style.color = '#991b1b';
                availabilityAlert.innerHTML = '⚠️ <b>Perhatian:</b> Mohon pilih ruangan, tanggal, serta jam mulai dan selesai terlebih dahulu untuk melakukan validasi.';
                return;
            }

            var originalBtnHtml = btnValidate.innerHTML;
            btnValidate.disabled = true;
            btnValidate.innerHTML = '⏳ Memeriksa ketersediaan...';

            var params = new URLSearchParams({
                room_ids: roomIds.join(','),
                event_date: sDate,
                start_time: sTime,
                end_time: eTime
            });
            if (isMultiDay && eDate) {
                params.append('end_date', eDate);
            }

            fetch('{{ route("events.check-availability") }}?' + params.toString(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                btnValidate.disabled = false;
                btnValidate.innerHTML = originalBtnHtml;
                availabilityAlert.style.display = 'block';

                if (data.available === true) {
                    availabilityAlert.style.background = '#f0fdf4';
                    availabilityAlert.style.border = '1px solid #bbf7d0';
                    availabilityAlert.style.color = '#166534';
                    availabilityAlert.innerHTML = '✅ <b>Ketersediaan Terkonfirmasi:</b> ' + (data.message || 'Semua ruangan tersedia pada waktu yang dipilih.') + ' Silakan klik tombol <b>Ajukan</b> untuk melanjutkan.';
                } else {
                    availabilityAlert.style.background = '#fef2f2';
                    availabilityAlert.style.border = '1px solid #fecaca';
                    availabilityAlert.style.color = '#991b1b';
                    availabilityAlert.innerHTML = '❌ <b>Bentrok Jadwal:</b> ' + (data.message || 'Ruangan sedang digunakan pada jadwal tersebut. Silakan pilih ruangan atau waktu lain.');
                }
            })
            .catch(function (err) {
                btnValidate.disabled = false;
                btnValidate.innerHTML = originalBtnHtml;
                availabilityAlert.style.display = 'block';
                availabilityAlert.style.background = '#fef2f2';
                availabilityAlert.style.border = '1px solid #fecaca';
                availabilityAlert.style.color = '#991b1b';
                availabilityAlert.innerHTML = '⚠️ Terjadi kesalahan saat memeriksa ketersediaan ruangan. Silakan coba lagi.';
            });
        });
    })();
</script>
<dialog id="confirm-booking-dialog" class="confirm-dialog" aria-labelledby="confirm-booking-heading">
    <div class="confirm-dialog-heading">
        <div>
            <h2 id="confirm-booking-heading">Konfirmasi Pengajuan Ruangan</h2>
            <p>Periksa kembali detail informasi peminjaman sebelum mengajukan</p>
        </div>
        <button class="confirm-dialog-close" type="button" id="confirm-booking-close" aria-label="Tutup dialog">×</button>
    </div>
    <div class="confirm-dialog-body">
        <div class="confirm-dialog-details">
            <div class="confirm-item">
                <span class="confirm-label">Nama Kegiatan:</span>
                <span class="confirm-value" id="confirm-booking-title">-</span>
            </div>
            <div class="confirm-item">
                <span class="confirm-label">Ruangan:</span>
                <span class="confirm-value" id="confirm-booking-room">-</span>
            </div>
            <div class="confirm-item">
                <span class="confirm-label">Tanggal:</span>
                <span class="confirm-value" id="confirm-booking-date">-</span>
            </div>
            <div class="confirm-item">
                <span class="confirm-label">Waktu / Sesi:</span>
                <span class="confirm-value" id="confirm-booking-time">-</span>
            </div>
        </div>
    </div>
    <div class="confirm-dialog-footer">
        <button class="btn ghost" type="button" id="confirm-booking-cancel">Batal</button>
        <button class="btn" type="button" id="confirm-booking-ok">Ya, Kirim Pengajuan</button>
    </div>
</dialog>

<script>
    (function () {
        var form = document.getElementById('event-form');
        var dialog = document.getElementById('confirm-booking-dialog');
        var btnCancel = document.getElementById('confirm-booking-cancel');
        var btnClose = document.getElementById('confirm-booking-close');
        var btnOk = document.getElementById('confirm-booking-ok');

        if (btnClose && dialog) {
            btnClose.addEventListener('click', function () { dialog.close(); });
        }
        if (btnCancel && dialog) {
            btnCancel.addEventListener('click', function () { dialog.close(); });
        }
        if (btnOk && dialog && form) {
            btnOk.addEventListener('click', function () {
                dialog.close();
                form.dataset.confirmed = 'true';
                form.submit();
            });
        }

        form.addEventListener('submit', function (event) {
            if (form.dataset.confirmed === 'true') {
                return;
            }

            var roomSearchEl = document.getElementById('room_search');
            var room = roomSearchEl ? roomSearchEl.value.trim() : '';
            var multiChips = Array.from(document.querySelectorAll('#multi_room_chips span span'));
            if (multiChips.length > 0) {
                room = multiChips.map(function(s){ return s.textContent.trim(); }).filter(Boolean).join(', ');
            }
            var titleEl = document.getElementById('title');
            var title = titleEl ? titleEl.value.trim() : '';
            var dateEl = document.getElementById('event_date');
            var date = dateEl ? dateEl.value : '';
            var bookingTypeEl = document.getElementById('booking_type');
            var isRange = bookingTypeEl && bookingTypeEl.value === 'range';
            var endDateEl = document.getElementById('end_date');
            var endDate = endDateEl ? endDateEl.value : '';
            if (isRange && endDate && endDate > date) {
                date = date + ' s/d ' + endDate;
            }

            var startEl = document.getElementById('start_time');
            var startFreeEl = document.getElementById('start_time_free');
            var start = (startEl && startEl.value === 'free' && startFreeEl)
                ? startFreeEl.value
                : (startEl ? startEl.value : '');

            var endEl = document.getElementById('end_time');
            var endFreeEl = document.getElementById('end_time_free');
            var end = (endEl && endEl.value === 'free' && endFreeEl)
                ? endFreeEl.value
                : (endEl ? endEl.value : '');

            var message = 'Kirim pengajuan agenda ini?\n\nKegiatan: ' + title
                + '\nRuangan: ' + room
                + '\nTanggal: ' + date
                + '\nWaktu: ' + start + ' - ' + end;

            if (dialog && typeof dialog.showModal === 'function') {
                event.preventDefault();
                document.getElementById('confirm-booking-title').textContent = title || '-';
                document.getElementById('confirm-booking-room').textContent = room || '-';
                document.getElementById('confirm-booking-date').textContent = date || '-';
                document.getElementById('confirm-booking-time').textContent = (start || '-') + ' - ' + (end || '-');
                dialog.showModal();
                return;
            }

            if (!window.confirm(message)) event.preventDefault();
        });
    })();
</script>
@if($canUseFreeTime)
<script>
    ['start_time', 'end_time'].forEach(function (field) {
        var select = document.getElementById(field);
        var container = document.getElementById(field + '_free_container');
        var input = document.getElementById(field + '_free');
        if (select && container && input) {
            function toggleFreeTime() {
                var isFree = select.value === 'free';
                container.hidden = !isFree;
                input.required = isFree;
                input.disabled = !isFree;
            }
            select.addEventListener('change', toggleFreeTime);
            toggleFreeTime();
        }
    });
</script>
@endif
@endsection
