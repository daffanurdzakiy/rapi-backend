<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kalender RAPI-Sekolah</title>
    @include('calendar.styles')
</head>
<body class="public-calendar">
    <header class="public-topbar">
        <a class="public-brand" href="{{ route('calendar.public') }}">
            <img class="brand-emblem" src="{{ asset('images/school-emblem.png') }}" alt="Logo SMK Negeri 1 Bantul">
            <span>RAPI</span>
        </a>
        <a class="login-link" href="{{ route('login') }}">Masuk</a>
    </header>
    <main class="public-wrap">
        @include('calendar.month')
    </main>
</body>
</html>
