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

<dialog id="confirm-room-dialog" class="confirm-dialog" aria-labelledby="confirm-room-title">
    <div class="confirm-dialog-heading">
        <div>
            <h2 id="confirm-room-title">{{ $room ? 'Konfirmasi Perubahan Ruangan' : 'Konfirmasi Penambahan Ruangan' }}</h2>
            <p>Periksa kembali detail informasi ruangan sebelum disimpan</p>
        </div>
        <button class="confirm-dialog-close" type="button" id="confirm-room-close" aria-label="Tutup dialog">×</button>
    </div>
    <div class="confirm-dialog-body">
        <div class="confirm-dialog-details">
            <div class="confirm-item">
                <span class="confirm-label">Nama ruangan:</span>
                <span class="confirm-value" id="confirm-display-name">-</span>
            </div>
            <div class="confirm-item">
                <span class="confirm-label">Lokasi:</span>
                <span class="confirm-value" id="confirm-display-location">-</span>
            </div>
            <div class="confirm-item">
                <span class="confirm-label">Kapasitas:</span>
                <span class="confirm-value" id="confirm-display-capacity">-</span>
            </div>
            <div class="confirm-item">
                <span class="confirm-label">PIC:</span>
                <span class="confirm-value" id="confirm-display-pic">-</span>
            </div>
            <div class="confirm-item">
                <span class="confirm-label">Deskripsi:</span>
                <span class="confirm-value" id="confirm-display-description">-</span>
            </div>
        </div>
    </div>
    <div class="confirm-dialog-footer">
        <button class="btn ghost" type="button" id="confirm-room-cancel">Batal</button>
        <button class="btn" type="button" id="confirm-room-ok">Ya, Simpan Ruangan</button>
    </div>
</dialog>

<script>
    (function () {
        var form = document.getElementById('room-form');
        var dialog = document.getElementById('confirm-room-dialog');
        var btnCancel = document.getElementById('confirm-room-cancel');
        var btnClose = document.getElementById('confirm-room-close');
        var btnOk = document.getElementById('confirm-room-ok');

        if (btnClose) {
            btnClose.addEventListener('click', function () { dialog.close(); });
        }
        if (btnCancel) {
            btnCancel.addEventListener('click', function () { dialog.close(); });
        }
        if (btnOk) {
            btnOk.addEventListener('click', function () {
                dialog.close();
                form.dataset.confirmed = 'true';
                form.submit();
            });
        }

        form.addEventListener('submit', function (event) {
            if (form.dataset.confirmed === 'true') {
                return;
            }

            var roomName = document.getElementById('room_name').value.trim();
            var location = document.getElementById('location').value.trim() || 'Tidak diisi';
            var capacity = document.getElementById('capacity').value;
            var picSelect = document.getElementById('pic_id');
            var pic = (picSelect.selectedOptions && picSelect.selectedOptions[0]) ? picSelect.selectedOptions[0].text : 'Tidak dipilih';
            var description = document.getElementById('description').value.trim() || 'Tidak diisi';

            var message = 'Konfirmasi penambahan ruangan ini?'
                + '\n\nNama ruangan: ' + roomName
                + '\nLokasi: ' + location
                + '\nKapasitas: ' + capacity
                + '\nPIC: ' + pic
                + '\nDeskripsi: ' + description;

            if (dialog && typeof dialog.showModal === 'function') {
                event.preventDefault();
                document.getElementById('confirm-display-name').textContent = roomName || '-';
                document.getElementById('confirm-display-location').textContent = location;
                document.getElementById('confirm-display-capacity').textContent = (capacity || '0') + ' orang';
                document.getElementById('confirm-display-pic').textContent = pic;
                document.getElementById('confirm-display-description').textContent = description;
                dialog.showModal();
                return;
            }

            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    })();
</script>
@endsection
