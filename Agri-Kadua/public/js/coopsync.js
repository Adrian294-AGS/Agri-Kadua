/*
 * Co-opSync connection monitor (NFR-03 / NFR-06).
 * Checks the server every 10 seconds, shows the connection banner and blocks
 * submissions while offline so records are never assumed to be saved.
 */
(function () {
    var loader = document.querySelector('.page-loader');

    function hideLoader() {
        if (!loader) {
            return;
        }

        loader.classList.add('is-hidden');
        window.setTimeout(function () {
            loader.hidden = true;
        }, 220);
    }

    function showLoader() {
        if (loader) {
            loader.hidden = false;
            loader.classList.remove('is-hidden');
        }
    }

    window.addEventListener('pageshow', function () {
        if (!document.body.classList.contains('login-page')) {
            hideLoader();
        }
    });
    document.addEventListener('click', function (event) {
        var link = event.target.closest('a');

        if (link && link.origin === window.location.origin && link.target !== '_blank' && !link.hasAttribute('download')) {
            showLoader();
        }
    });
    document.addEventListener('submit', showLoader);
    if (document.body.classList.contains('login-page')) {
        window.setTimeout(hideLoader, 850);
    } else {
        hideLoader();
    }

    var banner = document.getElementById('connection-banner');
    var pill = document.getElementById('live-status');
    var online = true;

    function apply(state) {
        online = state;

        if (banner) {
            banner.hidden = state;
        }

        if (pill) {
            pill.innerHTML = state ? '&#9679; Live &middot; Online' : '&#9679; Offline';
            pill.className = 'pill ' + (state ? 'pill-online' : 'pill-offline');
        }

        document.querySelectorAll('[data-requires-online]').forEach(function (element) {
            element.disabled = !state;
        });
    }

    function check() {
        fetch('/up', { cache: 'no-store' })
            .then(function (response) { apply(response.ok); })
            .catch(function () { apply(false); });
    }

    window.addEventListener('online', check);
    window.addEventListener('offline', function () { apply(false); });

    document.addEventListener('submit', function (event) {
        if (!online) {
            event.preventDefault();
        }
    });

    setInterval(check, 10000);
    check();

    window.CoopSync = {
        isOnline: function () { return online; }
    };
})();