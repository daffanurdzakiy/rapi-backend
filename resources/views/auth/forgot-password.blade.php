@extends('layouts.auth')

@section('content')
    <div class="login-screen reset-screen">
        <section class="reset-card">
            @if($otpEmail)
                <h1>Verifikasi Kode</h1>
                <p class="reset-description">
                    {{ session('status', 'Masukkan kode OTP yang dikirim ke email Anda. Kode berlaku selama 10 menit.') }}
                </p>

                @include('partials.flash-alerts', ['onlyKeys' => ['mail_error'], 'compact' => true])

                <form method="POST" action="{{ route('password.otp') }}">
                    @csrf
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $otpEmail) }}" autocomplete="email" required>
                    @error('email')<p class="error">{{ $message }}</p>@enderror
                    <a class="reset-change-email" href="{{ route('password.change-email') }}">Ganti email</a>

                    <label for="otp">Kode Verifikasi</label>
                    <input id="otp" name="otp" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" value="{{ old('otp') }}" required autofocus>
                    @error('otp')<p class="error">{{ $message }}</p>@enderror

                    <button class="reset-submit" type="submit">Verifikasi Kode</button>
                </form>

                <form method="POST" action="{{ route('password.email') }}">
                    @csrf
                    <input type="hidden" name="email" value="{{ old('email', $otpEmail) }}">
                    <button class="reset-resend" type="submit">Minta kode baru</button>
                </form>
            @else
                <h1>Atur ulang kata sandi</h1>
                <p class="reset-description">Masukan email buat reset password</p>

                @include('partials.flash-alerts', ['onlyKeys' => ['mail_error'], 'compact' => true])

                <form method="POST" action="{{ route('password.email') }}">
                    @csrf
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="daffa@gmail.com" autocomplete="email" required autofocus>
                    @error('email')<p class="error">{{ $message }}</p>@enderror
                    <button class="reset-submit" type="submit">Kirim Kode OTP</button>
                </form>
            @endif

            <a class="reset-back" href="{{ route('login') }}">Kembali ke masuk</a>
        </section>
    </div>
@endsection
