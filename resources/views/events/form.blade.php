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

    .booking-type-selector {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 12px;
        margin-top: 6px;
    }
    .booking-type-card {
        position: relative;
        display: block;
        padding: 14px 16px;
        border: 2px solid #dce5ef;
        border-radius: 10px;
        background: #fffdf8;
        cursor: pointer;
        transition: all .2s ease;
    }
    .booking-type-card:hover {
        border-color: #667b99;
        background: #f8fafc;
    }
    .booking-type-card input[type="radio"] {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }
    .booking-type-card.is-active {
        border-color: #35465e;
        background: #f1f5f9;
        box-shadow: 0 4px 14px rgba(38, 54, 74, .08);
    }
    .booking-type-content {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .booking-type-icon {
        font-size: 26px;
        line-height: 1;
        flex-shrink: 0;
    }
    .booking-type-title {
        display: block;
        color: #26364a;
        font-size: 14px;
        font-weight: 700;
    }
    .booking-type-desc {
        display: block;
        color: #6d7788;
        font-size: 12px;
        margin-top: 2px;
    }

    .date-summary-box {
        display: none;
        margin-top: 10px;
        padding: 12px 14px;
        border-radius: 8px;
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        color: #166534;
        font-size: 13px;
        line-height: 1.5;
    }
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
            <input id="title" name="title" value="{{ old('title') }}" placeholder="Contoh: Uji Kompetensi Keahlian (UKK) / Workshop IT" required>
        </div>

        <div class="full">
            <label style="font-weight:700">Tipe Durasi Peminjaman</label>
            <div class="booking-type-selector">
                <label class="booking-type-card {{ old('booking_type', 'single') === 'single' ? 'is-active' : '' }}" id="card_type_single">
                    <input type="radio" name="booking_type" value="single" @checked(old('booking_type', 'single') === 'single')>
                    <span class="booking-type-content">
                        <span class="booking-type-icon">📅</span>
                        <span>
                            <strong class="booking-type-title">Satu Hari</strong>
                            <small class="booking-type-desc">Kegiatan berlangsung selesai dalam 1 hari</small>
                        </span>
                    </span>
                </label>
                <label class="booking-type-card {{ old('booking_type') === 'range' ? 'is-active' : '' }}" id="card_type_range">
                    <input type="radio" name="booking_type" value="range" @checked(old('booking_type') === 'range')>
                    <span class="booking-type-content">
                        <span class="booking-type-icon">📆</span>
                        <span>
                            <strong class="booking-type-title">Lebih dari 1 Hari (Rentang Tanggal)</strong>
                            <small class="booking-type-desc">Kegiatan berturut-turut (misal: Ujian/UKK, Workshop, Lomba)</small>
                        </span>
                    </span>
                </label>
            </div>
        </div>

        <div>
            <label for="room_search">{{ $canUseFreeTime ? 'Pilih ruangan' : 'Ruangan' }}</label>
            <div class="room-picker">
                <div class="room-picker-input-wrap">
                    <input id="room_search" type="search" value="{{ $selectedRoom?->room_name }}" placeholder="Pilih atau cari ruangan..." autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="room-options" required>
                    <button type="button" id="room_picker_toggle" class="room-picker-toggle-btn" aria-label="Buka pilihan ruangan">▼</button>
                </div>
                <input id="room_id" type="hidden" name="room_id" value="{{ old('room_id') }}">
                <ul id="room-options" class="room-picker-list" role="listbox" hidden>
                @foreach($rooms as $room)
                    <li role="option">
                        <button class="room-picker-option" type="button" data-room-id="{{ $room->id }}" data-room-name="{{ $room->room_name }}">
                            <b>{{ $room->room_name }}</b>
                            @if($room->location || $room->capacity)
                                <div style="font-size:11px;color:#6d7788;margin-top:2px">
                                    {{ $room->location ? $room->location . ' · ' : '' }}Kapasitas {{ $room->capacity }} orang
                                </div>
                            @endif
                        </button>
                    </li>
                @endforeach
                <li class="room-picker-empty" data-room-empty hidden>Tidak ada ruangan yang cocok.</li>
                </ul>
            </div>
            @error('room_id')<p class="error">{{ $message }}</p>@enderror
        </div>

        <div id="single_date_container">
            <label for="event_date" id="event_date_label">Tanggal Kegiatan</label>
            <input id="event_date" type="date" name="event_date" value="{{ old('event_date') }}" min="{{ $firstBookingDate }}" required>
            <p class="muted">Peminjaman ruang hanya dapat diajukan mulai H-1 (besok) dan seterusnya.</p>
            @error('event_date')<p class="error">{{ $message }}</p>@enderror
        </div>

        <div id="end_date_container" style="display:none">
            <label for="end_date">Tanggal Selesai</label>
            <input id="end_date" type="date" name="end_date" value="{{ old('end_date') }}" min="{{ $firstBookingDate }}">
            <p class="muted">Pilih tanggal terakhir pelaksanaan kegiatan.</p>
            @error('end_date')<p class="error">{{ $message }}</p>@enderror
        </div>

        <div class="full" id="date_summary_wrapper" style="display:none">
            <div id="date_range_summary" class="date-summary-box"></div>
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
        var picker = search.closest('.room-picker');
        var toggleBtn = document.getElementById('room_picker_toggle');
        var selectedRoomName = search.value.trim();

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
            });
            empty.hidden = visibleCount > 0;
        }

        function chooseRoom(option) {
            roomId.value = option.dataset.roomId;
            selectedRoomName = option.dataset.roomName;
            search.value = option.dataset.roomName;
            search.setCustomValidity('');
            closeList();
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
                roomId.value = match.dataset.roomId;
                selectedRoomName = match.dataset.roomName;
            } else {
                roomId.value = '';
            }
        });
        search.addEventListener('search', function () {
            roomId.value = '';
            selectedRoomName = '';
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

        // ==========================================
        // DURATION TYPE & MULTI-DAY HANDLING
        // ==========================================
        var typeRadios = document.querySelectorAll('input[name="booking_type"]');
        var cardSingle = document.getElementById('card_type_single');
        var cardRange = document.getElementById('card_type_range');
        var eventDateLabel = document.getElementById('event_date_label');
        var eventDateInput = document.getElementById('event_date');
        var endDateContainer = document.getElementById('end_date_container');
        var endDateInput = document.getElementById('end_date');
        var summaryWrapper = document.getElementById('date_summary_wrapper');
        var summaryBox = document.getElementById('date_range_summary');

        var startSelect = document.getElementById('start_time');
        var endSelect = document.getElementById('end_time');

        function isRangeMode() {
            var selected = document.querySelector('input[name="booking_type"]:checked');
            return selected && selected.value === 'range';
        }

        function formatIdDate(dateStr) {
            if (!dateStr) return '';
            var parts = dateStr.split('-');
            if (parts.length !== 3) return dateStr;
            var d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
            var months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            var days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            return days[d.getDay()] + ', ' + d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();
        }

        function updateDurationView() {
            var range = isRangeMode();
            cardSingle.classList.toggle('is-active', !range);
            cardRange.classList.toggle('is-active', range);

            if (range) {
                eventDateLabel.textContent = 'Tanggal Mulai';
                endDateContainer.style.display = 'block';
                endDateInput.required = true;
                endDateInput.disabled = false;

                if (eventDateInput.value) {
                    endDateInput.min = eventDateInput.value;
                    if (!endDateInput.value || endDateInput.value < eventDateInput.value) {
                        endDateInput.value = eventDateInput.value;
                    }
                }
            } else {
                eventDateLabel.textContent = 'Tanggal Kegiatan';
                endDateContainer.style.display = 'none';
                endDateInput.required = false;
                endDateInput.disabled = true;
            }

            calculateDateRangeSummary();
        }

        function calculateDateRangeSummary() {
            if (!isRangeMode()) {
                summaryWrapper.style.display = 'none';
                summaryBox.style.display = 'none';
                return;
            }

            var startStr = eventDateInput.value;
            var endStr = endDateInput.value;

            if (!startStr || !endStr) {
                summaryWrapper.style.display = 'none';
                summaryBox.style.display = 'none';
                return;
            }

            var startParts = startStr.split('-');
            var endParts = endStr.split('-');
            var startDate = new Date(parseInt(startParts[0], 10), parseInt(startParts[1], 10) - 1, parseInt(startParts[2], 10));
            var endDate = new Date(parseInt(endParts[0], 10), parseInt(endParts[1], 10) - 1, parseInt(endParts[2], 10));

            var diffTime = endDate.getTime() - startDate.getTime();
            var totalDays = Math.round(diffTime / (1000 * 3600 * 24)) + 1;

            if (totalDays <= 0) {
                endDateInput.value = startStr;
                totalDays = 1;
            }

            var startTimeText = startSelect.value === 'free' ? 'Waktu Bebas' : startSelect.value;
            var endTimeText = endSelect.value === 'free' ? 'Waktu Bebas' : endSelect.value;
            var timeRangeText = (startTimeText && endTimeText) ? (startTimeText + ' – ' + endTimeText) : 'jam sesi yang dipilih';

            summaryWrapper.style.display = 'block';
            summaryBox.style.display = 'block';

            if (totalDays === 1) {
                summaryBox.innerHTML = '<strong>✨ Durasi: 1 Hari</strong> (' + formatIdDate(startStr) + ').<br>' +
                    '<span style="font-size:12px;opacity:0.9">Agenda akan dipinjam pada pukul ' + timeRangeText + '.</span>';
            } else {
                summaryBox.innerHTML = '<strong>✨ Peminjaman Multi-Hari: ' + totalDays + ' Hari Berturut-turut</strong><br>' +
                    'Rentang: <b>' + formatIdDate(startStr) + '</b> s/d <b>' + formatIdDate(endStr) + '</b>.<br>' +
                    '<span style="font-size:12px;opacity:0.9">Sistem otomatis memesan ruangan untuk ' + totalDays + ' hari pada pukul ' + timeRangeText + ' setiap harinya.</span>';
            }
        }

        typeRadios.forEach(function (radio) {
            radio.addEventListener('change', updateDurationView);
        });

        eventDateInput.addEventListener('change', function () {
            if (isRangeMode() && this.value) {
                endDateInput.min = this.value;
                if (!endDateInput.value || endDateInput.value < this.value) {
                    endDateInput.value = this.value;
                }
            }
            calculateDateRangeSummary();
        });

        endDateInput.addEventListener('change', calculateDateRangeSummary);
        startSelect.addEventListener('change', calculateDateRangeSummary);
        endSelect.addEventListener('change', calculateDateRangeSummary);

        // Submit validation
        document.getElementById('event-form').addEventListener('submit', function (event) {
            if (!roomId.value) {
                event.preventDefault();
                search.setCustomValidity('Pilih ruangan dari daftar yang tersedia.');
                search.reportValidity();
                return;
            }

            var room = document.getElementById('room_search').value;
            var title = document.getElementById('title').value.trim();
            var date = document.getElementById('event_date').value;
            var isRange = isRangeMode();
            var endDate = endDateInput.value;

            var start = document.getElementById('start_time').value === 'free'
                ? (document.getElementById('start_time_free') ? document.getElementById('start_time_free').value : 'Waktu Bebas')
                : document.getElementById('start_time').value;
            var end = document.getElementById('end_time').value === 'free'
                ? (document.getElementById('end_time_free') ? document.getElementById('end_time_free').value : 'Waktu Bebas')
                : document.getElementById('end_time').value;

            var dateText = date;
            if (isRange && endDate && endDate !== date) {
                var sParts = date.split('-');
                var eParts = endDate.split('-');
                var sDate = new Date(parseInt(sParts[0], 10), parseInt(sParts[1], 10) - 1, parseInt(sParts[2], 10));
                var eDate = new Date(parseInt(eParts[0], 10), parseInt(eParts[1], 10) - 1, parseInt(eParts[2], 10));
                var days = Math.round((eDate.getTime() - sDate.getTime()) / (1000 * 3600 * 24)) + 1;
                dateText = date + ' s/d ' + endDate + ' (' + days + ' hari berturut-turut)';
            }

            var message = 'Kirim pengajuan agenda ini?\n\n'
                + 'Kegiatan: ' + title + '\n'
                + 'Ruangan: ' + room + '\n'
                + 'Tanggal: ' + dateText + '\n'
                + 'Waktu: ' + start + ' - ' + end;

            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });

        // Initialize state
        updateDurationView();
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
