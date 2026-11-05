@php
    $today = now('Asia/Jakarta')->toDateString();
    $daysWithEvents = $events->filter(fn ($dayEvents) => $dayEvents->isNotEmpty());
@endphp

<div class="calendar-page">
    <div class="calendar-page-heading">
        <div>
            <h1>Kalender Agenda</h1>
            <p>Agenda peminjaman ruangan yang telah disetujui & hari libur nasional.</p>
        </div>
        <div class="approved-count"><strong>{{ $approvedCount }}</strong> agenda disetujui bulan ini</div>
    </div>

    <section class="calendar-card" aria-label="Kalender agenda bulanan">
        <div class="calendar-toolbar">
            <h2 class="month-title">{{ $month->locale('id')->translatedFormat('F') }} <span>{{ $month->format('Y') }}</span></h2>
            <nav class="calendar-controls" aria-label="Navigasi kalender">
                <a class="calendar-control today" href="{{ route($calendarRoute) }}">Hari ini</a>
                <a class="calendar-control" href="{{ route($calendarRoute, ['month' => $month->copy()->subMonth()->format('Y-m')]) }}" aria-label="Bulan sebelumnya">‹</a>
                <a class="calendar-control" href="{{ route($calendarRoute, ['month' => $month->copy()->addMonth()->format('Y-m')]) }}" aria-label="Bulan berikutnya">›</a>
            </nav>
        </div>

        {{-- Petunjuk Warna Kalender (Legend Bar) --}}
        <div class="calendar-legend-bar" aria-label="Petunjuk Warna Kalender">
            <span class="legend-item">
                <span class="legend-dot dot-green"></span> Guru &amp; Organisasi (Hijau)
            </span>
            <span class="legend-item">
                <span class="legend-dot dot-yellow"></span> Admin (Kuning)
            </span>
            <span class="legend-item">
                <span class="legend-dot dot-red"></span> Libur Nasional (Merah)
            </span>
        </div>

        <div class="calendar-scroll">
            <div class="calendar-weekdays" aria-hidden="true">
                <span>SEN</span><span>SEL</span><span>RAB</span><span>KAM</span><span>JUM</span><span class="weekend-header">SAB</span><span class="weekend-header">MIN</span>
            </div>
            <div class="calendar-grid">
                @for($cell = 0; $cell <= $calendarStart->diffInDays($calendarEnd); $cell++)
                    @php
                        $date = $calendarStart->copy()->addDays($cell);
                        $dateKey = $date->toDateString();
                        $dayEvents = $events->get($dateKey, collect());
                        $holidayName = $holidays[$dateKey] ?? null;
                        $hasContent = $dayEvents->isNotEmpty() || !empty($holidayName);
                        $dialogId = 'agenda-'.str_replace('-', '', $dateKey);
                        $isWeekend = $date->isSaturday() || $date->isSunday();
                        $dayClasses = [
                            'calendar-day',
                            $date->month !== $month->month ? 'outside-month' : '',
                            $dateKey === $today ? 'today' : '',
                            $isWeekend ? 'weekend' : '',
                            !empty($holidayName) ? 'has-holiday' : '',
                        ];
                    @endphp
                    <article class="{{ implode(' ', array_filter($dayClasses)) }}">
                        @if($hasContent)
                            <button
                                class="day-number {{ !empty($holidayName) ? 'holiday-number' : '' }}"
                                type="button"
                                data-dialog="{{ $dialogId }}"
                                aria-label="{{ $date->locale('id')->translatedFormat('l, d F Y') }}{{ !empty($holidayName) ? ' (Libur: '.$holidayName.')' : '' }}, {{ $dayEvents->count() }} agenda"
                            >{{ $date->day }}</button>
                            <span class="day-event-indicator" aria-hidden="true"></span>
                        @else
                            <span class="day-number {{ !empty($holidayName) ? 'holiday-number' : '' }}">{{ $date->day }}</span>
                        @endif

                        {{-- Menampilkan Hari Libur Nasional Otomatis (Merah) --}}
                        @if(!empty($holidayName))
                            <div class="agenda-chip chip-holiday" data-dialog="{{ $dialogId }}" role="button" title="Hari Libur Nasional: {{ $holidayName }}">
                                <span class="agenda-time holiday-tag">LIBUR NASIONAL</span>
                                <span class="agenda-caption holiday-caption">{{ $holidayName }}</span>
                            </div>
                        @endif

                        {{-- Menampilkan Agenda Peminjaman Ruangan (Hijau: Guru/Organisasi, Kuning: Admin) --}}
                        @php
                            $maxVisibleEvents = !empty($holidayName) ? 1 : 2;
                        @endphp
                        @foreach($dayEvents->take($maxVisibleEvents) as $event)
                            <div class="agenda-chip {{ $event->calendar_badge_class }}"
                                 data-dialog="{{ $dialogId }}"
                                 role="button"
                                 title="{{ substr($event->start_time, 0, 5) }}–{{ substr($event->end_time, 0, 5) }} · {{ $event->room->room_name }} · {{ $event->title }} ({{ $event->user?->role?->role_name }})">
                                <span class="agenda-time">{{ substr($event->start_time, 0, 5) }}–{{ substr($event->end_time, 0, 5) }}</span>
                                <span class="agenda-caption">{{ $event->room->room_name }} · {{ $event->title }}</span>
                            </div>
                        @endforeach

                        @if($dayEvents->count() > $maxVisibleEvents)
                            <button class="calendar-more" type="button" data-dialog="{{ $dialogId }}">+{{ $dayEvents->count() - $maxVisibleEvents }} lainnya</button>
                        @endif
                    </article>
                @endfor
            </div>
        </div>
    </section>
</div>

@include('calendar.dialogs', [
    'calendarStart' => $calendarStart,
    'calendarEnd' => $calendarEnd,
    'events' => $events,
    'holidays' => $holidays,
])

<script>
    document.querySelectorAll('[data-dialog]').forEach(function (element) {
        element.addEventListener('click', function () {
            var dialog = document.getElementById(element.dataset.dialog);
            if (dialog) {
                dialog.showModal();
            }
        });
    });

    document.querySelectorAll('.agenda-dialog').forEach(function (dialog) {
        dialog.addEventListener('click', function (event) {
            if (event.target === dialog || event.target.closest('[data-close-dialog]')) {
                dialog.close();
            }
        });
    });
</script>
