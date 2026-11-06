<style>
    .public-calendar {
        min-height: 100vh;
        margin: 0;
        box-sizing: border-box;
        background: #f7f4eb;
        color: #26364a;
        font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    }
    .public-calendar * { box-sizing: border-box; }
    .public-topbar {
        height: 68px;
        padding: 0 max(28px, calc((100% - 1200px) / 2));
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-top: 3.5px solid #35465e;
        border-bottom: 1px solid #e2dbcb;
        background: rgba(255, 253, 248, .98);
        box-shadow: 0 4px 18px rgba(38, 54, 74, .06);
    }
    .public-brand { display: inline-flex; align-items: center; gap: 8px; color: #172033; font-family: Inter, system-ui, -apple-system, sans-serif; font-size: 21px; font-weight: 800; text-decoration: none; }
    .brand-emblem { display: block; width: 34px; height: 34px; object-fit: contain; }
    .login-link { padding: 10px 23px; border-radius: 9px; background: #315cff; color: #fff; font-size: 13px; font-weight: 700; text-decoration: none; box-shadow: 0 5px 12px #315cff30; transition: transform .18s, box-shadow .18s; }
    .login-link:hover { transform: translateY(-1px); box-shadow: 0 8px 16px #315cff40; }
    .public-wrap { width: min(1200px, calc(100% - 48px)); margin: 0 auto; padding: 34px 0 56px; }
    .calendar-page { color: #202d5b; }
    .calendar-page-heading { display: flex; align-items: end; justify-content: space-between; gap: 18px; margin-bottom: 24px; }
    .calendar-page-heading h1 { margin: 0; color: #202d5b; font-size: 26px; letter-spacing: -.5px; }
    .calendar-page-heading p { margin: 7px 0 0; color: #7886aa; font-size: 13px; }
    .approved-count { padding-bottom: 4px; color: #8290b2; font-size: 12px; white-space: nowrap; }
    .approved-count strong { color: #315cff; }
    .calendar-card {
        padding: 25px;
        border: 1px solid #e7edf8;
        border-radius: 20px;
        background: linear-gradient(145deg, #fff 0%, #fff 65%, #fbfcff 100%);
        box-shadow: 0 14px 34px rgba(32, 52, 103, .09), 0 2px 7px rgba(32, 52, 103, .04);
    }
    .calendar-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 16px; }
    .month-title { margin: 0; color: #26364a; font-family: Inter, system-ui, -apple-system, sans-serif; font-size: 22px; font-weight: 700; letter-spacing: -.4px; }
    .month-title span { color: #8190b7; }
    .calendar-controls { display: flex; align-items: center; gap: 9px; }
    .calendar-control {
        display: inline-grid;
        min-width: 38px;
        height: 36px;
        place-items: center;
        padding: 0 12px;
        border: 1px solid #e2e9f8;
        border-radius: 11px;
        background: #fff;
        color: #40527e;
        font: inherit;
        font-size: 13px;
        text-decoration: none;
        transition: border-color .18s, color .18s, background .18s, transform .18s, box-shadow .18s;
    }
    .calendar-control:hover { transform: translateY(-1px); border-color: #bbcaff; background: #f7f9ff; color: #315cff; box-shadow: 0 4px 10px #315cff12; }
    .calendar-control.today { border-color: #e5ebf8; color: #315cff; font-size: 12px; font-weight: 700; }
    .calendar-scroll { overflow-x: auto; border: 1px solid #cbd6ef; border-radius: 12px; background: #fff; box-shadow: 0 4px 12px rgba(55, 79, 143, .04); }
    .calendar-weekdays, .calendar-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); min-width: 700px; }
    .calendar-weekdays { background: linear-gradient(180deg, #f5f7fd, #eef2fa); }
    .calendar-weekdays span { padding: 11px 12px; border-right: 1px solid #d3dcf0; color: #7e8bac; font-size: 10px; font-weight: 800; letter-spacing: .08em; }
    .calendar-weekdays span:nth-last-child(-n+2) { color: #dc8589; }
    .calendar-day {
        min-width: 0;
        min-height: 113px;
        padding: 8px;
        border-top: 1px solid #d3dcf0;
        border-right: 1px solid #d3dcf0;
        background: #fff;
        transition: background .18s, box-shadow .18s;
    }
    .calendar-day:hover { position: relative; z-index: 1; background: #fbfcff; box-shadow: inset 0 0 0 1px #b7c8ff; }
    .calendar-day:nth-child(7n) { border-right: 0; }
    .calendar-day.outside-month { background: #fafbfe; }
    .calendar-day.outside-month .day-number { color: #c0c9dd; }
    .calendar-day.today { background: linear-gradient(145deg, #f3f6ff, #fff); }
    .day-number { display: inline-grid; min-width: 23px; height: 23px; place-items: center; margin: 0 0 4px; border-radius: 50%; color: #26366c; font-size: 11px; font-weight: 700; }
    button.day-number { padding: 0; border: 0; background: transparent; cursor: pointer; font-family: inherit; }
    button.day-number:focus-visible { outline: 2px solid #667b99; outline-offset: 2px; }
    .day-event-indicator { display: none; }
    .today .day-number { background: #315cff; color: #fff; box-shadow: 0 3px 8px #315cff40; }
    .agenda-chip {
        display: block;
        overflow: hidden;
        margin-top: 4px;
        padding: 5px 6px;
        border-left: 3px solid #315cff;
        border-radius: 6px;
        background: #edf2ff;
        color: #293965;
        text-decoration: none;
        cursor: pointer;
        transition: transform .16s, background .16s, box-shadow .16s;
    }
    .agenda-chip:hover { transform: translateX(2px); box-shadow: 0 3px 8px rgba(33, 56, 116, .12); }
    .agenda-time { display: block; overflow: hidden; font-size: 9px; font-weight: 800; line-height: 1.3; text-overflow: ellipsis; white-space: nowrap; }
    .agenda-caption { display: block; overflow: hidden; font-size: 9px; line-height: 1.3; text-overflow: ellipsis; white-space: nowrap; }
    .calendar-more { margin: 4px 0 0 3px; padding: 1px 0; border: 0; background: transparent; color: #7181a8; cursor: pointer; font: inherit; font-size: 9px; text-align: left; }
    .calendar-more:hover { color: #315cff; text-decoration: underline; }
    .agenda-dialog { width: min(460px, calc(100% - 32px)); max-height: min(82vh, 620px); overflow: auto; padding: 26px 28px 20px; border: 0; border-radius: 20px; background: #fff; color: #202d5b; box-shadow: 0 24px 80px rgba(25, 39, 85, .25); }
    .agenda-dialog::backdrop { background: rgba(42, 57, 101, .34); backdrop-filter: blur(4px); }
    .dialog-heading { display: flex; align-items: start; justify-content: space-between; gap: 12px; padding-bottom: 17px; border-bottom: 1px solid #e5eaf5; }
    .dialog-heading h2 { margin: 0; color: #26364a; font-family: Inter, system-ui, -apple-system, sans-serif; font-size: 20px; font-weight: 700; letter-spacing: -.3px; }
    .dialog-heading p { margin: 5px 0 0; color: #7c8aaf; font-size: 13px; }
    .dialog-close { flex: 0 0 34px; width: 34px; height: 34px; border: 0; border-radius: 50%; background: #eff3ff; color: #315cff; cursor: pointer; font-size: 20px; line-height: 1; transition: transform .18s, background .18s; }
    .dialog-close:hover { transform: rotate(90deg); background: #e2eaff; }
    .dialog-list { padding-top: 1px; }
    .dialog-event { display: grid; grid-template-columns: 78px minmax(0, 1fr); gap: 12px; padding: 14px 0; border-bottom: 1px solid #e5eaf5; }
    .dialog-event:last-child { border-bottom: 0; }
    .dialog-event-time { color: #315cff; font-size: 15px; font-weight: 800; }
    .dialog-event-end { display: block; margin-top: 2px; color: #8190b3; font-size: 12px; font-weight: 400; }
    .dialog-event-title { color: #283766; font-size: 14px; font-weight: 700; }
    .dialog-event-room { margin-top: 5px; color: #7a89ad; font-size: 13px; }

    /* =========================================================================
       TEMA WARNA RESMI (LIGHT THEME UTAMA APLIKASI)
       ========================================================================= */
    .calendar-page { color: #26364a; }
    .public-topbar { border-top-color: #35465e; border-bottom-color: #e8e1d3; background: #fffdf8; box-shadow: 0 8px 22px rgba(38, 54, 74, .08); }
    .public-brand, .public-brand:hover { color: #26364a; }
    .login-link { background: #35465e; color: #fffdf8; box-shadow: 0 5px 12px rgba(53, 70, 94, .14); }
    .login-link:hover { background: #465a73; color: #fffdf8; box-shadow: 0 8px 16px rgba(53, 70, 94, .18); }
    .calendar-page-heading h1, .month-title, .dialog-heading h2 { color: #26364a; }
    .calendar-page-heading p, .approved-count, .month-title span, .dialog-heading p, .dialog-event-room, .dialog-event-end { color: #6d7788; }
    .approved-count strong, .agenda-time, .dialog-event-time { color: #35465e; }
    .calendar-card, .agenda-dialog { border-color: #e8e1d3; background: #fffdf8; color: #26364a; box-shadow: 0 14px 34px rgba(38, 54, 74, .08); }
    .agenda-dialog::backdrop { background: rgba(38, 54, 74, .32); }
    .calendar-control { border-color: #d7deea; background: #fffdf8; color: #35465e; }
    .calendar-control:hover, .calendar-control.today { border-color: #bdc9da; background: #f1f3f6; color: #26364a; }
    .calendar-scroll { border-color: #e8e1d3; background: #fffdf8; box-shadow: 0 4px 12px rgba(38, 54, 74, .04); }
    .calendar-weekdays { background: #f1f3f6; }
    .calendar-weekdays span { border-right-color: #e8e1d3; color: #667386; }
    .calendar-day { border-color: #e8e1d3; background: #fffdf8; }
    .calendar-day:hover { background: #fbf9f2; box-shadow: inset 0 0 0 1px #bdc9da; }
    .calendar-day.outside-month { background: #f6f3eb; }
    .calendar-day.outside-month .day-number { color: #8993a2; }
    .calendar-day.today { background: #eef3fb; }
    .day-number { color: #26364a; }
    .today .day-number { background: #35465e; color: #fffdf8; box-shadow: 0 3px 8px rgba(53, 70, 94, .18); }
    .calendar-more { color: #667386; }
    .calendar-more:hover { color: #26364a; }
    .dialog-heading, .dialog-event { border-color: #e8e1d3; }
    .dialog-close { background: #f1f3f6; color: #35465e; }
    .dialog-close:hover { background: #e8edf4; }

    /* =========================================================================
       FITUR: PETUNJUK WARNA KALENDER (LEGEND BAR)
       ========================================================================= */
    .calendar-legend-bar {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
        margin-bottom: 16px;
        padding: 9px 16px;
        border: 1px solid #e8e1d3;
        border-radius: 12px;
        background: #fbf9f4;
    }
    .legend-item {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 12px;
        font-weight: 600;
        color: #35465e;
    }
    .legend-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
        flex-shrink: 0;
    }
    .dot-green { background: #16a34a; box-shadow: 0 0 0 2px #bbf7d0; }
    .dot-yellow { background: #d97706; box-shadow: 0 0 0 2px #fde68a; }
    .dot-red { background: #dc2626; box-shadow: 0 0 0 2px #fecaca; }

    /* =========================================================================
       FITUR: WARNA KOLOM HARI SABTU & MINGGU (WEEKEND) SEDIKIT BERBEDA
       ========================================================================= */
    .calendar-weekdays span.weekend-header {
        background: #e9eef7;
        color: #b91c1c;
        font-weight: 800;
    }
    .calendar-day.weekend {
        background: #f7f3e8; /* Warna kolom akhir pekan berbeda lembut dan rapi */
    }
    .calendar-day.weekend:hover {
        background: #f1ebd8;
        box-shadow: inset 0 0 0 1px #bdc9da;
    }
    .calendar-day.weekend.outside-month {
        background: #ede7da;
    }
    .calendar-day.weekend.today {
        background: #e7eef9;
    }

    /* =========================================================================
       FITUR: WARNA AGENDA GURU & ORGANISASI (HIJAU)
       ========================================================================= */
    .agenda-chip.chip-applicant {
        border-left: 3px solid #16a34a !important;
        background: #f0fdf4 !important;
        color: #14532d !important;
    }
    .agenda-chip.chip-applicant:hover {
        background: #dcfce7 !important;
        box-shadow: 0 3px 8px rgba(22, 163, 74, 0.16) !important;
    }
    .agenda-chip.chip-applicant .agenda-time {
        color: #15803d !important;
        font-weight: 800;
    }
    .agenda-chip.chip-applicant .agenda-caption {
        color: #166534 !important;
        font-weight: 600;
    }

    /* =========================================================================
       FITUR: WARNA AGENDA ADMIN (KUNING / AMBER)
       ========================================================================= */
    .agenda-chip.chip-admin {
        border-left: 3px solid #d97706 !important;
        background: #fffbeb !important;
        color: #78350f !important;
    }
    .agenda-chip.chip-admin:hover {
        background: #fef3c7 !important;
        box-shadow: 0 3px 8px rgba(217, 119, 6, 0.16) !important;
    }
    .agenda-chip.chip-admin .agenda-time {
        color: #b45309 !important;
        font-weight: 800;
    }
    .agenda-chip.chip-admin .agenda-caption {
        color: #92400e !important;
        font-weight: 600;
    }

    /* =========================================================================
       FITUR: KALENDER HARI LIBUR NASIONAL OTOMATIS (MERAH)
       ========================================================================= */
    .agenda-chip.chip-holiday {
        border-left: 3px solid #dc2626 !important;
        background: #fef2f2 !important;
        color: #991b1b !important;
    }
    .agenda-chip.chip-holiday:hover {
        background: #fee2e2 !important;
        box-shadow: 0 3px 8px rgba(220, 38, 38, 0.16) !important;
    }
    .agenda-chip.chip-holiday .holiday-tag {
        color: #dc2626 !important;
        font-size: 8.5px !important;
        font-weight: 800 !important;
        letter-spacing: .04em;
    }
    .agenda-chip.chip-holiday .holiday-caption {
        color: #991b1b !important;
        font-weight: 700 !important;
    }
    .calendar-day.has-holiday .holiday-number {
        color: #dc2626 !important;
        font-weight: 800 !important;
    }

    /* =========================================================================
       STYLING DIALOG DETAIL AGENDA & LIBUR
       ========================================================================= */
    .dialog-holiday-box {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
        padding: 12px 16px;
        border-radius: 12px;
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #991b1b;
    }
    .dialog-holiday-dot { font-size: 16px; line-height: 1; }
    .dialog-holiday-tag { font-size: 10px; font-weight: 800; color: #dc2626; letter-spacing: .06em; margin-bottom: 2px; }
    .dialog-holiday-name { font-size: 14px; font-weight: 700; color: #7f1d1d; }

    .dialog-event.chip-applicant {
        border-left: 3px solid #16a34a;
        padding-left: 12px;
    }
    .dialog-event.chip-applicant .dialog-event-time { color: #16a34a !important; }

    .dialog-event.chip-admin {
        border-left: 3px solid #d97706;
        padding-left: 12px;
    }
    .dialog-event.chip-admin .dialog-event-time { color: #d97706 !important; }

    .role-pill {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 99px;
        font-size: 11px;
        font-weight: 700;
    }
    .role-pill.chip-applicant {
        background: #dcfce7;
        color: #15803d;
        border: 1px solid #bbf7d0;
    }
    .role-pill.chip-admin {
        background: #fef3c7;
        color: #b45309;
        border: 1px solid #fde68a;
    }

    /* =========================================================================
       RESPONSIVITAS MOBILE
       ========================================================================= */
    @media (max-width: 760px) {
        .public-wrap { width: calc(100% - 24px); padding-top: 20px; }
        .calendar-page-heading h1 { font-size: 24px; }
        .calendar-page-heading p { font-size: 14px; }
        .calendar-card { padding: 12px; }
        .calendar-toolbar {
            align-items: center;
            flex-direction: row;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 12px;
        }
        .calendar-legend-bar {
            gap: 10px;
            padding: 7px 12px;
            font-size: 11px;
        }
        .month-title { font-size: clamp(16px, 5vw, 19px); white-space: nowrap; }
        .calendar-controls { align-self: auto; gap: 5px; margin-top: 0; }
        .calendar-control { min-width: 34px; height: 34px; padding: 0 8px; }
        .calendar-control.today { font-size: 11px; }
        .calendar-scroll { overflow: hidden; }
        .calendar-weekdays, .calendar-grid { min-width: 0; grid-template-columns: repeat(7, minmax(0, 1fr)); }
        .calendar-weekdays span { padding: 9px 1px; font-size: 9px; letter-spacing: 0; text-align: center; }
        .calendar-day {
            display: flex;
            min-height: clamp(54px, 17vw, 72px);
            flex-direction: column;
            align-items: center;
            padding: 5px 1px;
        }
        .day-number { min-width: 21px; height: 21px; margin-bottom: 0; }
        .day-event-indicator { display: block; width: 5px; height: 5px; margin-top: 4px; border-radius: 50%; background: #667b99; }
        .agenda-chip, .calendar-more { display: none; }
    }
</style>
