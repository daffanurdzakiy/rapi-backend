<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $title ?? 'RAPI-Sekolah' }}</title>
    <style>
        :root {
            color-scheme: light;
            font-family: Inter, "Segoe UI", Arial, sans-serif;
            color: #26364a;
            background: #f7f4eb;
            font-synthesis: none;
            --page: #f7f4eb;
            --nav: #fffdf8;
            --panel: #fffdf8;
            --panel-raised: #fffdf8;
            --panel-hover: #f1f3f6;
            --border: #e8e1d3;
            --text: #26364a;
            --muted: #6d7788;
            --accent: #35465e;
            --accent-hover: #465a73;
            --teal: #35465e;
            --danger: #35465e;
        }

        * { box-sizing: border-box; }
        body { min-width: 320px; min-height: 100vh; margin: 0; background: var(--page); color: var(--text); }
        a { color: #465a73; }
        a:hover { color: #26364a; }
        button, input, select, textarea { font: inherit; }
        .layout { min-height: 100vh; }
        .top {
            position: sticky;
            top: 0;
            z-index: 20;
            display: flex;
            min-height: 68px;
            align-items: center;
            gap: 20px;
            padding: 0 clamp(12px, 1.7vw, 24px);
            border-top: 4px solid #35465e;
            border-bottom: 1px solid #e8e1d3;
            background: rgba(255, 253, 248, .97);
            box-shadow: 0 8px 24px rgba(38, 54, 74, .08);
            backdrop-filter: blur(14px);
        }
        .brand { display: inline-flex; flex: 0 0 auto; align-items: center; gap: 7px; color: #26364a; font-family: Georgia, serif; font-size: 20px; font-weight: 700; text-decoration: none; }
        .brand:hover { color: #35465e; }
        .brand img { display: block; width: 30px; height: 30px; object-fit: contain; }
        .nav { display: flex; flex: 1 1 auto; min-width: 0; align-items: stretch; justify-content: center; gap: clamp(2px, .35vw, 6px); overflow: visible; }
        .nav a { position: relative; display: inline-flex; flex: 0 0 auto; min-width: 0; min-height: 64px; align-items: center; padding: 0 clamp(6px, .65vw, 10px); border-radius: 7px 7px 0 0; color: #667386; font-size: clamp(10px, .9vw, 12px); white-space: nowrap; text-decoration: none; transition: color .18s, background .18s; }
        .nav a::after { position: absolute; right: 8px; bottom: 0; left: 8px; height: 3px; border-radius: 3px 3px 0 0; background: transparent; content: ""; }
        .nav a:hover { color: #26364a; background: #f1f3f6; }
        .nav a.active { background: #f1f3f6; color: #26364a; font-weight: 700; }
        .nav a.active::after { background: var(--accent); }
        .top-actions { display: flex; flex: 0 0 auto; align-items: center; gap: 10px; }
        .profile-link { display: inline-flex; min-width: 0; align-items: center; gap: 9px; border-radius: 9px; color: #26364a; text-decoration: none; }
        .profile-link:hover { color: #35465e; }
        .profile-avatar { display: grid; flex: 0 0 34px; width: 34px; height: 34px; place-items: center; border: 1px solid #d7deea; border-radius: 50%; background: #f1f3f6; color: #35465e; font-size: 13px; font-weight: 700; transition: background .18s, border-color .18s, transform .18s; }
        .profile-link:hover .profile-avatar { transform: translateY(-1px); border-color: #bdc9da; background: #e8edf4; }
        .profile { max-width: 118px; overflow: hidden; color: #26364a; font-size: 12px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
        .profile small { display: block; margin-top: 3px; color: #6d7788; font-size: 11px; font-weight: 400; }
        .logout { margin: 0 0 0 7px; }
        .logout button { display: grid; width: 34px; height: 34px; place-items: center; padding: 0; border: 0; border-radius: 8px; background: transparent; color: #667386; cursor: pointer; transition: background .18s, color .18s; }
        .logout button svg { width: 17px; height: 17px; fill: none; stroke: currentColor; stroke-linecap: round; stroke-linejoin: round; stroke-width: 1.7; }
        .logout button:hover { background: #e8edf4; color: #26364a; }
        .main { width: 100%; }
        .content { width: min(1500px, 100%); margin: 0 auto; padding: 30px clamp(14px, 2vw, 28px) 56px; }
        .heading { display: flex; align-items: start; justify-content: space-between; gap: 18px; }
        .heading h1 { margin: 0; color: var(--text); font-size: 28px; font-weight: 700; letter-spacing: -.45px; }
        .heading h2, h2 { color: var(--text); }
        .heading h3, h3 { color: var(--text); }
        .heading p { margin: 7px 0 22px; color: var(--muted); font-size: 14px; line-height: 1.55; }
        .card { margin-top: 16px; padding: 23px 24px; border: 1px solid var(--border); border-radius: 15px; background: var(--panel); box-shadow: 0 9px 22px rgba(38, 54, 74, .06); }
        .card h2:first-child, .card h3:first-child { margin-top: 0; }
        .stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 15px; }
        .dashboard-heading { align-items: start; margin-bottom: 8px; }
        .dashboard-heading p { margin-bottom: 20px; }
        .dashboard-date { padding-top: 6px; color: #46566d; font-size: 13px; white-space: nowrap; }
        .stat { position: relative; min-height: 126px; overflow: hidden; }
        .stat::after { position: absolute; top: -35px; right: -22px; width: 110px; height: 110px; border: 1px solid rgba(132, 158, 255, .08); border-radius: 50%; background: rgba(100, 131, 255, .035); content: ""; }
        .stat b { display: block; margin: 12px 0 5px; color: #26364a; font-size: 34px; line-height: 1; }
        .stat .muted:first-child { font-size: 11px; font-weight: 700; letter-spacing: .08em; }
        .stat-alert b { color: var(--danger); }
        .quick-action { min-width: 190px; justify-content: flex-start; }
        .muted { color: var(--muted); }
        .grid2 { display: grid; grid-template-columns: minmax(0, 2fr) minmax(260px, .95fr); align-items: start; gap: 16px; }
        .btn { display: inline-flex; min-height: 40px; align-items: center; justify-content: center; gap: 8px; padding: 10px 15px; border: 1px solid transparent; border-radius: 9px; background: var(--accent); color: #fffdf8; font-size: 13px; font-weight: 700; text-decoration: none; cursor: pointer; transition: transform .16s, background .16s, box-shadow .16s, border-color .16s; }
        .btn:hover { transform: translateY(-1px); background: var(--accent-hover); color: #fffdf8; box-shadow: 0 5px 13px rgba(53, 70, 94, .16); }
        .btn.warn, .btn.danger { background: #465a73; color: #fffdf8; }
        .btn.warn:hover, .btn.danger:hover { background: #26364a; color: #fffdf8; }
        .btn.ghost { border-color: #d7deea; background: #f1f3f6; color: #35465e; }
        .btn.ghost:hover { border-color: #bdc9da; background: #e8edf4; color: #26364a; }
        .room-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
        .room-actions form { margin: 0; }
        .list { padding: 16px 0; border-top: 1px solid #e8e1d3; }
        .list:first-child { border-top: 0; }
        .badge { display: inline-flex; min-height: 25px; align-items: center; justify-content: center; padding: 4px 10px; border: 1px solid #d7deea; border-radius: 99px; background: #f1f3f6; color: #35465e; font-size: 11px; font-weight: 700; white-space: nowrap; }
        .badge.pending, .badge.bad { border-color: #d9d1bf; background: #f7f4eb; color: #35465e; }
        table { width: 100%; border-collapse: collapse; color: var(--text); }
        th, td { padding: 14px 12px; border-bottom: 1px solid #e8e1d3; text-align: left; vertical-align: top; }
        th { color: #667386; font-size: 11px; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; }
        tbody tr:hover { background: #f7f4eb; }
        input, select, textarea { width: 100%; min-height: 42px; padding: 10px 12px; border: 1px solid #d7deea; border-radius: 8px; outline: none; background: #fffdf8; color: var(--text); font-size: 14px; transition: border-color .16s, box-shadow .16s, background .16s; }
        input::placeholder, textarea::placeholder { color: #8993a2; }
        input:focus, select:focus, textarea:focus { border-color: #667b99; background: #fffdf8; box-shadow: 0 0 0 3px rgba(53, 70, 94, .12); }
        input[type="file"] { min-height: auto; padding: 8px; }
        input[type="file"]::file-selector-button { margin-right: 10px; padding: 8px 11px; border: 0; border-radius: 6px; background: #f1f3f6; color: #35465e; cursor: pointer; }
        input[type="checkbox"], input[type="radio"] { width: auto; min-height: auto; accent-color: var(--accent); }
        input[type="date"] { color-scheme: light; }
        select option { background: #fffdf8; color: var(--text); }
        label { display: block; margin: 15px 0 7px; color: #35465e; font-size: 13px; font-weight: 600; }
        textarea { min-height: 90px; resize: vertical; }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 18px; }
        .full { grid-column: 1 / -1; }
        .success { margin: 0 0 16px; padding: 13px 16px; border: 1px solid #d7deea; border-radius: 9px; background: #f1f3f6; color: #35465e; }
        .error { color: #465a73; font-size: 13px; }
        .notice { margin: 12px 0; padding: 13px 15px; border: 1px solid #e8e1d3; border-radius: 9px; background: #f7f4eb; color: #34445d; }
        .notice a { color: #465a73; }
        details > summary { cursor: pointer; }
        .password-field { display: flex; gap: 7px; }
        .password-field input { flex: 1; min-width: 0; }
        .password-toggle { min-width: 42px; border: 1px solid #d7deea; border-radius: 8px; background: #f1f3f6; color: #35465e; cursor: pointer; }
        .calendar { display: grid; grid-template-columns: repeat(7, 1fr); border: 1px solid var(--border); }
        .day { min-height: 115px; padding: 9px; border-top: 1px solid var(--border); border-right: 1px solid var(--border); font-weight: 700; }
        .event { display: block; margin-top: 5px; padding: 5px; border-radius: 5px; background: #f1f3f6; color: #35465e; font-size: 12px; font-weight: 400; text-decoration: none; }
        .calendar-head { display: grid; grid-template-columns: repeat(7, 1fr); padding: 12px 0; color: #667386; font-weight: 700; text-align: center; }
        img { max-width: 100%; }
        [hidden] { display: none !important; }

        @media (max-width: 1050px) {
            .top { gap: 8px; }
            .brand { gap: 5px; font-size: 17px; }
            .brand img { width: 26px; height: 26px; }
            .nav { flex: 1 1 auto; justify-content: center; gap: 1px; }
            .nav a { min-height: 60px; padding-right: 4px; padding-left: 4px; font-size: clamp(9px, .95vw, 10px); }
            .profile { max-width: 86px; font-size: 10px; }
            .profile small { font-size: 9px; }
            .logout { margin-left: 0; }
        }
        @media (max-width: 760px) {
            .top {
                flex-wrap: wrap;
                min-height: auto;
                padding: 10px 14px;
                gap: 8px 12px;
            }
            .brand { font-size: 18px; }
            .brand img { width: 26px; height: 26px; }
            .top-actions { margin-left: auto; gap: 8px; }
            .profile-avatar { flex-basis: 30px; width: 30px; height: 30px; font-size: 12px; }
            .profile { max-width: 100px; font-size: 11px; }
            .profile small { display: block; font-size: 9px; }
            .logout { margin-left: 2px; }
            .nav {
                order: 3;
                width: 100%;
                flex: 0 0 100%;
                justify-content: flex-start;
                overflow-x: auto;
                overflow-y: hidden;
                -webkit-overflow-scrolling: touch;
                padding: 4px 0 2px;
                gap: 6px;
            }
            .nav::-webkit-scrollbar { display: none; }
            .nav a {
                min-height: 36px;
                padding: 6px 12px;
                border-radius: 6px;
                font-size: 12px;
                font-weight: 600;
                background: #f1f3f6;
                color: #667386;
                white-space: nowrap;
            }
            .nav a.active {
                background: #35465e;
                color: #fffdf8;
            }
            .nav a::after { display: none; }
            .content { padding: 20px 12px 40px; }
            .stats, .grid2 { grid-template-columns: 1fr; }
            .stats { gap: 8px; }
            .stat { min-height: 112px; }
            .form-grid { grid-template-columns: 1fr; }
            .full { grid-column: auto; }
            .heading { flex-wrap: wrap; }
            .heading h1 { font-size: 24px; }
            .heading p { margin-bottom: 16px; }
            .card { padding: 18px; }
            .report-table-wrap, .card:has(> table) { overflow-x: auto; }
            table { min-width: 620px; font-size: 13px; }
            .dashboard-date { padding: 0 0 12px; }
            .quick-action { width: 100%; }
        }
    </style>
</head>
<body>
    @php($currentUser = auth()->user())
    @php($currentRole = $currentUser->role?->role_name)
    <div class="layout {{ request()->routeIs('dashboard') ? 'dashboard-layout' : '' }}">
        <header class="top">
            <a class="brand" href="{{ route('dashboard') }}">
                <img src="{{ asset('images/school-emblem.png') }}" alt="">
                <span>RAPI</span>
            </a>
            <nav class="nav" aria-label="Navigasi utama">
                <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">Dashboard</a>
                <a class="{{ request()->routeIs('calendar') ? 'active' : '' }}" href="{{ route('calendar') }}">Kalender</a>
                <a class="{{ request()->routeIs('events.*') ? 'active' : '' }}" href="{{ route('events.index') }}">Jadwal Ruangan</a>
                <a class="{{ request()->routeIs('complaints.*') ? 'active' : '' }}" href="{{ route('complaints.index') }}">Pengaduan Fasilitas</a>
                @if($currentRole === 'PIC')
                    <a class="{{ request()->routeIs('pic.complaint-reports.*') ? 'active' : '' }}" href="{{ route('pic.complaint-reports.index') }}">Laporan</a>
                @endif
                @if($currentRole === 'Admin')
                    <a class="{{ request()->routeIs('admin.reports.*') ? 'active' : '' }}" href="{{ route('admin.reports.index') }}">Laporan</a>
                    <a class="{{ request()->routeIs('rooms.*') ? 'active' : '' }}" href="{{ route('rooms.index') }}">Kelola Ruangan</a>
                    <a class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">Pengguna & Peran</a>
                @endif
            </nav>
            <div class="top-actions">
                <a class="profile-link" href="{{ route('profile') }}" aria-label="Buka profil {{ $currentUser->name }}">
                    <span class="profile-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($currentUser->name, 0, 1)) }}</span>
                    <span class="profile">{{ $currentUser->name }}<small>{{ $currentRole }}</small></span>
                </a>
                <form class="logout" method="post" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" aria-label="Keluar" title="Keluar">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M11 4H5v16h6"></path>
                            <path d="M8 12h12"></path>
                            <path d="m16 8 4 4-4 4"></path>
                        </svg>
                    </button>
                </form>
            </div>
        </header>
        <main class="main">
            <section class="content">
                @if(session('success'))<p class="success">{{ session('success') }}</p>@endif
                @yield('content')
            </section>
        </main>
    </div>
    <script>
        document.querySelectorAll('.password-toggle').forEach(function (button) {
            button.addEventListener('click', function () {
                var input = button.parentElement.querySelector('input');
                var visible = input.type === 'password';
                input.type = visible ? 'text' : 'password';
                button.setAttribute('aria-label', visible ? 'Sembunyikan password' : 'Tampilkan password');
                button.textContent = visible ? '◉' : '◌';
            });
        });
    </script>
</body>
</html>
