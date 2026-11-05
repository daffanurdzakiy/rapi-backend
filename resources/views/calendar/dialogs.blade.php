@php
    $totalCells = $calendarStart->diffInDays($calendarEnd);
@endphp

@for($cell = 0; $cell <= $totalCells; $cell++)
    @php
        $date = $calendarStart->copy()->addDays($cell);
        $dateKey = $date->toDateString();
        $dayEvents = $events->get($dateKey, collect());
        $holidayName = $holidays[$dateKey] ?? null;
    @endphp

    @if($dayEvents->isNotEmpty() || !empty($holidayName))
        <dialog class="agenda-dialog" id="agenda-{{ str_replace('-', '', $dateKey) }}" aria-labelledby="heading-{{ str_replace('-', '', $dateKey) }}">
            <div class="dialog-heading">
                <div>
                    <h2 id="heading-{{ str_replace('-', '', $dateKey) }}">
                        {{ $date->locale('id')->translatedFormat('l, d F Y') }}
                    </h2>
                    <p>
                        @if($dayEvents->isNotEmpty() && !empty($holidayName))
                            1 Hari Libur Nasional &amp; {{ $dayEvents->count() }} agenda peminjaman
                        @elseif(!empty($holidayName))
                            Hari Libur Nasional
                        @else
                            {{ $dayEvents->count() }} agenda disetujui
                        @endif
                    </p>
                </div>
                <button class="dialog-close" type="button" data-close-dialog aria-label="Tutup daftar agenda">×</button>
            </div>

            {{-- Banner Hari Libur Nasional Jika Ada --}}
            @if(!empty($holidayName))
                <div class="dialog-holiday-box">
                    <span class="dialog-holiday-dot">🔴</span>
                    <div>
                        <div class="dialog-holiday-tag">HARI LIBUR NASIONAL</div>
                        <div class="dialog-holiday-name">{{ $holidayName }}</div>
                    </div>
                </div>
            @endif

            @if($dayEvents->isNotEmpty())
                <div class="dialog-list">
                    @foreach($dayEvents as $event)
                        <div class="dialog-event {{ $event->calendar_badge_class }}">
                            <div class="dialog-event-time">
                                {{ substr($event->start_time, 0, 5) }}
                                <span class="dialog-event-end">{{ substr($event->end_time, 0, 5) }}</span>
                            </div>
                            <div style="flex: 1 1 auto; min-width: 0;">
                                <div class="dialog-event-title">{{ $event->title }}</div>
                                <div class="dialog-event-room">{{ $event->room->room_name }}</div>
                                <div class="dialog-event-applicant" style="margin-top: 5px;">
                                    <span class="role-pill {{ $event->calendar_badge_class }}">
                                        {{ $event->user?->role?->role_name ?? 'Pemohon' }}: {{ $event->user?->name ?? '-' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </dialog>
    @endif
@endfor
