<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
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

        html {
            scrollbar-gutter: stable;
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
            min-height: 64px;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 0 clamp(16px, 2.5vw, 36px);
            border-top: 3.5px solid #35465e;
            border-bottom: 1px solid #e2dbcb;
            background: rgba(255, 253, 248, .98);
            box-shadow: 0 4px 18px rgba(38, 54, 74, .06);
            backdrop-filter: blur(14px);
        }
        .brand {
            display: inline-flex;
            flex: 0 0 auto;
            align-items: center;
            gap: 10px;
            color: #1e293b;
            font-family: Inter, system-ui, -apple-system, sans-serif;
            font-size: 20px;
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
            width: 36px;
            height: 36px;
            object-fit: contain;
            filter: drop-shadow(0 2px 4px rgba(38, 54, 74, .12));
        }
        .nav {
            display: flex;
            flex: 1 1 auto;
            min-width: 0;
            align-items: stretch;
            justify-content: safe center;
            gap: clamp(4px, .6vw, 12px);
            margin: 0 clamp(4px, .8vw, 14px);
            overflow-x: auto;
            scrollbar-width: none;
        }
        .nav::-webkit-scrollbar {
            display: none;
        }
        .nav a {
            position: relative;
            display: inline-flex;
            flex: 0 0 auto;
            min-width: 0;
            min-height: 64px;
            align-items: center;
            padding: 0 clamp(9px, .9vw, 16px);
            color: #64748b;
            font-size: 14px;
            font-weight: 600;
            letter-spacing: 0;
            word-spacing: 1px;
            white-space: nowrap;
            text-decoration: none;
            background: transparent;
            transition: color .18s ease;
        }
        .nav a::after {
            position: absolute;
            right: clamp(9px, .9vw, 16px);
            bottom: 0;
            left: clamp(9px, .9vw, 16px);
            height: 3px;
            border-radius: 3px 3px 0 0;
            background: transparent;
            content: "";
            transition: background .18s ease;
        }
        .nav a:hover {
            color: #1e293b;
            background: transparent;
        }
        .nav a.active {
            color: #1e293b;
            font-weight: 600;
            background: transparent;
        }
        .nav a.active::after {
            background: var(--accent);
        }
        .top-actions {
            display: flex;
            flex: 0 0 auto;
            align-items: center;
            gap: 10px;
        }
        .profile-link {
            display: inline-flex;
            min-width: 0;
            align-items: center;
            gap: 10px;
            padding: 4px 8px;
            border-radius: 10px;
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
            flex: 0 0 36px;
            width: 36px;
            height: 36px;
            place-items: center;
            border: 1.5px solid #d7deea;
            border-radius: 50%;
            background: #eef2f7;
            color: #35465e;
            font-size: 14px;
            font-weight: 700;
            box-shadow: 0 2px 5px rgba(38, 54, 74, .08);
            transition: background .18s, border-color .18s, transform .18s;
        }
        .profile-link:hover .profile-avatar {
            border-color: #bdc9da;
            background: #e2e8f0;
        }
        .profile {
            max-width: 140px;
            overflow: hidden;
            color: #1e293b;
            font-size: 13px;
            font-weight: 700;
            line-height: 1.25;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .profile small {
            display: block;
            margin-top: 1px;
            color: #64748b;
            font-size: 11px;
            font-weight: 500;
        }
        .logout {
            margin: 0;
        }
        .logout button {
            display: grid;
            width: 36px;
            height: 36px;
            place-items: center;
            padding: 0;
            border: 1px solid #d7deea;
            border-radius: 9px;
            background: #f8fafc;
            color: #64748b;
            cursor: pointer;
            transition: background .18s, color .18s, border-color .18s, transform .18s;
        }
        .logout button svg {
            width: 17px;
            height: 17px;
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

        /* =========================================================================
           PUSAT NOTIFIKASI WEB DESKTOP (LONCENG NAVBAR, DROPDOWN, TOAST, HIGHLIGHT)
           ========================================================================= */
        .notif-menu {
            position: relative;
        }
        .notif-bell-btn {
            position: relative;
            display: grid;
            width: 36px;
            height: 36px;
            place-items: center;
            padding: 0;
            border: 1px solid #d7deea;
            border-radius: 9px;
            background: #f8fafc;
            color: #35465e;
            cursor: pointer;
            transition: background .18s, color .18s, border-color .18s, transform .18s;
        }
        .notif-bell-btn svg {
            width: 18px;
            height: 18px;
            fill: none;
            stroke: currentColor;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-width: 1.9;
        }
        .notif-bell-btn:hover,
        .notif-bell-btn[aria-expanded="true"] {
            background: #eef2f7;
            border-color: #9fb0c7;
            color: #1e293b;
            transform: translateY(-1px);
        }
        .notif-bell-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            border: 2px solid #fffdf8;
            border-radius: 99px;
            background: #dc2626;
            color: #fff;
            font-size: 10px;
            font-weight: 800;
            line-height: 14px;
            text-align: center;
            box-shadow: 0 2px 6px rgba(220, 38, 38, .35);
        }
        .notif-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            z-index: 60;
            width: min(420px, calc(100vw - 24px));
            border: 1px solid #dfd6c4;
            border-radius: 16px;
            background: #fffdf8;
            box-shadow: 0 18px 48px rgba(26, 39, 56, .18);
            overflow: hidden;
        }
        .notif-dropdown-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 14px 16px 10px;
            border-bottom: 1px solid #ece5d8;
            background: #faf7f0;
        }
        .notif-dropdown-title-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .notif-dropdown-title {
            margin: 0;
            font-size: 14.5px;
            font-weight: 800;
            color: #26364a;
        }
        .notif-dropdown-count {
            padding: 2px 8px;
            border-radius: 99px;
            background: #e8edf4;
            color: #35465e;
            font-size: 11px;
            font-weight: 700;
        }
        .notif-mark-all-btn {
            padding: 4px 9px;
            border: 1px solid #d7deea;
            border-radius: 7px;
            background: #fffdf8;
            color: #35465e;
            font-size: 11.5px;
            font-weight: 600;
            cursor: pointer;
            transition: background .16s, border-color .16s;
        }
        .notif-mark-all-btn:hover:not(:disabled) {
            background: #eef2f7;
            border-color: #9fb0c7;
        }
        .notif-mark-all-btn:disabled {
            opacity: .45;
            cursor: default;
        }
        .notif-dropdown-tabs {
            display: flex;
            gap: 6px;
            padding: 8px 16px;
            border-bottom: 1px solid #ece5d8;
            background: #faf7f0;
        }
        .notif-tab-btn {
            padding: 4px 10px;
            border: 1px solid transparent;
            border-radius: 99px;
            background: transparent;
            color: #64748b;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }
        .notif-tab-btn.active {
            border-color: #35465e;
            background: #35465e;
            color: #fffdf8;
        }
        .notif-dropdown-list {
            max-height: 380px;
            overflow-y: auto;
        }
        .notif-dd-item {
            display: flex;
            align-items: flex-start;
            gap: 11px;
            padding: 12px 16px;
            border-bottom: 1px solid #efe9dc;
            background: #fffdf8;
            cursor: pointer;
            transition: background .15s;
        }
        .notif-dd-item:last-child {
            border-bottom: 0;
        }
        .notif-dd-item:hover {
            background: #f7f4eb;
        }
        .notif-dd-item.is-unread {
            background: #f2f6fb;
            box-shadow: inset 3px 0 0 #35465e;
        }
        .notif-dd-item.is-unread:hover {
            background: #e9eff8;
        }
        .notif-dd-icon {
            display: grid;
            place-items: center;
            flex: 0 0 32px;
            width: 32px;
            height: 32px;
            margin-top: 2px;
            border-radius: 9px;
            border: 1px solid #d7deea;
            background: #f1f5f9;
            color: #35465e;
        }
        .notif-dd-icon.tone-success { background: #ecfdf5; border-color: #a7f3d0; color: #15803d; }
        .notif-dd-icon.tone-warning { background: #fffbeb; border-color: #fde68a; color: #b45309; }
        .notif-dd-icon.tone-danger { background: #fef2f2; border-color: #fecaca; color: #b91c1c; }
        .notif-dd-icon.tone-info { background: #eff6ff; border-color: #bfdbfe; color: #1d4ed8; }
        .notif-dd-icon svg {
            width: 15px;
            height: 15px;
            fill: none;
            stroke: currentColor;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        .notif-dd-content {
            flex: 1 1 auto;
            min-width: 0;
        }
        .notif-dd-badges {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 5px;
            margin-bottom: 4px;
        }
        .notif-dd-cat {
            font-size: 10.5px;
            font-weight: 700;
            color: #465a73;
        }
        .notif-dd-pill {
            padding: 1px 7px;
            border-radius: 99px;
            font-size: 10px;
            font-weight: 700;
        }
        .notif-dd-pill.tone-success { background: #dcfce7; color: #166534; }
        .notif-dd-pill.tone-warning { background: #fef3c7; color: #92400e; }
        .notif-dd-pill.tone-danger { background: #fee2e2; color: #991b1b; }
        .notif-dd-pill.tone-info { background: #e0f2fe; color: #0369a1; }
        .notif-dd-title {
            margin: 0 0 2px;
            color: #26364a;
            font-size: 13px;
            font-weight: 700;
            line-height: 1.3;
        }
        .notif-dd-msg {
            margin: 0;
            color: #46566d;
            font-size: 12px;
            line-height: 1.45;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .notif-dd-meta {
            margin-top: 5px;
            color: #6d7788;
            font-size: 11px;
        }
        .notif-dd-read-btn {
            flex: 0 0 auto;
            width: 24px;
            height: 24px;
            display: grid;
            place-items: center;
            border: 1px solid #cfd8e5;
            border-radius: 50%;
            background: #fffdf8;
            color: #35465e;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }
        .notif-dd-read-btn:hover {
            background: #35465e;
            border-color: #35465e;
            color: #fff;
        }
        .notif-dropdown-empty {
            padding: 28px 18px;
            text-align: center;
            color: #6d7788;
            font-size: 13px;
        }
        .notif-dropdown-footer {
            padding: 10px 16px;
            border-top: 1px solid #ece5d8;
            background: #faf7f0;
            text-align: center;
        }
        .notif-dropdown-footer a {
            display: block;
            color: #35465e;
            font-size: 12.5px;
            font-weight: 700;
            text-decoration: none;
        }
        .notif-dropdown-footer a:hover {
            color: #1e293b;
            text-decoration: underline;
        }

        .notif-toast-stack {
            position: fixed;
            right: 20px;
            bottom: 20px;
            z-index: 90;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: min(370px, calc(100vw - 28px));
            pointer-events: none;
        }
        .notif-toast {
            pointer-events: auto;
            padding: 13px 15px;
            border: 1px solid #d7deea;
            border-left: 4px solid #35465e;
            border-radius: 12px;
            background: #fffdf8;
            box-shadow: 0 14px 36px rgba(26, 39, 56, .2);
            animation: notifToastIn .22s ease;
        }
        @keyframes notifToastIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .notif-toast-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 4px;
        }
        .notif-toast-cat {
            font-size: 11px;
            font-weight: 800;
            color: #35465e;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .notif-toast-close {
            border: 0;
            background: transparent;
            color: #64748b;
            font-size: 16px;
            line-height: 1;
            cursor: pointer;
        }
        .notif-toast-title {
            margin: 0 0 3px;
            font-size: 13.5px;
            font-weight: 700;
            color: #26364a;
        }
        .notif-toast-msg {
            margin: 0 0 8px;
            font-size: 12.5px;
            color: #46566d;
            line-height: 1.45;
        }
        .notif-toast-link {
            font-size: 12px;
            font-weight: 700;
            color: #35465e;
            text-decoration: none;
        }
        .notif-toast-link:hover {
            text-decoration: underline;
        }

        .highlight-target {
            margin: 6px -12px;
            padding: 16px 14px !important;
            border: 1.5px solid #35465e !important;
            border-left: 5px solid #d97706 !important;
            border-radius: 12px;
            background: #fffbeb;
            box-shadow: 0 8px 24px rgba(53, 70, 94, .1);
            animation: highlightPulse 2.4s ease;
        }
        @keyframes highlightPulse {
            0% { box-shadow: 0 0 0 5px rgba(217, 119, 6, .28); }
            100% { box-shadow: 0 8px 24px rgba(53, 70, 94, .1); }
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

        /* =========================================================================
           CUSTOM MODERN MODAL DIALOG (TEMA SESUAI DESAIN RAPI / MIRIP KALENDER)
           ========================================================================= */
        .confirm-dialog {
            width: min(500px, calc(100% - 32px));
            max-height: min(86vh, 660px);
            overflow: auto;
            margin: auto;
            padding: 0;
            border: 1px solid #e8e1d3;
            border-radius: 22px;
            background: #fffdf8;
            color: #26364a;
            box-shadow: 0 24px 80px rgba(38, 54, 74, .25);
        }
        .confirm-dialog::backdrop {
            background: rgba(38, 54, 74, .36);
            backdrop-filter: blur(5px);
        }
        .confirm-dialog-heading {
            display: flex;
            align-items: start;
            justify-content: space-between;
            gap: 16px;
            padding: 24px 26px 18px;
            border-bottom: 1px solid #e8e1d3;
        }
        .confirm-dialog-heading h2 {
            margin: 0;
            color: #26364a;
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -.3px;
        }
        .confirm-dialog-heading p {
            margin: 4px 0 0;
            color: #6d7788;
            font-size: 13px;
        }
        .confirm-dialog-close {
            display: grid;
            flex: 0 0 32px;
            width: 32px;
            height: 32px;
            place-items: center;
            border: 0;
            border-radius: 50%;
            background: #f1f3f6;
            color: #64748b;
            cursor: pointer;
            font-size: 20px;
            line-height: 1;
            transition: background .18s, color .18s, transform .18s;
        }
        .confirm-dialog-close:hover {
            background: #e2e8f0;
            color: #1e293b;
            transform: rotate(90deg);
        }
        .confirm-dialog-body {
            padding: 22px 26px;
        }
        .confirm-dialog-details {
            border-left: 3.5px solid #d97706; /* Garis aksen oranye seperti pada tampilan kalender */
            padding-left: 16px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .confirm-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .confirm-label {
            font-size: 11px;
            font-weight: 700;
            color: #6d7788;
            text-transform: uppercase;
            letter-spacing: .06em;
        }
        .confirm-value {
            font-size: 14px;
            font-weight: 600;
            color: #26364a;
            word-break: break-word;
        }
        .confirm-pill {
            display: inline-flex;
            align-items: center;
            align-self: flex-start;
            margin-top: 3px;
            padding: 2px 10px;
            border: 1px solid #fde68a;
            border-radius: 99px;
            background: #fef3c7;
            color: #92400e;
            font-size: 11.5px;
            font-weight: 600;
        }
        .confirm-dialog-footer {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            padding: 16px 26px 22px;
            border-top: 1px solid #e8e1d3;
            background: #fbf9f4;
        }

        /* Mobile Bottom Navigation (Mirip Flutter NavigationBar) */
        .mobile-bottom-nav {
            display: none !important;
        }

        @media (max-width: 1250px) {
            .top { gap: 10px; padding: 0 16px; }
            .brand { gap: 8px; font-size: 18px; }
            .brand img { width: 32px; height: 32px; }
            .nav { gap: 4px; margin: 0 4px; }
            .nav a { min-height: 64px; padding: 0 7px; font-size: 13px; word-spacing: .5px; }
            .nav a::after { right: 7px; left: 7px; }
            .profile-avatar { width: 32px; height: 32px; font-size: 13px; }
            .profile { max-width: 105px; font-size: 12px; }
            .profile small { font-size: 10.5px; }
            .logout button { width: 32px; height: 32px; }
        }

        @media (max-width: 1024px) {
            .nav a { padding: 0 4px; font-size: 12px; }
            .nav a::after { right: 4px; left: 4px; }
        }

        /* Mobile View: Tampilan Sama Persis dengan Flutter Mobile App */
        @media (max-width: 800px) {
            .top {
                min-height: 58px;
                padding: 0 14px;
                justify-content: space-between;
                gap: 10px;
            }
            .brand { font-size: 19px; gap: 8px; }
            .brand img { width: 32px; height: 32px; }
            
            /* Sembunyikan desktop navbar dari atas */
            .nav {
                display: none !important;
            }

            .top-actions {
                margin-left: auto;
                gap: 8px;
            }
            .profile-avatar {
                flex-basis: 34px;
                width: 34px;
                height: 34px;
                font-size: 13px;
            }
            .profile {
                max-width: 110px;
                font-size: 12px;
            }
            .profile small {
                display: block;
                font-size: 10px;
            }
            .logout button {
                width: 34px;
                height: 34px;
            }
            .logout button svg {
                width: 16px;
                height: 16px;
            }
            .notif-bell-btn {
                width: 34px;
                height: 34px;
            }
            .notif-bell-btn svg {
                width: 16px;
                height: 16px;
            }
            .notif-dropdown {
                position: fixed;
                top: 68px;
                right: 12px;
                left: 12px;
                width: auto;
            }
            .notif-toast-stack {
                bottom: 76px;
                right: 12px;
                left: 12px;
                max-width: none;
            }

            .content {
                padding: 18px 12px 90px;
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
                display: flex !important;
                align-items: center;
                justify-content: safe center;
                overflow-x: auto;
                scrollbar-width: none;
                z-index: 100;
                box-shadow: 0 -4px 20px rgba(38, 54, 74, 0.08);
                backdrop-filter: blur(12px);
                padding: 0 6px;
            }
            .mobile-bottom-nav::-webkit-scrollbar {
                display: none;
            }
            .mobile-bottom-nav a {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                flex: 1 0 56px;
                min-width: 56px;
                padding: 0 4px;
                height: 100%;
                color: #7b889b;
                text-decoration: none;
                font-size: 10.5px;
                font-weight: 500;
                white-space: nowrap;
                gap: 3px;
                transition: color .18s, transform .18s;
            }
            .mobile-bottom-nav a svg {
                width: 20px;
                height: 20px;
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
            .card { padding: 16px; border-radius: 16px; }
            .form-grid { grid-template-columns: 1fr; }
            .full { grid-column: auto; }
            .heading { flex-wrap: wrap; gap: 12px; }
            .heading h1 { font-size: 22px; }
            .heading p { margin-bottom: 12px; }
            .complaint-heading { flex-direction: column; gap: 12px; }
            .complaint-side { flex-direction: column; align-items: flex-start !important; width: 100%; gap: 10px; }
            .complaint-item .complaint-heading p { margin-bottom: 0; }
            .report-table-wrap, .card:has(> table) { overflow-x: auto; -webkit-overflow-scrolling: touch; }
            table { min-width: 600px; font-size: 13px; }
            .dashboard-date { padding: 0 0 8px; }
            .quick-action { width: 100%; }
        }
    </style>
</head>
<body>
    @php($currentUser = auth()->user())
    @php($currentRole = $currentUser?->role?->role_name)
    @php($unreadSummary = $currentUser ? \App\Models\Notification::visibleTo($currentUser)->where('is_read', false)->selectRaw('COUNT(*) as total, COALESCE(MAX(id), 0) as latest_id')->first() : null)
    @php($unreadNotificationCount = (int) ($unreadSummary?->total ?? 0))
    @php($latestUnreadNotificationId = (int) ($unreadSummary?->latest_id ?? 0))
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
                @if($currentUser)
                    <div class="notif-menu" id="notif-menu">
                        <button
                            type="button"
                            id="notif-bell-btn"
                            class="notif-bell-btn"
                            aria-label="Notifikasi"
                            aria-expanded="false"
                            aria-controls="notif-dropdown"
                            title="Notifikasi"
                        >
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                            </svg>
                            <span
                                id="notif-bell-badge"
                                class="notif-bell-badge"
                                @if($unreadNotificationCount <= 0) hidden @endif
                            >{{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}</span>
                        </button>

                        <div id="notif-dropdown" class="notif-dropdown" role="region" aria-label="Daftar notifikasi" hidden>
                            <div class="notif-dropdown-header">
                                <div class="notif-dropdown-title-wrap">
                                    <h3 class="notif-dropdown-title">Notifikasi</h3>
                                    <span id="notif-dropdown-count" class="notif-dropdown-count">
                                        {{ $unreadNotificationCount > 0 ? $unreadNotificationCount.' belum dibaca' : 'Semua terbaca' }}
                                    </span>
                                </div>
                                <button
                                    type="button"
                                    id="notif-mark-all-btn"
                                    class="notif-mark-all-btn"
                                    @if($unreadNotificationCount <= 0) disabled @endif
                                >Tandai semua dibaca</button>
                            </div>
                            <div class="notif-dropdown-tabs">
                                <button type="button" class="notif-tab-btn active" data-notif-filter="all">Semua</button>
                                <button type="button" class="notif-tab-btn" data-notif-filter="unread">Belum Dibaca</button>
                            </div>
                            <div id="notif-dropdown-list" class="notif-dropdown-list">
                                <div class="notif-dropdown-empty">Memuat notifikasi...</div>
                            </div>
                            <div class="notif-dropdown-footer">
                                <a href="{{ route('notifications.index') }}">Lihat semua di Pusat Notifikasi →</a>
                            </div>
                        </div>
                    </div>
                @endif
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
            <a href="{{ route('admin.reports.index') }}" class="{{ request()->routeIs('admin.reports.*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                <span>Laporan</span>
            </a>
            <a href="{{ route('rooms.index') }}" class="{{ request()->routeIs('rooms.*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/><path d="M9 9v.01"/><path d="M9 12v.01"/><path d="M9 15v.01"/><path d="M9 18v.01"/></svg>
                <span>Ruangan</span>
            </a>
            <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span>Pengguna</span>
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

    <div id="notif-toast-stack" class="notif-toast-stack" aria-live="polite"></div>

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

        (function () {
            var highlighted = document.querySelector('.highlight-target');
            if (highlighted) {
                highlighted.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            var menu = document.getElementById('notif-menu');
            var bellBtn = document.getElementById('notif-bell-btn');
            var dropdown = document.getElementById('notif-dropdown');
            var listEl = document.getElementById('notif-dropdown-list');
            var badgeEl = document.getElementById('notif-bell-badge');
            var countEl = document.getElementById('notif-dropdown-count');
            var markAllBtn = document.getElementById('notif-mark-all-btn');
            var toastStack = document.getElementById('notif-toast-stack');
            if (!menu || !bellBtn || !dropdown || !listEl) return;

            var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            var feedUrl = @json(route('notifications.feed', [], false));
            var readAllUrl = @json(route('notifications.read-all', [], false));
            var storageKey = 'rapi_last_unread_' + @json($currentUser?->id ?? 0);
            var activeFilter = 'all';
            var isOpen = false;
            var latestKnownUnreadId = Math.max(
                Number(sessionStorage.getItem(storageKey) || '0'),
                Number(@json($latestUnreadNotificationId))
            );
            sessionStorage.setItem(storageKey, String(latestKnownUnreadId));
            var initializedBaseline = true;

            function updateBadge(count) {
                var n = Number(count || 0);
                if (badgeEl) {
                    if (n > 0) {
                        badgeEl.hidden = false;
                        badgeEl.textContent = n > 99 ? '99+' : String(n);
                    } else {
                        badgeEl.hidden = true;
                        badgeEl.textContent = '0';
                    }
                }
                if (countEl) {
                    countEl.textContent = n > 0 ? (n + ' belum dibaca') : 'Semua terbaca';
                }
                if (markAllBtn) {
                    markAllBtn.disabled = n <= 0;
                }
            }

            function buildIconSvg(category) {
                var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                svg.setAttribute('viewBox', '0 0 24 24');
                if (category === 'booking') {
                    svg.innerHTML = '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line>';
                } else if (category === 'complaint') {
                    svg.innerHTML = '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>';
                } else {
                    svg.innerHTML = '<circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line>';
                }
                return svg;
            }

            function renderItems(items) {
                listEl.textContent = '';
                if (!items || items.length === 0) {
                    var empty = document.createElement('div');
                    empty.className = 'notif-dropdown-empty';
                    empty.textContent = activeFilter === 'unread'
                        ? 'Tidak ada notifikasi yang belum dibaca.'
                        : 'Belum ada notifikasi aktivitas.';
                    listEl.appendChild(empty);
                    return;
                }

                items.forEach(function (item) {
                    var row = document.createElement('div');
                    row.className = 'notif-dd-item' + (item.is_read ? '' : ' is-unread');
                    row.addEventListener('click', function () {
                        window.location.href = item.open_url;
                    });

                    var iconWrap = document.createElement('div');
                    iconWrap.className = 'notif-dd-icon tone-' + (item.tone || 'info');
                    iconWrap.appendChild(buildIconSvg(item.category));

                    var content = document.createElement('div');
                    content.className = 'notif-dd-content';

                    var badges = document.createElement('div');
                    badges.className = 'notif-dd-badges';

                    var catSpan = document.createElement('span');
                    catSpan.className = 'notif-dd-cat';
                    catSpan.textContent = item.category_label || 'Notifikasi';
                    badges.appendChild(catSpan);

                    var pillSpan = document.createElement('span');
                    pillSpan.className = 'notif-dd-pill tone-' + (item.tone || 'info');
                    pillSpan.textContent = item.badge_label || 'Info';
                    badges.appendChild(pillSpan);

                    var title = document.createElement('p');
                    title.className = 'notif-dd-title';
                    title.textContent = item.title || '';

                    var msg = document.createElement('p');
                    msg.className = 'notif-dd-msg';
                    msg.textContent = item.summary || item.message || '';

                    var metaParts = [];
                    if (Array.isArray(item.meta) && item.meta.length > 0) {
                        metaParts.push(item.meta[0].value);
                    }
                    if (item.time_ago) {
                        metaParts.push(item.time_ago);
                    }
                    var meta = document.createElement('div');
                    meta.className = 'notif-dd-meta';
                    meta.textContent = metaParts.join(' · ');

                    content.appendChild(badges);
                    content.appendChild(title);
                    content.appendChild(msg);
                    content.appendChild(meta);

                    row.appendChild(iconWrap);
                    row.appendChild(content);

                    if (!item.is_read && item.read_url) {
                        var readBtn = document.createElement('button');
                        readBtn.type = 'button';
                        readBtn.className = 'notif-dd-read-btn';
                        readBtn.title = 'Tandai sudah dibaca';
                        readBtn.setAttribute('aria-label', 'Tandai sudah dibaca');
                        readBtn.textContent = '✓';
                        readBtn.addEventListener('click', function (ev) {
                            ev.stopPropagation();
                            markSingleRead(item.read_url);
                        });
                        row.appendChild(readBtn);
                    }

                    listEl.appendChild(row);
                });
            }

            function showToast(item) {
                if (!toastStack || !item) return;
                var toast = document.createElement('div');
                toast.className = 'notif-toast';

                var head = document.createElement('div');
                head.className = 'notif-toast-head';

                var cat = document.createElement('span');
                cat.className = 'notif-toast-cat';
                cat.textContent = (item.category_label || 'Notifikasi') + ' • ' + (item.badge_label || 'Baru');

                var closeBtn = document.createElement('button');
                closeBtn.type = 'button';
                closeBtn.className = 'notif-toast-close';
                closeBtn.setAttribute('aria-label', 'Tutup notifikasi');
                closeBtn.textContent = '×';
                closeBtn.addEventListener('click', function () {
                    toast.remove();
                });

                head.appendChild(cat);
                head.appendChild(closeBtn);

                var title = document.createElement('p');
                title.className = 'notif-toast-title';
                title.textContent = item.title || 'Notifikasi Baru';

                var msg = document.createElement('p');
                msg.className = 'notif-toast-msg';
                msg.textContent = item.summary || item.message || '';

                var link = document.createElement('a');
                link.className = 'notif-toast-link';
                link.href = item.open_url;
                link.textContent = (item.action_label || 'Lihat Detail') + ' →';

                toast.appendChild(head);
                toast.appendChild(title);
                toast.appendChild(msg);
                toast.appendChild(link);
                toastStack.appendChild(toast);

                setTimeout(function () {
                    if (toast.parentNode) toast.remove();
                }, 7000);
            }

            async function fetchFeed(allowToast) {
                try {
                    var url = feedUrl + (activeFilter === 'unread' ? '?only_unread=1' : '');
                    var res = await fetch(url, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    if (!res.ok) return;
                    var data = await res.json();
                    updateBadge(data.unread_count);

                    if (isOpen) {
                        renderItems(data.items || []);
                    }

                    var incomingId = Number(data.latest_unread_id || 0);
                    if (!initializedBaseline) {
                        initializedBaseline = true;
                        if (incomingId > latestKnownUnreadId) {
                            latestKnownUnreadId = incomingId;
                            sessionStorage.setItem(storageKey, String(latestKnownUnreadId));
                        }
                    } else if (allowToast && incomingId > latestKnownUnreadId) {
                        latestKnownUnreadId = incomingId;
                        sessionStorage.setItem(storageKey, String(latestKnownUnreadId));
                        var newest = (data.items || []).find(function (x) { return Number(x.id) === incomingId; });
                        if (newest) showToast(newest);
                    }
                } catch (e) {
                    // Abaikan error jaringan sementara saat polling
                }
            }

            async function markSingleRead(url) {
                try {
                    var res = await fetch(url, {
                        method: 'PATCH',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    if (res.ok) {
                        await fetchFeed(false);
                    }
                } catch (e) {}
            }

            async function markAllRead() {
                try {
                    var res = await fetch(readAllUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    if (res.ok) {
                        await fetchFeed(false);
                    }
                } catch (e) {}
            }

            function setOpen(open) {
                isOpen = open;
                dropdown.hidden = !open;
                bellBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (open) {
                    fetchFeed(false);
                }
            }

            bellBtn.addEventListener('click', function (ev) {
                ev.stopPropagation();
                setOpen(!isOpen);
            });

            dropdown.addEventListener('click', function (ev) {
                ev.stopPropagation();
            });

            document.addEventListener('click', function () {
                if (isOpen) setOpen(false);
            });

            document.addEventListener('keydown', function (ev) {
                if (ev.key === 'Escape' && isOpen) setOpen(false);
            });

            dropdown.querySelectorAll('[data-notif-filter]').forEach(function (tab) {
                tab.addEventListener('click', function () {
                    activeFilter = tab.getAttribute('data-notif-filter') || 'all';
                    dropdown.querySelectorAll('[data-notif-filter]').forEach(function (b) {
                        b.classList.toggle('active', b === tab);
                    });
                    fetchFeed(false);
                });
            });

            if (markAllBtn) {
                markAllBtn.addEventListener('click', markAllRead);
            }

            setInterval(function () {
                if (document.visibilityState === 'visible') {
                    fetchFeed(true);
                }
            }, 25000);
        })();
    </script>
</body>
</html>
