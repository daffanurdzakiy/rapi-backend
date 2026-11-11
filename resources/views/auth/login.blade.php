@extends('layouts.auth')

@section('content')
    <div class="login-screen">
        <section class="login-card">
            <div class="login-intro">
                <img class="school-emblem" src="{{ asset('images/school-emblem.png') }}" alt="Logo SMK Negeri 1 Bantul">
                <h2>Welcome back</h2>
                <p>Rencana Agenda dan Penjadwalan<br>Terintegrasi</p>
            </div>

            <section class="login-panel">
                <header class="login-heading">
                    <h1>LOGIN</h1>
                    <p>Gunakan akun yang telah dibuat oleh administrator</p>
                </header>

                <form class="login-form" method="POST" action="{{ route('login.store') }}">
                    @csrf
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="daffa@gmail.com" autocomplete="email" required autofocus>

                    <label for="password">Password</label>
                    <div class="password-field">
                        <input id="password" name="password" type="password" autocomplete="current-password" required>
                        <button type="button" class="password-toggle" aria-label="Tampilkan password">◌</button>
                    </div>

                    <div class="login-options">
                        <label class="remember"><input name="remember" type="checkbox" value="1"> Ingat saya</label>
                        <a href="{{ route('password.request') }}">Lupa Password?</a>
                    </div>

                    @include('partials.flash-alerts', ['onlyKeys' => ['status'], 'compact' => true])
                    @error('email')<p class="error">{{ $message }}</p>@enderror
                    @error('password')<p class="error">{{ $message }}</p>@enderror

                    <button class="submit" type="submit">Masuk</button>
                </form>

                <a class="calendar-link" href="{{ route('calendar.public') }}">Kembali Ke Kalender Utama</a>
            </section>
        </section>
    </div>
@endsection
