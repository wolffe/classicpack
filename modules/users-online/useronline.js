document.addEventListener('DOMContentLoaded', function () {
    var ajaxUrl = useronlineL10n.ajax_url;
    var postOpts = { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' } };

    // ── Heartbeat ──────────────────────────────────────────────────────────────
    // Re-records the current visitor every 5 minutes so long-running sessions
    // are not pruned from the online list before they navigate away.
    function sendHeartbeat() {
        fetch(ajaxUrl, Object.assign({}, postOpts, {
            body: new URLSearchParams({
                action: 'classicpress_useronline',
                nonce: useronlineL10n.nonce,
                mode: 'heartbeat',
                page_url: location.protocol + '//' + location.host + location.pathname + location.search,
                page_title: document.title
            }).toString()
        }));
    }

    sendHeartbeat();
    setInterval(sendHeartbeat, 300000);

    // ── Dashboard widget refresh ───────────────────────────────────────────────
    // Refreshes the widget content every 30 seconds.
    var widget = document.getElementById('useronline-details');
    if (widget) {
        function refreshWidget() {
            fetch(ajaxUrl, Object.assign({}, postOpts, {
                body: new URLSearchParams({
                    action: 'classicpress_useronline',
                    nonce: useronlineL10n.nonce,
                    mode: 'details'
                }).toString()
            }))
                .then(function (r) { return r.text(); })
                .then(function (html) { widget.innerHTML = html; });
        }

        setInterval(refreshWidget, 30000);
    }
});
