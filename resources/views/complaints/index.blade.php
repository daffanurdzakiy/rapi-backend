@extends('layouts.app')

@section('content')
    <div class="heading">
        <div>
            <h1>Pengaduan Fasilitas</h1>
            <p>PIC hanya melihat dan menangani pengaduan dari ruangan yang menjadi tanggung jawabnya.</p>
        </div>
        @if($canReport)
            <a class="btn" href="{{ route('complaints.create') }}">+ Buat Pengaduan</a>
        @endif
    </div>

    <div class="card">
        @forelse($complaints as $complaint)
            <div class="list complaint-item">
                <div class="heading complaint-heading">
                    <div>
                        <h3>{{ $complaint->room->room_name }}</h3>
                        <p>
                            {{ $complaint->description }}<br>
                            <span class="muted">
                                Pelapor: {{ $complaint->reporter->name }} · PIC: {{ $complaint->room->pic->name }}
                            </span>
                        </p>
                    </div>
                    <div class="complaint-side">
                        <span class="badge {{ $complaint->status_repair === 'Belum Diperbaiki' ? 'bad' : '' }}">
                            {{ $complaint->status_repair }}
                        </span>
                        @if($complaint->photo_path)
                            @php($photoUrl = route('complaints.show-photo', $complaint, false))
                            <a href="{{ $photoUrl }}" target="_blank" rel="noopener" aria-label="Buka foto pengaduan ukuran penuh">
                                <img
                                    src="{{ $photoUrl }}"
                                    alt="Foto pengaduan {{ $complaint->room->room_name }}"
                                    style="display:block;max-width:220px;max-height:180px;object-fit:contain;border-radius:8px"
                                >
                            </a>
                        @else
                            <span class="muted">Foto belum dilampirkan</span>
                        @endif
                    </div>
                </div>

                @if(!$complaint->photo_path && (auth()->user()->role?->role_name === 'Admin' || $complaint->reporter_id === auth()->id()))
                    <form method="post" action="{{ route('complaints.photo', $complaint) }}" enctype="multipart/form-data" style="margin:12px 0">
                        @csrf
                        @method('PATCH')
                        <label for="photo-{{ $complaint->id }}">Tambahkan foto bukti (maksimal 5 MB)</label>
                        <input id="photo-{{ $complaint->id }}" type="file" name="photo" accept="image/jpeg,image/png,image/webp" required>
                        @error('photo')<p class="error">{{ $message }}</p>@enderror
                        <button class="btn" style="margin-top:10px">Simpan Foto</button>
                    </form>
                @endif

                @if(auth()->user()->role?->role_name === 'PIC')
                    <form method="post" action="{{ route('complaints.status', $complaint) }}">
                        @csrf
                        @method('PATCH')
                        <select name="status_repair" style="max-width:260px">
                            @foreach(['Belum Diperbaiki', 'Menunggu Spare Part', 'Sudah Diperbaiki'] as $status)
                                <option @selected($status === $complaint->status_repair)>{{ $status }}</option>
                            @endforeach
                        </select>
                        <button class="btn">Perbarui Status</button>
                    </form>
                @endif

                @if(isset($whatsappWarnings[$complaint->id]))
                    <a
                        class="btn warn"
                        target="_blank"
                        rel="noopener"
                        href="{{ $whatsappWarnings[$complaint->id] }}"
                    >Kirim Peringatan WhatsApp</a>
                @endif
            </div>
        @empty
            <p class="muted">Belum ada pengaduan.</p>
        @endforelse
    </div>
@endsection
