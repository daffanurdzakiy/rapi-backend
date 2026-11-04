@foreach($daysWithEvents as $dateKey => $dayEvents)
    <dialog class="agenda-dialog" id="agenda-{{ str_replace('-', '', $dateKey) }}" aria-labelledby="heading-{{ str_replace('-', '', $dateKey) }}">
        <div class="dialog-heading">
            <div>
                <h2 id="heading-{{ str_replace('-', '', $dateKey) }}">{{ \Carbon\Carbon::parse($dateKey)->locale('id')->translatedFormat('l, d F Y') }}</h2>
                <p>{{ $dayEvents->count() }} agenda disetujui</p>
            </div>
            <button class="dialog-close" type="button" data-close-dialog aria-label="Tutup daftar agenda">×</button>
        </div>
        <div class="dialog-list">
            @foreach($dayEvents as $event)
                <div class="dialog-event">
                    <div class="dialog-event-time">
                        {{ substr($event->start_time, 0, 5) }}
                        <span class="dialog-event-end">{{ substr($event->end_time, 0, 5) }}</span>
                    </div>
                    <div>
                        <div class="dialog-event-title">{{ $event->title }}</div>
                        <div class="dialog-event-room">{{ $event->room->room_name }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </dialog>
@endforeach
