@extends('layouts.app')

@section('content')
<div class="heading">
    <div>
        <h1>{{ auth()->user()->role?->role_name === 'PIC' ? 'Jadwal Ruangan' : 'Manajemen Agenda' }}</h1>
        <p>Agenda pending dan disetujui diperiksa untuk mencegah jadwal bentrok.</p>
    </div>
    <div style="display:flex;gap:10px">
        @if(in_array(auth()->user()->role?->role_name, ['Admin', 'Guru', 'Organisasi']))
            <a class="btn" href="{{ route('events.create') }}">+ Ajukan Agenda</a>
        @endif
    </div>
</div>

<div class="card">
    @forelse($events as $event)
        <div class="list">
            <div class="heading">
                <div>
                    <h3 style="margin:0">{{ $event->title }}</h3>
                    <p>
                        {{ $event->event_date->format('d M Y') }} · {{ substr($event->start_time, 0, 5) }}–{{ substr($event->end_time, 0, 5) }} ·
                        <b>{{ $event->room->room_name }}</b><br>
                        <span class="muted">Pemohon: {{ $event->user->name }}</span>
                        @if($event->move_reason)
                            <br><span class="muted">Alasan perubahan: {{ $event->move_reason }}</span>
                        @endif
                        @if($event->status === 'Ditolak' && $event->rejection_reason)
                            <br><span class="error"><b>Alasan ditolak:</b> {{ $event->rejection_reason }}</span>
                        @endif
                        @if($event->status === 'Disetujui' && $event->approver)
                            <br><span class="muted">Disetujui oleh: {{ $event->approver->name }} ({{ $event->approver->role?->role_name }})</span>
                        @endif
                    </p>
                </div>
                <span class="badge {{ $event->status === 'Pending' ? 'pending' : '' }}">{{ $event->status }}</span>
            </div>

            @if(in_array(auth()->user()->role?->role_name, ['Admin', 'Guru', 'Organisasi']) && in_array($event->status, ['Pending', 'Disetujui']))
                <a class="btn ghost" href="{{ route('complaints.create', ['room_id' => $event->room_id]) }}">Laporkan Kerusakan Ruangan</a>
            @endif

            @if(in_array(auth()->user()->role?->role_name, ['Admin', 'PIC']) && $event->status === 'Pending')
                <form method="post" action="{{ route('events.decide', $event) }}" style="display:inline">
                    @csrf
                    @method('PATCH')
                    <button class="btn" name="status" value="Disetujui">Setujui</button>
                    <button class="btn danger" type="button" data-rejection-toggle="{{ $event->id }}">Tolak</button>
                    <div id="rejection-panel-{{ $event->id }}" @if(!$errors->has('rejection_reason')) hidden @endif style="margin-top:12px;min-width:280px">
                        <label for="rejection_reason-{{ $event->id }}">Keterangan penolakan</label>
                        <textarea id="rejection_reason-{{ $event->id }}" name="rejection_reason" rows="2" maxlength="1000" @if(!$errors->has('rejection_reason')) disabled @else required @endif>{{ old('rejection_reason') }}</textarea>
                        @error('rejection_reason')<p class="error">{{ $message }}</p>@enderror
                        <input type="hidden" name="status" value="Ditolak" @if(!$errors->has('rejection_reason')) disabled @endif>
                        <button class="btn danger" type="submit" data-rejection-submit="{{ $event->id }}" @if(!$errors->has('rejection_reason')) disabled @endif>Konfirmasi Tolak</button>
                    </div>
                </form>
            @endif
            @if(auth()->user()->role?->role_name === 'Admin')
                @if(in_array($event->status, ['Pending', 'Disetujui']))
                    <a class="btn ghost" href="{{ route('events.edit', $event) }}">Edit</a>
                @endif
                @if($event->status === 'Disetujui')
                    <form method="post" action="{{ route('events.destroy', $event) }}" style="display:inline" onsubmit="return confirm('Hapus agenda yang disetujui ini?')">
                        @csrf
                        @method('DELETE')
                        <button class="btn danger">Hapus Agenda</button>
                    </form>
                @endif
            @endif
        </div>
    @empty
        <p class="muted">Belum ada agenda.</p>
    @endforelse
</div>
<script>
    document.querySelectorAll('[data-rejection-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            var eventId = button.getAttribute('data-rejection-toggle');
            var panel = document.getElementById('rejection-panel-' + eventId);
            var textarea = document.getElementById('rejection_reason-' + eventId);
            var status = panel.querySelector('input[name="status"]');
            var submit = panel.querySelector('[data-rejection-submit]');
            panel.hidden = false;
            textarea.disabled = false;
            status.disabled = false;
            submit.disabled = false;
            textarea.required = true;
            textarea.focus();
        });
    });
</script>
@endsection
