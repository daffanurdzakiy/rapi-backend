@extends('layouts.app')
@section('content')
<h1>{{ $room ? 'Edit Ruangan' : 'Tambah Ruangan' }}</h1>
<form id="room-form" class="card" method="post" action="{{ $room ? route('rooms.update',$room) : route('rooms.store') }}">
    @csrf
    @if($room)@method('PUT')@endif
    <div class="form-grid">
        <div>
            <label for="room_name">Nama ruangan</label>
            <input id="room_name" name="room_name" value="{{ old('room_name',$room?->room_name) }}" required>
        </div>
        <div>
            <label for="location">Lokasi</label>
            <input id="location" name="location" value="{{ old('location',$room?->location) }}">
        </div>
        <div>
            <label for="capacity">Kapasitas</label>
            <input id="capacity" type="number" min="0" name="capacity" value="{{ old('capacity',$room?->capacity ?? 0) }}" required>
        </div>
        <div>
            <label for="pic_id">PIC</label>
            <select id="pic_id" name="pic_id" required>
                <option value="">Pilih PIC</option>
                @foreach($pics as $pic)
                    <option value="{{ $pic->id }}" @selected(old('pic_id',$room?->pic_id)==$pic->id)>{{ $pic->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="full">
            <label for="description">Deskripsi</label>
            <textarea id="description" name="description">{{ old('description',$room?->description) }}</textarea>
        </div>
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:20px">
        <button class="btn" type="submit">Simpan Ruangan</button>
        <a class="btn ghost" href="{{ route('rooms.index') }}">Batal</a>
    </div>
</form>
@if(! $room)
<script>
    document.getElementById('room-form').addEventListener('submit', function (event) {
        var roomName = document.getElementById('room_name').value.trim();
        var location = document.getElementById('location').value.trim() || 'Tidak diisi';
        var capacity = document.getElementById('capacity').value;
        var pic = document.getElementById('pic_id').selectedOptions[0].text;
        var description = document.getElementById('description').value.trim() || 'Tidak diisi';
        var message = 'Konfirmasi penambahan ruangan ini?'
            + '\n\nNama ruangan: ' + roomName
            + '\nLokasi: ' + location
            + '\nKapasitas: ' + capacity
            + '\nPIC: ' + pic
            + '\nDeskripsi: ' + description;
        if (!window.confirm(message)) event.preventDefault();
    });
</script>
@endif
@endsection
