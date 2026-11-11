    <script>
        (function () {
            function dismissFlashAlert(alertEl) {
                if (!alertEl || alertEl.classList.contains('is-closing')) return;
                alertEl.classList.add('is-closing');
                setTimeout(function () {
                    if (alertEl.parentNode) alertEl.remove();
                }, 240);
            }

            document.querySelectorAll('[data-flash-alert]').forEach(function (alertEl) {
                var closeBtn = alertEl.querySelector('[data-flash-close]');
                if (closeBtn) {
                    closeBtn.addEventListener('click', function () {
                        dismissFlashAlert(alertEl);
                    });
                }

                var autoMs = Number(alertEl.getAttribute('data-flash-autoclose') || '0');
                if (autoMs > 0) {
                    var remaining = autoMs;
                    var startedAt = Date.now();
                    var timer = setTimeout(function () {
                        dismissFlashAlert(alertEl);
                    }, remaining);

                    alertEl.addEventListener('mouseenter', function () {
                        clearTimeout(timer);
                        remaining = Math.max(0, remaining - (Date.now() - startedAt));
                    });

                    alertEl.addEventListener('mouseleave', function () {
                        if (remaining <= 0 || alertEl.classList.contains('is-closing')) return;
                        startedAt = Date.now();
                        timer = setTimeout(function () {
                            dismissFlashAlert(alertEl);
                        }, remaining);
                    });
                }
            });
        })();
    </script>

