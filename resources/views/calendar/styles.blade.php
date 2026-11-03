<style>
    :root {
        color: #202d5b;
        background: #f4f7fc;
        font-family: Arial, sans-serif;
        font-synthesis: none;
    }

    * { box-sizing: border-box; }
    body { margin: 0; }
    .public-calendar { min-height: 100vh; background: #f4f7fc; }
    .public-topbar {
        height: 68px;
        padding: 0 max(28px, calc((100% - 1200px) / 2));
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-top: 4px solid #315cff;
        border-bottom: 1px solid #cbd6e8;
        background: #e9eff8;
        box-shadow: 0 8px 22px rgba(33, 56, 116, .16);
    }
    .public-brand { display: inline-flex; align-items: center; gap: 8px; color: #172033; font-family: Georgia, serif; font-size: 21px; font-weight: 700; text-decoration: none; }
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
    .calendar-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 22px; }
    .month-title { margin: 0; color: #27366d; font-family: Georgia, serif; font-size: 24px; font-weight: 500; }
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
    .today .day-number { background: #315cff; color: #fff; box-shadow: 0 3px 8px #315cff40; }
    .agenda-chip {
        display: block;
        overflow: hidden;
        margin-top: 4px;
        padding: 5px 6px;
        border-left: 2px solid #315cff;
        border-radius: 5px;
        background: linear-gradient(105deg, #edf2ff, #f2f5ff);
        color: #293965;
        text-decoration: none;
        transition: transform .16s, background .16s, box-shadow .16s;
    }
    .agenda-chip:hover { transform: translateX(2px); background: #e5edff; box-shadow: 0 3px 8px #315cff19; }
    .agenda-time { display: block; overflow: hidden; color: #315cff; font-size: 9px; font-weight: 800; line-height: 1.3; text-overflow: ellipsis; white-space: nowrap; }
    .agenda-caption { display: block; overflow: hidden; color: #39496f; font-size: 9px; line-height: 1.3; text-overflow: ellipsis; white-space: nowrap; }
    .calendar-more { margin: 4px 0 0 3px; padding: 1px 0; border: 0; background: transparent; color: #7181a8; cursor: pointer; font: inherit; font-size: 9px; text-align: left; }
    .calendar-more:hover { color: #315cff; text-decoration: underline; }
    .agenda-dialog { width: min(440px, calc(100% - 32px)); max-height: min(82vh, 620px); overflow: auto; padding: 26px 28px 20px; border: 0; border-radius: 20px; background: #fff; color: #202d5b; box-shadow: 0 24px 80px rgba(25, 39, 85, .25); }
    .agenda-dialog::backdrop { background: rgba(42, 57, 101, .34); backdrop-filter: blur(4px); }
    .dialog-heading { display: flex; align-items: start; justify-content: space-between; gap: 12px; padding-bottom: 17px; border-bottom: 1px solid #e5eaf5; }
    .dialog-heading h2 { margin: 0; color: #27366d; font-family: Georgia, serif; font-size: 22px; font-weight: 500; }
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
    .public-calendar { color: #e5eaff; background: #0b1028; }
    .public-topbar { border-bottom-color: #293568; background: #151d49; box-shadow: 0 8px 24px rgba(3, 7, 24, .3); }
    .public-brand { color: #f4f6ff; }
    .public-brand:hover { color: #fff; }
    .login-link { background: #6388ff; color: #091438; }
    .login-link:hover { background: #7898ff; color: #081231; }
    .calendar-page { color: #e5eaff; }
    .calendar-page-heading h1 { color: #e5eaff; }
    .calendar-page-heading p, .approved-count { color: #93a2ce; }
    .approved-count strong { color: #83a3ff; }
    .calendar-card { border-color: #29366f; background: linear-gradient(145deg, #171f4b, #141c43); box-shadow: 0 14px 34px rgba(3, 7, 24, .24), 0 2px 7px rgba(3, 7, 24, .12); }
    .month-title, .dialog-heading h2 { color: #e5eaff; }
    .month-title span { color: #93a2ce; }
    .calendar-control { border-color: #354477; background: #10183b; color: #dce4ff; }
    .calendar-control:hover { border-color: #718cff; background: #202b5a; color: #fff; }
    .calendar-control.today { color: #a9beff; }
    .calendar-scroll { border-color: #344477; background: #11183a; box-shadow: 0 4px 12px rgba(3, 7, 24, .15); }
    .calendar-weekdays { background: #202957; }
    .calendar-weekdays span { border-right-color: #344477; color: #aab7dc; }
    .calendar-weekdays span:nth-last-child(-n+2) { color: #ff999e; }
    .calendar-day { border-color: #344477; background: #151d45; }
    .calendar-day:hover { background: #1b2556; box-shadow: inset 0 0 0 1px #637fdb; }
    .calendar-day.outside-month { background: #101735; }
    .calendar-day.outside-month .day-number { color: #62709b; }
    .calendar-day.today { background: linear-gradient(145deg, #202d65, #171f4b); }
    .day-number { color: #e5eaff; }
    .today .day-number { background: #6388ff; color: #091438; box-shadow: 0 3px 8px rgba(99, 136, 255, .3); }
    .agenda-chip { border-left-color: #7192ff; background: linear-gradient(105deg, #26366f, #202d5d); color: #e5eaff; }
    .agenda-chip:hover { background: #30427f; box-shadow: 0 3px 8px rgba(3, 7, 24, .2); }
    .agenda-time { color: #9cb5ff; }
    .agenda-caption { color: #d4ddff; }
    .calendar-more { color: #a8b8e3; }
    .calendar-more:hover { color: #c5d2ff; }
    .agenda-dialog { border: 1px solid #354477; background: #171f4b; color: #e5eaff; box-shadow: 0 24px 80px rgba(3, 7, 24, .55); }
    .agenda-dialog::backdrop { background: rgba(4, 8, 28, .68); }
    .dialog-heading { border-bottom-color: #354477; }
    .dialog-heading p, .dialog-event-room { color: #93a2ce; }
    .dialog-close { background: #26366f; color: #b6c8ff; }
    .dialog-close:hover { background: #314782; }
    .dialog-event { border-bottom-color: #354477; }
    .dialog-event-time { color: #9cb5ff; }
    .dialog-event-end { color: #93a2ce; }
    .dialog-event-title { color: #e5eaff; }
    @media (max-width: 760px) {
        .public-topbar { padding: 0 20px; }
        .public-wrap { width: min(100% - 28px, 1200px); padding-top: 25px; }
        .calendar-page-heading { align-items: start; flex-direction: column; margin-bottom: 18px; }
        .approved-count { padding: 0; }
        .calendar-card { padding: 15px; border-radius: 16px; }
        .calendar-toolbar { align-items: start; flex-direction: column; gap: 12px; margin-bottom: 15px; }
        .month-title { font-size: 22px; }
        .calendar-controls { align-self: end; margin-top: -47px; }
        .calendar-day { min-height: 96px; padding: 6px; }
        .agenda-dialog { padding: 22px 20px 16px; }
    }
    :root { color-scheme: light; color: #26364a; background: #f7f4eb; }
    .public-calendar, .calendar-page { color: #26364a; background: #f7f4eb; }
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
    .calendar-weekdays span:nth-last-child(-n+2) { color: #667386; }
    .calendar-day { border-color: #e8e1d3; background: #fffdf8; }
    .calendar-day:hover { background: #f7f4eb; box-shadow: inset 0 0 0 1px #bdc9da; }
    .calendar-day.outside-month { background: #f7f4eb; }
    .calendar-day.outside-month .day-number { color: #8993a2; }
    .calendar-day.today { background: #f1f3f6; }
    .day-number { color: #26364a; }
    .today .day-number { background: #35465e; color: #fffdf8; box-shadow: 0 3px 8px rgba(53, 70, 94, .18); }
    .agenda-chip { border-left-color: #667b99; background: #f1f3f6; color: #26364a; }
    .agenda-chip:hover { background: #e8edf4; box-shadow: 0 3px 8px rgba(38, 54, 74, .08); }
    .agenda-caption, .dialog-event-title { color: #35465e; }
    .calendar-more { color: #667386; }
    .calendar-more:hover { color: #26364a; }
    .dialog-heading, .dialog-event { border-color: #e8e1d3; }
    .dialog-close { background: #f1f3f6; color: #35465e; }
    .dialog-close:hover { background: #e8edf4; }
</style>
