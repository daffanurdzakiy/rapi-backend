@extends('layouts.auth')

@section('content')
    <div class="login-screen reset-screen">
        <section class="reset-card">
            <h1>Buat Password Baru</h1>
            <p class="reset-description">{{ session('status', 'Masukkan password baru untuk akun Anda.') }}</p>

            <form method="POST" action="{{ route('password.update') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <input type="hidden" name="email" value="{{ old('email', $email) }}">

                @error('email')<p class="error" role="alert">{{ $message }}</p>@enderror

                <label for="password">Password Baru</label>
                <div class="reset-password-field">
                    <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required autofocus>
                    <button type="button" class="password-toggle reset-password-toggle" aria-label="Tampilkan password">◉</button>
                </div>
                @error('password')<p class="error">{{ $message }}</p>@enderror

                <label for="password_confirmation">Konfirmasi Password</label>
                <div class="reset-password-field">
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required>
                    <button type="button" class="password-toggle reset-password-toggle" aria-label="Tampilkan password">◉</button>
                </div>

                <button class="reset-submit" type="submit">Simpan</button>
            </form>

            <a class="reset-back" href="{{ route('login') }}">Kembali ke masuk</a>
        </section>
    </div>
@endsection
