@php
    $today = now()->toDateString();
    $overflowDays = $events->filter(fn ($dayEvents) => $dayEvents->count() > 2);
@endphp

<div class="calendar-page">
    <div class="calendar-page-heading">
        <div>
            <h1>Kalender Agenda</h1>
            <p>Agenda sekolah yang telah disetujui.</p>
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

        <div class="calendar-scroll">
            <div class="calendar-weekdays" aria-hidden="true">
                <span>SEN</span><span>SEL</span><span>RAB</span><span>KAM</span><span>JUM</span><span>SAB</span><span>MIN</span>
            </div>
            <div class="calendar-grid">
                @for($cell = 0; $cell <= $calendarStart->diffInDays($calendarEnd); $cell++)
                    @php
                        $date = $calendarStart->copy()->addDays($cell);
                        $dateKey = $date->toDateString();
                        $dayEvents = $events->get($dateKey, collect());
                        $dialogId = 'agenda-'.str_replace('-', '', $dateKey);
                    @endphp
                    <article class="calendar-day {{ $date->month !== $month->month ? 'outside-month' : '' }} {{ $dateKey === $today ? 'today' : '' }}">
                        <span class="day-number">{{ $date->day }}</span>
                        @foreach($dayEvents->take(2) as $event)
                            <div class="agenda-chip" title="{{ substr($event->start_time, 0, 5) }}–{{ substr($event->end_time, 0, 5) }} · {{ $event->room->room_name }} · {{ $event->title }}">
                                <span class="agenda-time">{{ substr($event->start_time, 0, 5) }}–{{ substr($event->end_time, 0, 5) }}</span>
                                <span class="agenda-caption">{{ $event->room->room_name }} · {{ $event->title }}</span>
                            </div>
                        @endforeach
                        @if($dayEvents->count() > 2)
                            <button class="calendar-more" type="button" data-dialog="{{ $dialogId }}">+{{ $dayEvents->count() - 2 }} lainnya</button>
                        @endif
                    </article>
                @endfor
            </div>
        </div>
    </section>
</div>

@include('calendar.dialogs')

<script>
    document.querySelectorAll('[data-dialog]').forEach(function (button) {
        button.addEventListener('click', function () {
            var dialog = document.getElementById(button.dataset.dialog);
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
