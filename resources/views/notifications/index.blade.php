@extends('layouts.app')

@section('content')
<style>
    .notif-filter-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        margin: 18px 0 16px;
    }
    .notif-filter-tab {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 7px 14px;
        border-radius: 99px;
        border: 1px solid #d7deea;
        background: #fffdf8;
        color: #465a73;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        transition: all .18s ease;
    }
    .notif-filter-tab:hover {
        background: #f1f3f6;
        color: #26364a;
        border-color: #bdc9da;
    }
    .notif-filter-tab.active {
        background: #35465e;
        color: #fffdf8;
        border-color: #35465e;
        box-shadow: 0 3px 10px rgba(53, 70, 94, .18);
    }
    .notif-filter-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 20px;
        height: 20px;
        padding: 0 6px;
        border-radius: 99px;
        font-size: 11px;
        font-weight: 700;
        background: #e8edf4;
        color: #35465e;
    }
    .notif-filter-tab.active .notif-filter-count {
        background: rgba(255, 255, 255, .25);
        color: #fff;
    }

    .notif-list-card {
        padding: 0;
        overflow: hidden;
    }
    .notif-item {
        display: flex;
        align-items: flex-start;
        gap: 16px;
        padding: 20px 24px;
        border-top: 1px solid #e8e1d3;
        background: #fffdf8;
        transition: background .18s ease;
    }
    .notif-item:first-child {
        border-top: 0;
    }
    .notif-item.is-unread {
        background: #f4f7fb;
        box-shadow: inset 4px 0 0 #35465e;
    }
    .notif-item:hover {
        background: #f9f6ee;
    }
    .notif-item.is-unread:hover {
        background: #edf2f8;
    }

    .notif-icon {
        display: grid;
        place-items: center;
        flex: 0 0 42px;
        width: 42px;
        height: 42px;
        border-radius: 12px;
        border: 1px solid #d7deea;
        background: #f1f5f9;
        color: #35465e;
    }
    .notif-icon.tone-success {
        background: #ecfdf5;
        border-color: #a7f3d0;
        color: #15803d;
    }
    .notif-icon.tone-warning {
        background: #fffbeb;
        border-color: #fde68a;
        color: #b45309;
    }
    .notif-icon.tone-danger {
        background: #fef2f2;
        border-color: #fecaca;
        color: #b91c1c;
    }
    .notif-icon.tone-info {
        background: #eff6ff;
        border-color: #bfdbfe;
        color: #1d4ed8;
    }
    .notif-icon svg {
        width: 20px;
        height: 20px;
        fill: none;
        stroke: currentColor;
        stroke-width: 2;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    .notif-body {
        flex: 1 1 auto;
        min-width: 0;
    }
    .notif-top-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 8px 14px;
        margin-bottom: 6px;
    }
    .notif-badges {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px;
    }
    .notif-cat-tag {
        display: inline-flex;
        align-items: center;
        padding: 3px 9px;
        border-radius: 6px;
        background: #eef2f7;
        color: #35465e;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .02em;
    }
    .notif-tone-badge {
        display: inline-flex;
        align-items: center;
        padding: 3px 10px;
        border-radius: 99px;
        font-size: 11px;
        font-weight: 700;
        border: 1px solid transparent;
    }
    .notif-tone-badge.tone-success {
        background: #dcfce7;
        border-color: #86efac;
        color: #166534;
    }
    .notif-tone-badge.tone-warning {
        background: #fef3c7;
        border-color: #fde68a;
        color: #92400e;
    }
    .notif-tone-badge.tone-danger {
        background: #fee2e2;
        border-color: #fca5a5;
        color: #991b1b;
    }
    .notif-tone-badge.tone-info {
        background: #e0f2fe;
        border-color: #bae6fd;
        color: #0369a1;
    }
    .notif-unread-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 2px 8px;
        border-radius: 99px;
        background: #35465e;
        color: #fffdf8;
        font-size: 10.5px;
        font-weight: 700;
    }
    .notif-unread-pill::before {
        content: "";
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #fbbf24;
    }

    .notif-time {
        color: #6d7788;
        font-size: 12px;
        font-weight: 500;
        white-space: nowrap;
    }
    .notif-title {
        margin: 0 0 4px;
        color: #26364a;
        font-size: 15.5px;
        font-weight: 700;
        line-height: 1.35;
    }
    .notif-summary {
        margin: 0;
        color: #34445d;
        font-size: 13.5px;
        line-height: 1.55;
    }

    .notif-meta-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 6px 14px;
        margin-top: 10px;
        padding: 9px 12px;
        border-radius: 9px;
        border: 1px solid #e8e1d3;
        background: rgba(255, 253, 248, .85);
        font-size: 12.5px;
        color: #465a73;
    }
    .notif-meta-chip strong {
        color: #26364a;
        font-weight: 600;
    }

    .notif-note-box {
        margin-top: 10px;
        padding: 10px 13px;
        border-radius: 8px;
        font-size: 13px;
        line-height: 1.5;
    }
    .notif-note-box.tone-danger {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #991b1b;
    }
    .notif-note-box.tone-info {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        color: #1e40af;
    }
    .notif-note-box.tone-success {
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        color: #166534;
    }
    .notif-note-box.tone-warning {
        background: #fffbeb;
        border: 1px solid #fde68a;
        color: #92400e;
    }
    .notif-note-label {
        display: block;
        font-size: 11.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        margin-bottom: 2px;
    }

    .notif-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        margin-top: 12px;
    }
    .notif-actions form {
        margin: 0;
    }
    .notif-btn-sm {
        min-height: 32px;
        padding: 0 12px;
        font-size: 12.5px;
    }

    .notif-pagination {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-top: 16px;
        padding: 14px 20px;
        border: 1px solid #e8e1d3;
        border-radius: 12px;
        background: #fffdf8;
        font-size: 13px;
        color: #6d7788;
    }
    .notif-pagination-links {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    @media (max-width: 760px) {
        .notif-item {
            padding: 16px;
            gap: 12px;
        }
        .notif-icon {
            width: 36px;
            height: 36px;
            flex-basis: 36px;
        }
        .notif-pagination {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>

<div class="heading">
    <div>
        <h1>Pusat Notifikasi</h1>
        <p>Pantau seluruh pemberitahuan peminjaman ruang dan pengaduan fasilitas secara terstruktur.</p>
    </div>
    @if(($counts['unread'] ?? 0) > 0)
        <form method="post" action="{{ route('notifications.read-all') }}" style="margin:0;">
            @csrf
            <button type="submit" class="btn ghost">Tandai Semua Dibaca ({{ $counts['unread'] }})</button>
        </form>
    @endif
</div>

<div class="notif-filter-bar" aria-label="Filter notifikasi">
    <a href="{{ route('notifications.index', ['filter' => 'semua']) }}"
       class="notif-filter-tab {{ $currentFilter === 'semua' ? 'active' : '' }}">
        Semua <span class="notif-filter-count">{{ $counts['semua'] ?? 0 }}</span>
    </a>
    <a href="{{ route('notifications.index', ['filter' => 'unread']) }}"
       class="notif-filter-tab {{ $currentFilter === 'unread' ? 'active' : '' }}">
        Belum Dibaca <span class="notif-filter-count">{{ $counts['unread'] ?? 0 }}</span>
    </a>
    <a href="{{ route('notifications.index', ['filter' => 'booking']) }}"
       class="notif-filter-tab {{ $currentFilter === 'booking' ? 'active' : '' }}">
        Peminjaman Ruang <span class="notif-filter-count">{{ $counts['booking'] ?? 0 }}</span>
    </a>
    <a href="{{ route('notifications.index', ['filter' => 'complaint']) }}"
       class="notif-filter-tab {{ $currentFilter === 'complaint' ? 'active' : '' }}">
        Pengaduan Ruang <span class="notif-filter-count">{{ $counts['complaint'] ?? 0 }}</span>
    </a>
</div>

<div class="card notif-list-card">
    @forelse($items as $item)
        <article class="notif-item {{ $item['is_read'] ? '' : 'is-unread' }}">
            <div class="notif-icon tone-{{ $item['tone'] }}" aria-hidden="true">
                @if($item['category'] === 'booking')
                    <svg viewBox="0 0 24 24">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                @elseif($item['category'] === 'complaint')
                    <svg viewBox="0 0 24 24">
                        <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
                    </svg>
                @else
                    <svg viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="16" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                @endif
            </div>

            <div class="notif-body">
                <div class="notif-top-row">
                    <div class="notif-badges">
                        <span class="notif-cat-tag">{{ $item['category_label'] }}</span>
                        <span class="notif-tone-badge tone-{{ $item['tone'] }}">{{ $item['badge_label'] }}</span>
                        @if(! $item['is_read'])
                            <span class="notif-unread-pill">Baru</span>
                        @endif
                    </div>
                    <span class="notif-time" title="{{ $item['timestamp'] }}">
                        {{ $item['time_ago'] }} · {{ $item['timestamp'] }}
                    </span>
                </div>

                <h2 class="notif-title">{{ $item['title'] }}</h2>
                <p class="notif-summary">{{ $item['summary'] }}</p>

                @if(! empty($item['meta']))
                    <div class="notif-meta-grid">
                        @foreach($item['meta'] as $meta)
                            <span class="notif-meta-chip">
                                {{ $meta['label'] }}: <strong>{{ $meta['value'] }}</strong>
                            </span>
                        @endforeach
                    </div>
                @endif

                @if(! empty($item['note_text']))
                    <div class="notif-note-box tone-{{ $item['tone'] }}">
                        <span class="notif-note-label">{{ $item['note_label'] }}</span>
                        {{ $item['note_text'] }}
                    </div>
                @endif

                <div class="notif-actions">
                    <a href="{{ $item['open_url'] }}" class="btn notif-btn-sm">
                        {{ $item['action_label'] }} →
                    </a>
                    @if(! $item['is_read'])
                        <form method="post" action="{{ $item['read_url'] }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn ghost notif-btn-sm">Tandai Dibaca</button>
                        </form>
                    @endif
                </div>
            </div>
        </article>
    @empty
        <div style="text-align:center; padding:42px 20px;">
            <p class="muted" style="margin:0; font-size:14px;">
                @if($currentFilter === 'unread')
                    Semua notifikasi sudah dibaca. Tidak ada pemberitahuan baru saat ini.
                @elseif($currentFilter === 'booking')
                    Belum ada notifikasi terkait peminjaman ruang.
                @elseif($currentFilter === 'complaint')
                    Belum ada notifikasi terkait pengaduan fasilitas ruang.
                @else
                    Belum ada notifikasi aktivitas untuk akun Anda.
                @endif
            </p>
            @if($currentFilter !== 'semua')
                <div style="margin-top:12px;">
                    <a href="{{ route('notifications.index') }}" class="btn ghost notif-btn-sm">Tampilkan Semua Notifikasi</a>
                </div>
            @endif
        </div>
    @endforelse
</div>

@if($notifications->hasPages())
    <div class="notif-pagination">
        <span>
            Menampilkan {{ $notifications->firstItem() }}–{{ $notifications->lastItem() }} dari {{ $notifications->total() }} notifikasi
        </span>
        <div class="notif-pagination-links">
            @if($notifications->onFirstPage())
                <span class="btn ghost notif-btn-sm" style="opacity:.45; pointer-events:none;">← Sebelumnya</span>
            @else
                <a class="btn ghost notif-btn-sm" href="{{ $notifications->previousPageUrl() }}">← Sebelumnya</a>
            @endif
            <span style="font-weight:600; color:#26364a;">Hal. {{ $notifications->currentPage() }} / {{ $notifications->lastPage() }}</span>
            @if($notifications->hasMorePages())
                <a class="btn ghost notif-btn-sm" href="{{ $notifications->nextPageUrl() }}">Berikutnya →</a>
            @else
                <span class="btn ghost notif-btn-sm" style="opacity:.45; pointer-events:none;">Berikutnya →</span>
            @endif
        </div>
    </div>
@endif
@endsection

