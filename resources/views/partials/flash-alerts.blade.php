@php
    $flashMap = [
        'success' => ['variant' => 'success', 'title' => 'Berhasil', 'role' => 'status', 'autoclose' => true],
        'error' => ['variant' => 'error', 'title' => 'Terjadi Kesalahan', 'role' => 'alert', 'autoclose' => false],
        'mail_error' => ['variant' => 'error', 'title' => 'Pengiriman Email Gagal', 'role' => 'alert', 'autoclose' => false],
        'warning' => ['variant' => 'warning', 'title' => 'Perhatian', 'role' => 'alert', 'autoclose' => false],
        'status' => ['variant' => 'info', 'title' => 'Informasi', 'role' => 'status', 'autoclose' => true],
        'info' => ['variant' => 'info', 'title' => 'Informasi', 'role' => 'status', 'autoclose' => true],
    ];
    $keysToRender = $onlyKeys ?? array_keys($flashMap);
    $isCompact = (bool) ($compact ?? false);
@endphp

@foreach($keysToRender as $flashKey)
    @if(session($flashKey) && isset($flashMap[$flashKey]))
        @php($cfg = $flashMap[$flashKey])
        @php($classes = implode(' ', array_filter([
            'flash-alert',
            'flash-alert--'.$cfg['variant'],
            $cfg['variant'] === 'success' ? 'success' : null,
            $isCompact ? 'flash-alert--compact' : null,
            'no-print',
        ])))
        <div
            class="{{ $classes }}"
            role="{{ $cfg['role'] }}"
            data-flash-alert
            @if($cfg['autoclose']) data-flash-autoclose="7500" @endif
        >
            <span class="flash-alert-icon" aria-hidden="true">
                @if($cfg['variant'] === 'success')
                    <svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"></path></svg>
                @elseif($cfg['variant'] === 'error')
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
                @elseif($cfg['variant'] === 'warning')
                    <svg viewBox="0 0 24 24"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                @else
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                @endif
            </span>
            <div class="flash-alert-body">
                <strong class="flash-alert-title">{{ $cfg['title'] }}</strong>
                <p class="flash-alert-message">{{ session($flashKey) }}</p>
            </div>
            <button
                type="button"
                class="flash-alert-close"
                aria-label="Tutup pemberitahuan"
                title="Tutup"
                data-flash-close
            >&times;</button>
            @if($cfg['autoclose'])
                <span class="flash-alert-progress" aria-hidden="true"></span>
            @endif
        </div>
    @endif
@endforeach

