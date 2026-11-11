<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'RAPI-Sekolah' }}</title>
    <style>
        :root { color-scheme: light; color: #26364a; background: #f7f4eb; font-family: Inter, "Segoe UI", Arial, sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: #0b1028; }
        .shell { width: min(100% - 32px, 960px); margin: 0 auto; }
        .topbar { padding: 20px 0; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .brand { font-weight: 700; color: #e5eaff; text-decoration: none; }
        .panel { background: #171f4b; border: 1px solid #29366f; border-radius: 14px; padding: 28px; box-shadow: 0 8px 24px rgba(3, 7, 24, .24); }
        .login-panel { max-width: 420px; margin: 11vh auto; }
        h1 { font-size: 24px; margin: 0 0 8px; }
        p { color: #93a2ce; line-height: 1.5; }
        label { display: block; font-size: 14px; font-weight: 600; margin: 18px 0 6px; color: #d4dcf8; }
        input, select { width: 100%; min-height: 42px; border: 1px solid #354477; border-radius: 8px; padding: 9px 11px; background: #10183b; color: #e5eaff; font: inherit; }
        input:focus, select:focus { outline: 2px solid #718cff; outline-offset: 1px; border-color: #718cff; }
        input::placeholder { color: #7786b1; }
        button, .button { display: inline-flex; align-items: center; justify-content: center; min-height: 42px; border: 1px solid transparent; border-radius: 9px; padding: 9px 16px; background: #6388ff; color: #091438; font: inherit; font-weight: 700; cursor: pointer; text-decoration: none; transition: background .18s, transform .18s, box-shadow .18s; }
        button:hover, .button:hover { background: #7898ff; color: #081231; box-shadow: 0 5px 13px rgba(79, 112, 255, .2); transform: translateY(-1px); }
        .button.secondary { border-color: #3b4b80; background: #202b5a; color: #dce4ff; }
        .actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .submit { width: 100%; margin-top: 24px; }
        .remember { display: flex; gap: 8px; align-items: center; margin-top: 16px; font-size: 14px; color: #93a2ce; }
        .remember input { width: auto; min-height: auto; }
        .error { margin-top: 14px; color: #ff9297; font-size: 14px; }
        .password-field { display: flex; gap: 6px; }
        .password-field input { flex: 1; }
        .password-toggle { min-width: 42px; padding: 6px; border-color: #354477; background: #202b5a; color: #dce4ff; }
        .success { padding: 12px; background: rgba(22, 104, 72, .3); color: #72e1ad; border: 1px solid rgba(68, 198, 139, .25); border-radius: 9px; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        th, td { padding: 12px 8px; text-align: left; border-bottom: 1px solid rgba(113, 134, 195, .2); font-size: 14px; }
        th { color: #a4b1d6; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 18px; }
        .full { grid-column: 1 / -1; }
        .login-screen { position: relative; left: 50%; width: 100vw; min-height: 100vh; margin-left: -50vw; padding: 40px 24px; display: grid; place-items: center; overflow: hidden; background: radial-gradient(ellipse at 12% 8%, rgba(49, 82, 164, .3), transparent 38%), radial-gradient(ellipse at 92% 90%, rgba(31, 69, 112, .25), transparent 36%), #0b1028; }
        .login-screen::before, .login-screen::after { position: absolute; z-index: 0; width: 270px; height: 220px; content: ""; pointer-events: none; }
        .login-screen::before { top: -44px; left: -94px; border-radius: 42% 58% 61% 39% / 38% 35% 65% 62%; background: linear-gradient(135deg, rgba(77, 111, 206, .25), rgba(77, 111, 206, 0)); transform: rotate(-24deg); }
        .login-screen::after { right: -102px; bottom: -94px; border-radius: 60% 40% 38% 62% / 50% 35% 65% 50%; background: linear-gradient(315deg, rgba(75, 111, 208, .2), rgba(75, 111, 208, 0)); transform: rotate(-25deg); }
        .login-card { position: relative; z-index: 1; display: grid; grid-template-columns: 48% 52%; width: min(900px, 100%); min-height: 560px; overflow: hidden; border: 1px solid #354477; border-radius: 20px; background: linear-gradient(145deg, #171f4b, #141c43); box-shadow: 0 24px 70px rgba(3, 7, 24, .48); }
        .login-card::before { position: absolute; top: 0; bottom: 0; left: 0; width: 48%; content: ""; background: radial-gradient(ellipse at 50% 35%, rgba(66, 105, 198, .22), transparent 57%), linear-gradient(145deg, #1a285c, #151f4c); }
        .login-intro { position: relative; z-index: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px 32px; text-align: center; }
        .school-emblem { display: block; width: 190px; height: 190px; margin-bottom: 26px; object-fit: contain; }
        .login-intro h2 { margin: 0 0 8px; color: #f0f3ff; font-size: 22px; font-weight: 700; letter-spacing: .01em; }
        .login-intro p { margin: 0; color: #aab8e0; font-size: 14px; line-height: 1.6; }
        .login-panel { position: relative; z-index: 1; display: flex; flex-direction: column; justify-content: center; margin: 24px; padding: 42px clamp(24px, 4vw, 54px); border: 1px solid rgba(95, 116, 177, .28); border-radius: 16px; background: rgba(11, 16, 40, .34); box-shadow: 0 12px 30px rgba(3, 7, 24, .14); }
        .login-heading { text-align: center; }
        .login-heading h1 { margin: 0; color: #f0f3ff; font-size: 29px; font-weight: 700; letter-spacing: .02em; }
        .login-heading p { max-width: 280px; margin: 8px auto 22px; color: #93a2ce; font-size: 13px; line-height: 1.5; }
        .login-form label { margin: 18px 0 7px; color: #d4dcf8; font-size: 13px; font-weight: 600; }
        .login-form > label[for="password"] { margin-top: 18px; margin-bottom: 7px; }
        .login-form > input, .login-form .password-field input { width: 100%; height: 46px; min-height: 46px; padding: 10px 12px; border: 1px solid #354477; border-radius: 8px; background: #10183b; color: #e5eaff; font-size: 14px; }
        .login-form > input:focus, .login-form .password-field input:focus { outline: 2px solid rgba(113, 140, 255, .5); outline-offset: 1px; border-color: #718cff; background: #121c45; }
        .login-form .password-field { position: relative; display: block; }
        .login-form .password-field input { padding-right: 46px; }
        .login-form .password-toggle { position: absolute; top: 4px; right: 4px; min-width: 38px; min-height: 38px; padding: 0; border: 0; border-radius: 6px; background: transparent; color: #93a2ce; font-size: 14px; font-weight: 400; }
        .login-form .password-toggle:hover { background: #202b5a; color: #e5eaff; box-shadow: none; transform: none; }
        .login-options { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 16px; }
        .login-options .remember { display: flex; align-items: center; gap: 7px; margin: 0; color: #aab8e0; font-size: 12px; }
        .login-options .remember input { width: 15px; min-height: 15px; height: 15px; margin: 0; accent-color: #6388ff; }
        .login-options a { color: #9fb5ff; font-size: 12px; text-decoration: none; }
        .login-options a:hover { color: #c2d0ff; text-decoration: underline; }
        .login-form .error, .login-form .success { font-size: 12px; }
        .login-form .submit { width: 100%; min-height: 44px; margin: 24px 0 0; padding: 10px 14px; border-radius: 9px; background: #6388ff; color: #091438; font-size: 14px; }
        .login-form .submit:hover { background: #7898ff; }
        .calendar-link { margin-top: auto; padding-top: 26px; color: #aab8e0; font-size: 12px; text-align: center; text-decoration: none; }
        .calendar-link:hover { color: #dce4ff; text-decoration: underline; }
        .reset-screen { min-height: 100vh; }
        .reset-card { position: relative; z-index: 1; display: flex; flex-direction: column; width: min(440px, 100%); min-height: 0; padding: 38px; border: 1px solid #354477; border-radius: 18px; background: linear-gradient(145deg, #171f4b, #141c43); color: #e5eaff; box-shadow: 0 24px 70px rgba(3, 7, 24, .48); }
        .reset-card h1 { margin: 0; color: #f0f3ff; font-size: 26px; font-weight: 700; line-height: 1.2; }
        .reset-description { margin: 9px 0 22px; color: #93a2ce; font-size: 13px; line-height: 1.5; }
        .reset-card label { margin: 16px 0 7px; color: #d4dcf8; font-size: 13px; font-weight: 600; }
        .reset-card form > input:not([type="hidden"]), .reset-password-field input { width: 100%; height: 46px; min-height: 46px; padding: 10px 12px; border: 1px solid #354477; border-radius: 8px; background: #10183b; color: #e5eaff; font: inherit; }
        .reset-card form > input:focus, .reset-password-field input:focus { outline: 2px solid rgba(113, 140, 255, .5); outline-offset: 1px; border-color: #718cff; background: #121c45; }
        .reset-submit { width: 100%; min-height: 44px; margin-top: 22px; padding: 10px 14px; border: 1px solid transparent; border-radius: 9px; background: #6388ff; color: #091438; font-size: 14px; }
        .reset-submit:hover { background: #7898ff; }
        .reset-back { margin-top: auto; padding-top: 24px; color: #aab8e0; font-size: 13px; text-align: center; text-decoration: none; }
        .reset-back:hover { color: #dce4ff; text-decoration: underline; }
        .reset-resend { display: block; margin: 12px auto 0; padding: 4px 8px; min-height: 36px; border: 0; background: transparent; color: #9fb5ff; font-size: 13px; font-weight: 600; }
        .reset-resend:hover { background: transparent; color: #dce4ff; text-decoration: underline; box-shadow: none; transform: none; }
        .reset-change-email { display: inline-block; margin: 7px 0 2px; color: #9fb5ff; font-size: 12px; text-decoration: none; }
        .reset-change-email:hover { color: #dce4ff; text-decoration: underline; }
        .reset-password-field { position: relative; }
        .reset-password-field input { padding-right: 48px; }
        .reset-password-toggle { position: absolute; top: 4px; right: 4px; width: 38px; min-width: 38px; min-height: 38px; padding: 0; border: 0; border-radius: 6px; background: transparent; color: #93a2ce; }
        .reset-password-toggle:hover { background: #202b5a; color: #e5eaff; box-shadow: none; transform: none; }
        .reset-card .error { margin: 7px 0 0; color: #ff9297; font-size: 12px; }
        .reset-card .success { margin: 0 0 16px; font-size: 13px; }
        body { background: #f7f4eb; color: #26364a; }
        .brand { color: #26364a; }
        .panel, .login-card, .reset-card { border-color: #e8e1d3; background: #fffdf8; color: #26364a; box-shadow: 0 12px 36px rgba(38, 54, 74, .1); }
        .login-screen { background: #f7f4eb; }
        .login-screen::before, .login-screen::after { display: none; }
        .login-card::before { background: linear-gradient(145deg, #35465e, #26364a); }
        .login-intro h2 { color: #fffdf8; }
        .login-intro p { color: #e2e7ed; }
        .login-panel { border-color: #e8e1d3; background: #fffdf8; box-shadow: 0 8px 24px rgba(38, 54, 74, .06); }
        .login-heading h1, h1 { color: #26364a; }
        .reset-card h1 { color: #26364a; }
        .login-heading p, p { color: #6d7788; }
        label, .login-form label, .reset-card label { color: #35465e; }
        input, select, .login-form > input, .login-form .password-field input { border-color: #d7deea; background: #fffdf8; color: #26364a; }
        input:focus, select:focus, .login-form > input:focus, .login-form .password-field input:focus { outline-color: #667b99; border-color: #667b99; background: #fffdf8; }
        .reset-card form > input:not([type="hidden"]), .reset-password-field input { border-color: #d7deea; background: #fffdf8; color: #26364a; }
        .reset-card form > input:focus, .reset-password-field input:focus { outline-color: #667b99; border-color: #667b99; background: #fffdf8; }
        input::placeholder { color: #8993a2; }
        button, .button, .login-form .submit { background: #35465e; color: #fffdf8; }
        button:hover, .button:hover, .login-form .submit:hover { background: #465a73; color: #fffdf8; box-shadow: 0 5px 13px rgba(53, 70, 94, .16); }
        .reset-submit, .reset-submit:hover { background: #35465e; color: #fffdf8; }
        .button.secondary, .password-toggle { border-color: #d7deea; background: #f1f3f6; color: #35465e; }
        .password-toggle:hover, .login-form .password-toggle:hover { background: #e8edf4; color: #26364a; }
        .reset-password-toggle, .reset-password-toggle:hover { background: transparent; color: #667386; }
        .remember, .login-options .remember { color: #667386; }
        .login-options .remember input { accent-color: #35465e; }
        .login-options a, .reset-resend, .reset-change-email { color: #465a73; }
        .login-options a:hover, .reset-resend:hover, .reset-change-email:hover { color: #26364a; }
        .calendar-link, .reset-back { color: #667386; }
        .calendar-link:hover, .reset-back:hover { color: #26364a; }
        .error, .login-form .error, .reset-card .error { color: #465a73; }
        .reset-description { color: #6d7788; }
        .success { border-color: #d7deea; background: #f1f3f6; color: #35465e; }
        @media (max-width: 760px) {
            .login-screen { padding: 24px 16px; }
            .login-card { grid-template-columns: 1fr; width: min(480px, 100%); min-height: 0; }
            .login-card::before { right: 0; bottom: auto; width: 100%; height: 42%; }
            .login-intro { padding: 30px 24px 20px; }
            .school-emblem { width: 120px; height: 120px; margin-bottom: 16px; }
            .login-intro h2 { font-size: 19px; }
            .login-panel { margin: 0 16px 16px; padding: 26px 22px 22px; }
            .calendar-link { padding-top: 22px; }
            .reset-card { padding: 30px 24px; }
        }
        @media (max-width: 640px) { .panel { padding: 20px; } .grid { grid-template-columns: 1fr; } .topbar { align-items: flex-start; flex-direction: column; } }
@include('partials.flash-alert-styles')
    </style>
</head>
<body>
    <main class="shell">
        @auth
            <header class="topbar">
                <a class="brand" href="{{ route('dashboard') }}">RAPI-Sekolah</a>
                <div class="actions">
                    @if(auth()->user()->role?->role_name === 'Admin')
                        <a class="button secondary" href="{{ route('admin.users.index') }}">Kelola Akun</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">@csrf <button type="submit">Keluar</button></form>
                </div>
            </header>
        @endauth

        @yield('content')
    </main>
</body>
<script>
    document.querySelectorAll('.password-toggle').forEach(function (button) {
        button.addEventListener('click', function () {
            const input = button.parentElement.querySelector('input');
            const visible = input.type === 'password';
            input.type = visible ? 'text' : 'password';
            button.setAttribute('aria-label', visible ? 'Sembunyikan password' : 'Tampilkan password');
            button.textContent = visible ? '◉' : '◌';
        });
    });
</script>
@include('partials.flash-alert-script')
</html>
