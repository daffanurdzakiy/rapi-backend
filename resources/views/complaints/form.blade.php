@extends('layouts.app')

@section('content')
<h1>Buat Pengaduan Fasilitas</h1>
<p class="muted">Lampirkan foto bukti (JPG, PNG, atau WebP). Foto kamera akan otomatis dikompres agar hemat penyimpanan.</p>

<form id="complaint-form" class="card" method="post" action="{{ route('complaints.store') }}" enctype="multipart/form-data">
    @csrf
    <label>Nama Pengadu</label>
    <p><b>{{ $reporterName }}</b></p>

    <label>Ruangan</label>
    @if($selectedRoom)
        <p><b>{{ $selectedRoom->room_name }}</b></p>
        <input type="hidden" name="room_id" value="{{ $selectedRoom->id }}">
    @else
        <select name="room_id" required>
            @foreach($rooms as $room)
                <option value="{{ $room->id }}" @selected((string) old('room_id') === (string) $room->id)>{{ $room->room_name }}</option>
            @endforeach
        </select>
    @endif

    <label for="description">Deskripsi kerusakan</label>
    <textarea id="description" name="description" rows="5" required>{{ old('description') }}</textarea>

    <label>Foto bukti</label>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px">
        <button id="choose-file" class="btn ghost" type="button">Pilih dari file</button>
        <button id="open-camera" class="btn" type="button">Ambil dari kamera</button>
    </div>
    <input id="photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp" hidden>
    <p id="photo-status" class="muted" aria-live="polite">Foto wajib dilampirkan.</p>
    @error('photo')<p class="error">{{ $message }}</p>@enderror

    <div id="camera-panel" hidden>
        <video id="camera-preview" autoplay playsinline style="display:block;width:100%;max-width:640px;max-height:480px;background:#f1f3f6;border-radius:8px;object-fit:contain"></video>
        <button id="take-photo" class="btn" type="button" style="margin-top:10px">Ambil Foto</button>
        <button id="close-camera" class="btn ghost" type="button" style="margin-top:10px">Tutup Kamera</button>
    </div>
    <img id="photo-preview" alt="Pratinjau foto bukti" hidden style="display:block;max-width:320px;max-height:240px;object-fit:contain;border-radius:8px;margin-top:12px">
    <p id="camera-error" class="error" role="alert" hidden></p>

    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:20px">
        <button class="btn" type="submit">Kirim Pengaduan</button>
        <a class="btn ghost" href="{{ route('complaints.index') }}">Batal</a>
    </div>
</form>

<script>
    (function () {
        var form = document.getElementById('complaint-form');
        var photoInput = document.getElementById('photo');
        var photoStatus = document.getElementById('photo-status');
        var photoPreview = document.getElementById('photo-preview');
        var cameraPanel = document.getElementById('camera-panel');
        var cameraPreview = document.getElementById('camera-preview');
        var cameraError = document.getElementById('camera-error');
        var cameraStream = null;
        var previewUrl = null;

        function stopCamera() {
            if (cameraStream) {
                cameraStream.getTracks().forEach(function (track) {
                    track.stop();
                });
                cameraStream = null;
            }
            cameraPreview.srcObject = null;
            cameraPanel.hidden = true;
        }

        function formatBytes(bytes) {
            if (!bytes || bytes <= 0) return '0 B';
            var k = 1024;
            var sizes = ['B', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        }

        function compressImageFile(file, onComplete) {
            if (!window.FileReader || !window.HTMLCanvasElement || !file.type.startsWith('image/')) {
                onComplete(file, file.size, file.size);
                return;
            }

            var reader = new FileReader();
            reader.onerror = function () {
                onComplete(file, file.size, file.size);
            };
            reader.onload = function (e) {
                var img = new Image();
                img.onerror = function () {
                    onComplete(file, file.size, file.size);
                };
                img.onload = function () {
                    try {
                        var maxDim = 1200;
                        var width = img.naturalWidth || img.width;
                        var height = img.naturalHeight || img.height;
                        var scale = Math.min(1, maxDim / width, maxDim / height);
                        var targetWidth = Math.max(1, Math.round(width * scale));
                        var targetHeight = Math.max(1, Math.round(height * scale));

                        var canvas = document.createElement('canvas');
                        canvas.width = targetWidth;
                        canvas.height = targetHeight;
                        var ctx = canvas.getContext('2d');

                        // Latar putih untuk format PNG/WebP dengan transparansi
                        ctx.fillStyle = '#ffffff';
                        ctx.fillRect(0, 0, targetWidth, targetHeight);
                        ctx.drawImage(img, 0, 0, targetWidth, targetHeight);

                        canvas.toBlob(function (blob) {
                            if (!blob) {
                                onComplete(file, file.size, file.size);
                                return;
                            }
                            var baseName = file.name.replace(/\.[^/.]+$/, '');
                            var compressedFile = new File([blob], baseName + '.jpg', { type: 'image/jpeg' });
                            onComplete(compressedFile, file.size, blob.size);
                        }, 'image/jpeg', 0.75);
                    } catch (err) {
                        onComplete(file, file.size, file.size);
                    }
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }

        function setPhoto(file, originalSize, compressedSize) {
            var transfer = new DataTransfer();
            transfer.items.add(file);
            photoInput.files = transfer.files;

            if (originalSize && compressedSize && originalSize > compressedSize) {
                var pct = Math.round(((originalSize - compressedSize) / originalSize) * 100);
                photoStatus.innerHTML = 'Foto terpilih: <b>' + file.name + '</b> ' +
                    '<span style="display:inline-block; margin-left:6px; padding:2px 8px; border-radius:6px; background:#ecfdf5; color:#065f46; font-size:12px; font-weight:600;">' +
                    '⚡ Otomatis dikompres: ' + formatBytes(originalSize) + ' → ' + formatBytes(compressedSize) + ' (hemat ' + pct + '%)' +
                    '</span>';
            } else {
                photoStatus.textContent = 'Foto terpilih: ' + file.name + ' (' + formatBytes(file.size) + ')';
            }

            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
            }
            previewUrl = URL.createObjectURL(file);
            photoPreview.src = previewUrl;
            photoPreview.hidden = false;
        }

        document.getElementById('choose-file').addEventListener('click', function () {
            photoInput.click();
        });

        photoInput.addEventListener('change', function () {
            if (photoInput.files.length) {
                stopCamera();
                var originalFile = photoInput.files[0];
                photoStatus.innerHTML = '<span class="muted">Mengompres foto otomatis...</span>';
                compressImageFile(originalFile, function (compressedFile, origSize, compSize) {
                    setPhoto(compressedFile, origSize, compSize);
                });
            }
        });

        document.getElementById('open-camera').addEventListener('click', async function () {
            cameraError.hidden = true;
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                cameraError.textContent = 'Kamera tidak tersedia. Buka aplikasi melalui localhost atau HTTPS, lalu gunakan browser yang mendukung akses kamera.';
                cameraError.hidden = false;
                return;
            }

            stopCamera();
            try {
                cameraStream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: { ideal: 'environment' } },
                    audio: false
                });
                cameraPreview.srcObject = cameraStream;
                cameraPanel.hidden = false;
            } catch (error) {
                cameraError.textContent = error.name === 'NotAllowedError'
                    ? 'Izin kamera ditolak. Izinkan akses kamera di browser, lalu coba lagi.'
                    : 'Kamera tidak dapat dibuka. Pastikan kamera tersambung dan tidak sedang digunakan aplikasi lain.';
                cameraError.hidden = false;
            }
        });

        document.getElementById('take-photo').addEventListener('click', function () {
            if (!cameraStream || !cameraPreview.videoWidth) {
                cameraError.textContent = 'Kamera belum siap. Tunggu sebentar lalu coba lagi.';
                cameraError.hidden = false;
                return;
            }

            var canvas = document.createElement('canvas');
            var maxDim = 1200;
            var scale = Math.min(1, maxDim / cameraPreview.videoWidth, maxDim / cameraPreview.videoHeight);
            canvas.width = Math.round(cameraPreview.videoWidth * scale);
            canvas.height = Math.round(cameraPreview.videoHeight * scale);
            canvas.getContext('2d').drawImage(cameraPreview, 0, 0, canvas.width, canvas.height);
            canvas.toBlob(function (blob) {
                if (!blob) {
                    cameraError.textContent = 'Foto gagal diambil. Silakan coba lagi.';
                    cameraError.hidden = false;
                    return;
                }
                if (blob.size > 5 * 1024 * 1024) {
                    cameraError.textContent = 'Ukuran foto lebih dari 5 MB. Silakan ambil foto lagi dengan resolusi lebih rendah.';
                    cameraError.hidden = false;
                    return;
                }

                var file = new File([blob], 'foto-kamera-' + Date.now() + '.jpg', { type: 'image/jpeg' });
                setPhoto(file, blob.size, blob.size);
                stopCamera();
                cameraError.hidden = true;
            }, 'image/jpeg', 0.75);
        });

        document.getElementById('close-camera').addEventListener('click', stopCamera);
        form.addEventListener('submit', function (event) {
            if (!photoInput.files.length) {
                event.preventDefault();
                cameraError.textContent = 'Pilih foto atau ambil foto dari kamera sebelum mengirim pengaduan.';
                cameraError.hidden = false;
                photoInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
        window.addEventListener('beforeunload', function () {
            stopCamera();
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
            }
        });
    })();
</script>
@endsection
