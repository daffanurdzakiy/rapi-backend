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
            min-height: 76px;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            padding: 0 clamp(16px, 2.4vw, 36px);
            border-top: 4px solid #35465e;
            border-bottom: 1px solid #e2dbcb;
            background: rgba(255, 253, 248, .98);
            box-shadow: 0 4px 20px rgba(38, 54, 74, .06);
            backdrop-filter: blur(16px);
        }
        .brand {
            display: inline-flex;
            flex: 0 0 auto;
            align-items: center;
            gap: 12px;
            color: #26364a;
            font-family: Inter, system-ui, -apple-system, sans-serif;
            font-size: 22px;
            font-weight: 800;
            letter-spacing: .02em;
            text-decoration: none;
            transition: transform .18s ease, color .18s ease;
        }
        .brand:hover {
            color: #35465e;
            transform: translateY(-1px);
        }
        .brand img {
            display: block;
            width: 38px;
            height: 38px;
            object-fit: contain;
            filter: drop-shadow(0 2px 5px rgba(38, 54, 74, .12));
        }
        .nav {
            display: flex;
            flex: 1 1 auto;
            min-width: 0;
            align-items: stretch;
            justify-content: center;
            gap: clamp(4px, .6vw, 10px);
            overflow: visible;
        }
        .nav a {
            position: relative;
            display: inline-flex;
            flex: 0 0 auto;
            min-width: 0;
            min-height: 72px;
            align-items: center;
            padding: 0 clamp(10px, 1vw, 16px);
            border-radius: 8px 8px 0 0;
            color: #55657b;
            font-size: clamp(13px, .92vw, 14.5px);
            font-weight: 600;
            white-space: nowrap;
            text-decoration: none;
            transition: color .18s, background .18s;
        }
        .nav a::after {
            position: absolute;
            right: 10px;
            bottom: 0;
            left: 10px;
            height: 3.5px;
            border-radius: 3px 3px 0 0;
            background: transparent;
            content: "";
            transition: background .18s ease;
        }
        .nav a:hover {
            color: #1e293b;
            background: #f1f3f6;
        }
        .nav a.active {
            background: #f1f3f6;
            color: #1e293b;
            font-weight: 700;
        }
        .nav a.active::after {
            background: var(--accent);
        }
        .top-actions {
            display: flex;
            flex: 0 0 auto;
            align-items: center;
            gap: 12px;
        }
        .profile-link {
            display: inline-flex;
            min-width: 0;
            align-items: center;
            gap: 11px;
            padding: 6px 10px;
            border-radius: 12px;
            color: #26364a;
            text-decoration: none;
            transition: background .18s, transform .18s;
        }
        .profile-link:hover {
            color: #35465e;
            background: rgba(53, 70, 94, .06);
            transform: translateY(-1px);
        }
        .profile-avatar {
            display: grid;
            flex: 0 0 40px;
            width: 40px;
            height: 40px;
            place-items: center;
            border: 1.5px solid #d7deea;
            border-radius: 50%;
            background: #eef2f7;
            color: #35465e;
            font-size: 15px;
            font-weight: 700;
            box-shadow: 0 2px 6px rgba(38, 54, 74, .08);
            transition: background .18s, border-color .18s, transform .18s;
        }
        .profile-link:hover .profile-avatar {
            border-color: #bdc9da;
            background: #e2e8f0;
        }
        .profile {
            max-width: 150px;
            overflow: hidden;
            color: #1e293b;
            font-size: 13.5px;
            font-weight: 700;
            line-height: 1.35;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .profile small {
            display: block;
            margin-top: 2px;
            color: #64748b;
            font-size: 11.5px;
            font-weight: 500;
        }
        .logout {
            margin: 0;
        }
        .logout button {
            display: grid;
            width: 40px;
            height: 40px;
            place-items: center;
            padding: 0;
            border: 1px solid #d7deea;
            border-radius: 10px;
            background: #f8fafc;
            color: #64748b;
            cursor: pointer;
            transition: background .18s, color .18s, border-color .18s, transform .18s;
        }
        .logout button svg {
            width: 19px;
            height: 19px;
            fill: none;
            stroke: currentColor;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-width: 1.8;
        }
        .logout button:hover {
            background: #fee2e2;
            border-color: #fca5a5;
            color: #dc2626;
            transform: translateY(-1px);
        }
        .main { width: 100%; }
        .content { width: min(1500px, 100%); margin: 0 auto; padding: 30px clamp(14px, 2vw, 28px) 56px; }
        .heading { display: flex; align-items: start; justify-content: space-between; gap: 18px; }
        .heading h1 { margin: 0; color: var(--text); font-size: 28px; font-weight: 700; letter-spacing: -.45px; }
        .heading h2, h2 { color: var(--text); }
        .heading h3, h3 { color: var(--text); }
        .heading p { margin: 7px 0 22px; color: var(--muted); font-size: 14px; line-height: 1.55; }
        .card { margin-top: 16px; padding: 24px; border: 1px solid var(--border); border-radius: 14px; background: var(--panel); box-shadow: 0 6px 18px rgba(38, 54, 74, .05); }
        .grid2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
        .stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; margin-top: 20px; }
        .stat { display: flex; min-height: 128px; flex-direction: column; justify-content: space-between; padding: 18px 20px; border: 1px solid #e8e1d3; border-radius: 14px; background: #fffdf8; box-shadow: 0 4px 12px rgba(38, 54, 74, .03); }
        .stat .k { color: #6d7788; font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        .stat .v { color: #26364a; font-size: 32px; font-weight: 800; line-height: 1; }
        .stat .hint { color: #6d7788; font-size: 13px; }
        .btn { display: inline-flex; min-height: 38px; align-items: center; justify-content: center; padding: 0 16px; border: 1px solid transparent; border-radius: 8px; background: var(--accent); color: #fffdf8; font-size: 13px; font-weight: 600; text-decoration: none; cursor: pointer; transition: transform .18s, background .18s, box-shadow .18s; }
        .btn:hover { transform: translateY(-1px); background: var(--accent-hover); color: #fffdf8; box-shadow: 0 5px 13px rgba(53, 70, 94, .16); }
        .btn.warn, .btn.danger { background: #465a73; color: #fffdf8; }
        .btn.warn:hover, .btn.danger:hover { background: #26364a; color: #fffdf8; }
        .btn.ghost { border-color: #d7deea; background: #f1f3f6; color: #35465e; }
        .btn.ghost:hover { border-color: #bdc9da; background: #e8edf4; color: #26364a; }
        .room-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
        .room-actions form { margin: 0; }
        .list { padding: 16px 0; border-top: 1px solid #e8e1d3; }
        .list:first-child { border-top: 0; }
        .complaint-item h3 { margin: 0 0 8px; }
        .complaint-item .complaint-heading p { margin: 7px 0 0; }
        .complaint-side { display: flex; flex: 0 0 auto; flex-direction: column; align-items: flex-end; gap: 12px; }
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

        /* Mobile Bottom Navigation (Mirip Flutter NavigationBar) */
        .mobile-bottom-nav {
            display: none;
        }

        @media (max-width: 1150px) {
            .top { gap: 14px; padding: 0 16px; }
            .brand { gap: 8px; font-size: 20px; }
            .brand img { width: 34px; height: 34px; }
            .nav { gap: 4px; }
            .nav a { min-height: 68px; padding: 0 8px; font-size: 13px; }
            .profile { max-width: 110px; font-size: 12.5px; }
            .profile small { font-size: 11px; }
        }

        /* Mobile View: Tampilan Sama Persis dengan Flutter Mobile App */
        @media (max-width: 800px) {
            .top {
                min-height: 64px;
                padding: 0 16px;
                justify-content: space-between;
                gap: 12px;
            }
            .brand { font-size: 20px; gap: 8px; }
            .brand img { width: 32px; height: 32px; }
            
            /* Sembunyikan desktop navbar dari atas */
            .nav {
                display: none !important;
            }

            .top-actions {
                margin-left: auto;
                gap: 10px;
            }
            .profile-avatar {
                flex-basis: 36px;
                width: 36px;
                height: 36px;
                font-size: 13.5px;
            }
            .profile {
                max-width: 120px;
                font-size: 12.5px;
            }
            .profile small {
                display: block;
                font-size: 10.5px;
            }
            .logout button {
                width: 36px;
                height: 36px;
            }
            .logout button svg {
                width: 17px;
                height: 17px;
            }

            .content {
                padding: 20px 14px 90px; /* Space bawah untuk bottom navbar */
            }

            /* Bottom Bar Gaya Flutter (Curved / Modern Floating App Bar) */
            .mobile-bottom-nav {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                height: 64px;
                background: rgba(255, 253, 248, 0.98);
                border-top: 1px solid #e8e1d3;
                display: flex;
                align-items: center;
                justify-content: space-around;
                z-index: 100;
                box-shadow: 0 -4px 20px rgba(38, 54, 74, 0.08);
                backdrop-filter: blur(12px);
                padding: 0 8px;
            }
            .mobile-bottom-nav a {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                flex: 1;
                height: 100%;
                color: #7b889b;
                text-decoration: none;
                font-size: 11px;
                font-weight: 500;
                gap: 3px;
                transition: color .18s, transform .18s;
            }
            .mobile-bottom-nav a svg {
                width: 22px;
                height: 22px;
                stroke: currentColor;
                fill: none;
                stroke-width: 2;
                stroke-linecap: round;
                stroke-linejoin: round;
                transition: transform .18s;
            }
            .mobile-bottom-nav a.active {
                color: #35465e;
                font-weight: 700;
            }
            .mobile-bottom-nav a.active svg {
                transform: translateY(-2px);
                stroke-width: 2.3;
            }

            .stats, .grid2 { grid-template-columns: 1fr; }
            .stats { gap: 10px; }
            .stat { min-height: 110px; border-radius: 16px; }
            .card { padding: 18px; border-radius: 16px; }
            .form-grid { grid-template-columns: 1fr; }
            .full { grid-column: auto; }
            .heading { flex-wrap: wrap; }
            .heading h1 { font-size: 24px; }
            .heading p { margin-bottom: 16px; }
            .complaint-heading { flex-direction: column; gap: 12px; }
            .complaint-side { flex-direction: row; align-items: center; flex-wrap: wrap; gap: 10px; }
            .complaint-item .complaint-heading p { margin-bottom: 0; }
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

        <!-- Navigasi Bawah Mobile (Mirip CurvedBottomNavbar di Flutter) -->
        <nav class="mobile-bottom-nav" aria-label="Navigasi Bawah Mobile">
            <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                <span>Beranda</span>
            </a>
            <a href="{{ route('events.index') }}" class="{{ request()->routeIs('events.*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                <span>Agenda</span>
            </a>
            <a href="{{ route('complaints.index') }}" class="{{ request()->routeIs('complaints.*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                <span>Pengaduan</span>
            </a>
            <a href="{{ route('calendar') }}" class="{{ request()->routeIs('calendar') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <span>Kalender</span>
            </a>
            @if($currentRole === 'Admin')
            <a href="{{ route('admin.reports.index') }}" class="{{ request()->routeIs('admin.*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                <span>Laporan</span>
            </a>
            @elseif($currentRole === 'PIC')
            <a href="{{ route('pic.complaint-reports.index') }}" class="{{ request()->routeIs('pic.*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/></svg>
                <span>Laporan</span>
            </a>
            @endif
            <a href="{{ route('profile') }}" class="{{ request()->routeIs('profile') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <span>Profil</span>
            </a>
        </nav>
    </div>

    <!-- Route bantuan init-storage tetap ada -->
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
