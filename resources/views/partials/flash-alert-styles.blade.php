        /* =========================================================================
           FLASH ALERT / BANNER NOTIFIKASI STATUS MODERN
           ========================================================================= */
        .flash-alert {
            position: relative;
            display: flex;
            align-items: flex-start;
            gap: 13px;
            margin: 0 0 18px;
            padding: 14px 16px;
            border: 1px solid #d7deea;
            border-left-width: 4px;
            border-radius: 12px;
            background: #fffdf8;
            color: #26364a;
            box-shadow: 0 8px 22px rgba(38, 54, 74, .07);
            overflow: hidden;
            animation: flashSlideIn .28s cubic-bezier(.16, 1, .3, 1);
            transition: opacity .24s ease, transform .24s ease, margin .24s ease, padding .24s ease, max-height .24s ease;
        }
        .flash-alert.is-closing {
            opacity: 0;
            transform: translateY(-6px);
            pointer-events: none;
        }
        .flash-alert--compact {
            margin: 14px 0 0;
            padding: 11px 13px;
            gap: 10px;
            border-radius: 10px;
            box-shadow: 0 4px 14px rgba(38, 54, 74, .06);
        }
        .flash-alert--success {
            border-color: #bbf7d0;
            border-left-color: #16a34a;
            background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%);
        }
        .flash-alert--error {
            border-color: #fecaca;
            border-left-color: #dc2626;
            background: linear-gradient(135deg, #fef2f2 0%, #ffffff 100%);
        }
        .flash-alert--warning {
            border-color: #fde68a;
            border-left-color: #d97706;
            background: linear-gradient(135deg, #fffbeb 0%, #ffffff 100%);
        }
        .flash-alert--info {
            border-color: #bfdbfe;
            border-left-color: #2563eb;
            background: linear-gradient(135deg, #eff6ff 0%, #ffffff 100%);
        }
        .flash-alert-icon {
            display: grid;
            flex: 0 0 34px;
            width: 34px;
            height: 34px;
            place-items: center;
            border-radius: 10px;
        }
        .flash-alert--compact .flash-alert-icon {
            flex-basis: 28px;
            width: 28px;
            height: 28px;
            border-radius: 8px;
        }
        .flash-alert-icon svg {
            width: 18px;
            height: 18px;
            fill: none;
            stroke: currentColor;
            stroke-width: 2.3;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        .flash-alert--compact .flash-alert-icon svg {
            width: 15px;
            height: 15px;
        }
        .flash-alert--success .flash-alert-icon { background: #dcfce7; color: #15803d; }
        .flash-alert--error .flash-alert-icon { background: #fee2e2; color: #b91c1c; }
        .flash-alert--warning .flash-alert-icon { background: #fef3c7; color: #b45309; }
        .flash-alert--info .flash-alert-icon { background: #dbeafe; color: #1d4ed8; }
        .flash-alert-body {
            flex: 1 1 auto;
            min-width: 0;
            padding-top: 1px;
        }
        .flash-alert-title {
            display: block;
            margin: 0 0 2px;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: .01em;
        }
        .flash-alert--compact .flash-alert-title {
            font-size: 12px;
        }
        .flash-alert--success .flash-alert-title { color: #166534; }
        .flash-alert--error .flash-alert-title { color: #991b1b; }
        .flash-alert--warning .flash-alert-title { color: #92400e; }
        .flash-alert--info .flash-alert-title { color: #1e40af; }
        .flash-alert-message {
            margin: 0;
            color: #26364a;
            font-size: 13.5px;
            font-weight: 500;
            line-height: 1.5;
            word-break: break-word;
        }
        .flash-alert--compact .flash-alert-message {
            font-size: 12.5px;
        }
        .flash-alert-list {
            margin: 6px 0 0;
            padding-left: 18px;
            color: #7f1d1d;
            font-size: 13px;
            line-height: 1.55;
        }
        .flash-alert-close {
            flex: 0 0 auto;
            display: grid;
            width: 28px;
            min-width: 28px;
            height: 28px;
            min-height: 28px;
            place-items: center;
            margin: -2px -4px 0 0;
            padding: 0;
            border: 0;
            border-radius: 8px;
            background: transparent;
            color: #64748b;
            font-size: 18px;
            font-weight: 600;
            line-height: 1;
            cursor: pointer;
            transition: background .16s ease, color .16s ease, transform .16s ease;
        }
        .flash-alert-close:hover {
            background: rgba(38, 54, 74, .08);
            color: #1e293b;
            box-shadow: none;
            transform: none;
        }
        .flash-alert-progress {
            position: absolute;
            right: 0;
            bottom: 0;
            left: 0;
            height: 2.5px;
            transform-origin: left center;
            animation: flashProgress 7.5s linear forwards;
        }
        .flash-alert--success .flash-alert-progress { background: rgba(22, 163, 74, .45); }
        .flash-alert--info .flash-alert-progress { background: rgba(37, 99, 235, .45); }
        .flash-alert:hover .flash-alert-progress {
            animation-play-state: paused;
        }
        @keyframes flashSlideIn {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes flashProgress {
            from { transform: scaleX(1); }
            to { transform: scaleX(0); }
        }
        @media (max-width: 640px) {
            .flash-alert { padding: 12px 13px; gap: 10px; }
            .flash-alert-icon { flex-basis: 30px; width: 30px; height: 30px; }
        }

