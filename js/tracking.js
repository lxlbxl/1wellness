/**
 * Centralized Tracker for PCOS Funnel
 * 
 * This script is responsible for injecting tracking codes (GTM, Pixels, etc.)
 * into the <head> and <body> of the page.
 * 
 * Usage:
 * Simply include this script in the <head> of your HTML file:
 * <script src="../js/tracking.js"></script>
 */

(function () {
    'use strict';

    // --- CONFIGURATION ---
    const CONFIG = {
        GTM_ID: 'GTM-5Q5HPMZ2' // Google Tag Manager ID
    };

    /**
     * Helper to create and append script tags
     */
    function injectScript(content, location = 'head', isExternal = false, src = '') {
        const script = document.createElement('script');
        if (isExternal) {
            script.src = src;
            script.async = true;
        } else {
            script.innerHTML = content;
        }

        if (location === 'head') {
            document.head.appendChild(script);
        } else {
            document.body.appendChild(script);
        }
    }

    /**
     * Helper to create and append HTML strings (for noscript etc)
     */
    function injectHTML(htmlString, location = 'body') {
        const div = document.createElement('div');
        div.innerHTML = htmlString;

        // We use the first child because we want the actual element, not the wrapper div
        // usually. But for noscript/iframe it's tricky since they don't render inside div well if script disabled.
        // However, since we are Running JS, noscript tags strictly won't trigger for purely JS disabled users.
        // But we inject them for completeness or if they contain iframes that might load.
        // Note: Dynamically inserting <noscript> via JS is mostly redundant because if JS is off, this file won't run.
        // But for GTM, the <iframe> inside noscript is sometimes used for verification or other non-js tracking quirks.

        while (div.firstChild) {
            if (location === 'head') {
                document.head.appendChild(div.firstChild);
            } else {
                // Prepend to body to be "immediately after opening body tag" as requested
                document.body.insertBefore(div.firstChild, document.body.firstChild);
            }
        }
    }


    // --- IMPLEMENTATIONS ---

    // 1. Google Tag Manager (HEAD)
    const gtmHeadScript = `
        (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
         new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
         j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
         'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
         })(window,document,'script','dataLayer','${CONFIG.GTM_ID}');
    `;
    // We execute this directly or inject it? GTM code is self-executing.
    // However, since we are INSIDE a script, we can just run the function logic or inject a new script tag.
    // Injecting a new script tag is safer to ensure it runs in global scope exactly as GTM expects.
    injectScript(gtmHeadScript, 'head');


    // 2. Google Tag Manager (BODY - NOSCRIPT)
    // Note: As mentioned, this won't run if JS is disabled (catch-22), but we add it for DOM completeness.
    const gtmBodyContent = `
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=${CONFIG.GTM_ID}"
        height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    `;
    // Wait for body to be ready incase this script is in head
    document.addEventListener('DOMContentLoaded', function () {
        injectHTML(gtmBodyContent, 'body');
    });

    // --- FUTURE TRACKING CODES ---

    // Facebook Pixel
    const fbPixelCode = `
    !function(f,b,e,v,n,t,s)
    {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};
    if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
    n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];
    s.parentNode.insertBefore(t,s)}(window, document,'script',
    'https://connect.facebook.net/en_US/fbevents.js');
    fbq('init', '2722720648064540');
    fbq('track', 'PageView');
    `;
    injectScript(fbPixelCode, 'head');

    const fbNoScript = `
    <noscript><img height="1" width="1" style="display:none"
    src="https://www.facebook.com/tr?id=2722720648064540&ev=PageView&noscript=1"
    /></noscript>
    `;
    document.addEventListener('DOMContentLoaded', function () {
        injectHTML(fbNoScript, 'body');
    });

    // --- META PIXEL CONVERSION HELPER ---
    // Expose global helper for pages to fire conversion events
    window.WellnessTracking = {
        /**
         * Fire a Meta Pixel standard event
         * @param {string} eventName - e.g. 'Purchase', 'Lead', 'InitiateCheckout', 'ViewContent'
         * @param {object} params - event parameters
         */
        trackEvent: function(eventName, params) {
            try {
                if (typeof fbq === 'function') {
                    fbq('track', eventName, params || {});
                    console.log('[WellnessTracking] Meta Pixel event:', eventName, params);
                }
            } catch(e) {
                console.warn('[WellnessTracking] Pixel error:', e);
            }
        },

        /** Track Purchase conversion */
        trackPurchase: function(amount, currency, planName) {
            this.trackEvent('Purchase', {
                value: parseFloat(amount) || 0,
                currency: currency || 'USD',
                content_name: planName || 'PCOS Plan',
                content_type: 'product',
                content_category: 'health_plan'
            });
        },

        /** Track Lead (assessment completion) */
        trackLead: function(assessmentType, pcosType) {
            this.trackEvent('Lead', {
                content_name: (assessmentType || 'PCOS') + ' Assessment',
                content_category: assessmentType || 'pcos',
                content_type: pcosType || 'general'
            });
        },

        /** Track when user views a sales/product page */
        trackViewContent: function(planName, price, currency) {
            this.trackEvent('ViewContent', {
                content_name: planName || 'PCOS Plan',
                content_type: 'product',
                value: parseFloat(price) || 0,
                currency: currency || 'USD'
            });
        },

        /** Track when user clicks "Buy Now" / starts checkout */
        trackInitiateCheckout: function(planName, price, currency) {
            this.trackEvent('InitiateCheckout', {
                content_name: planName || 'PCOS Plan',
                value: parseFloat(price) || 0,
                currency: currency || 'USD',
                num_items: 1
            });
        },

        /** Track assessment form completion (registration) */
        trackCompleteRegistration: function(type) {
            this.trackEvent('CompleteRegistration', {
                content_name: (type || 'PCOS') + ' Assessment',
                status: 'completed'
            });
        }
    };

})();

/**
 * ============================================================
 * 1wellness A/B Engine client module
 * ============================================================
 * 1. Variant override applier — applies window.__VARIANT_OVERRIDES
 *    (injected by backend/router.php) and releases the anti-flicker
 *    guard.
 * 2. Funnel event emitter — posts the fixed event taxonomy to
 *    /backend/api/track-event.php, gated on GDPR analytics consent.
 *    Attribution to experiments/variants happens server-side via the
 *    session's sticky assignments, so inner funnel pages need no
 *    injected context.
 *
 * Public API:
 *   window.AB.track(event, metadata)   // e.g. AB.track('checkout_init')
 *   window.AB.sessionId
 */
(function () {
    'use strict';

    var FUNNELS = ['pcos', 'acne', 'weight', 'mens'];
    // funnel -> CONFIG.pricing key (for config-based price tests)
    var PRICING_KEYS = {
        pcos: 'pcos-90-day-plan',
        acne: 'acne-treatment-plan',
        weight: 'weight-loss-plan',
        mens: 'mens-vitality-plan'
    };

    function detectFunnel() {
        var path = window.location.pathname.toLowerCase();
        for (var i = 0; i < FUNNELS.length; i++) {
            if (path.indexOf('/' + FUNNELS[i] + '/') !== -1 ||
                path.indexOf('/' + FUNNELS[i] + '__') !== -1) {
                return FUNNELS[i];
            }
        }
        return null;
    }

    function getCookie(name) {
        var value = '; ' + document.cookie;
        var parts = value.split('; ' + name + '=');
        if (parts.length === 2) return parts.pop().split(';').shift();
        return null;
    }

    /**
     * Unified session id. Priority: router cookie (1w_sid) ->
     * WebhookManager localStorage id (1w_session_id) -> generate.
     * Both stores are synced so server + checkout share one id.
     */
    function resolveSessionId() {
        var sid = getCookie('1w_sid');
        var ls = null;
        try { ls = localStorage.getItem('1w_session_id'); } catch (e) {}

        if (!sid && ls) sid = ls;
        if (!sid) {
            sid = '1w_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now().toString(36);
        }
        try { localStorage.setItem('1w_session_id', sid); } catch (e) {}
        // Host-only fallback cookie so the server sees the same id even
        // when the page wasn't served through the router.
        if (!getCookie('1w_sid')) {
            document.cookie = '1w_sid=' + sid + ';path=/;max-age=7776000;SameSite=Lax';
        }
        return sid;
    }

    /**
     * GDPR gate for analytics event logging. The assignment cookie is
     * functional and exempt; event logging is not.
     *  - consent cookie present  -> honor analytics flag
     *  - banner loaded, no decision yet -> queue (flushed on consent)
     *  - no GDPR framework on page -> allow
     */
    function consentState() {
        var raw = getCookie('gdpr_cookie_consent');
        if (raw) {
            try {
                var state = JSON.parse(decodeURIComponent(raw));
                return state.analytics ? 'granted' : 'denied';
            } catch (e) { /* fallthrough */ }
        }
        return window.GDPRConsent ? 'pending' : 'granted';
    }

    var sessionId = resolveSessionId();
    var funnel = detectFunnel();
    var pendingQueue = [];
    var sentOnce = {}; // client-side dedup per pageload

    function apiBase() {
        // Pages live one level deep (/pcos/...), root pages use ./backend
        var path = window.location.pathname;
        var inFunnel = /\/[a-z]+(__[a-z0-9-]+)?\//i.test(path);
        return (inFunnel ? '../' : './') + 'backend/api';
    }

    function send(event, metadata) {
        var payload = {
            session_id: sessionId,
            funnel: funnel,
            event: event,
            url: window.location.pathname,
            metadata: metadata || {}
        };
        var body = JSON.stringify(payload);
        var url = apiBase() + '/track-event.php';
        // sendBeacon survives page unloads (plan_select / checkout_init)
        if (navigator.sendBeacon) {
            try {
                var blob = new Blob([body], { type: 'application/json' });
                if (navigator.sendBeacon(url, blob)) return;
            } catch (e) { /* fall back to fetch */ }
        }
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: body,
            keepalive: true
        }).catch(function () { /* tracking must never break the funnel */ });
    }

    function track(event, metadata) {
        if (!funnel) return;
        var dedupKey = event + ':' + window.location.pathname;
        if (sentOnce[dedupKey]) return;
        sentOnce[dedupKey] = true;

        var consent = consentState();
        if (consent === 'denied') return;
        if (consent === 'pending') {
            pendingQueue.push([event, metadata]);
            return;
        }
        send(event, metadata);
    }

    // Flush queued events if the visitor accepts analytics cookies later
    var flushTimer = setInterval(function () {
        if (!pendingQueue.length) return;
        var consent = consentState();
        if (consent === 'granted') {
            var q = pendingQueue.splice(0);
            q.forEach(function (item) { send(item[0], item[1]); });
        } else if (consent === 'denied') {
            pendingQueue.length = 0;
        }
    }, 2000);
    setTimeout(function () { clearInterval(flushTimer); }, 120000);

    // ------------------------------------------------------------------
    // Variant override applier (+ anti-flicker release)
    // ------------------------------------------------------------------
    function applyOverrides() {
        var ov = window.__VARIANT_OVERRIDES || {};
        try {
            var sel;
            if (ov.text) {
                for (sel in ov.text) {
                    document.querySelectorAll(sel).forEach(function (el) { el.textContent = ov.text[sel]; });
                }
            }
            if (ov.html) {
                for (sel in ov.html) {
                    document.querySelectorAll(sel).forEach(function (el) { el.innerHTML = ov.html[sel]; });
                }
            }
            if (ov.attr) {
                for (sel in ov.attr) {
                    document.querySelectorAll(sel).forEach(function (el) {
                        for (var attr in ov.attr[sel]) el.setAttribute(attr, ov.attr[sel][attr]);
                    });
                }
            }
            if (ov.style) {
                for (sel in ov.style) {
                    document.querySelectorAll(sel).forEach(function (el) {
                        for (var prop in ov.style[sel]) el.style[prop] = ov.style[sel][prop];
                    });
                }
            }
            if (ov.config && window.CONFIG && window.CONFIG.pricing) {
                // Price tests: merge config overrides into the funnel's
                // pricing entry before DataManager renders.
                var key = PRICING_KEYS[funnel];
                if (key && window.CONFIG.pricing[key]) {
                    for (var k in ov.config) window.CONFIG.pricing[key][k] = ov.config[k];
                }
                window.__AB_CONFIG = ov.config;
            }
        } catch (e) {
            console.warn('[AB] override apply error:', e);
        } finally {
            if (typeof window.__abReveal === 'function') window.__abReveal();
        }
    }

    // ------------------------------------------------------------------
    // Auto-wired funnel events (fixed taxonomy)
    // ------------------------------------------------------------------
    function autoWire() {
        var path = window.location.pathname.toLowerCase();

        // view — every funnel page load (server dedupes per session/page/day)
        track('view');

        if (path.indexOf('assessment') !== -1) {
            var started = false;
            var markStart = function () {
                if (started) return;
                started = true;
                track('assessment_start');
            };
            document.addEventListener('change', markStart, { once: false, capture: true });
            document.addEventListener('click', function (e) {
                var t = e.target;
                if (t && t.closest && t.closest('button, [type="radio"], [type="checkbox"], .option, [data-answer]')) {
                    markStart();
                }
            }, true);
            document.addEventListener('submit', function () {
                track('assessment_complete');
            }, true);
        }

        if (path.indexOf('results') !== -1) {
            track('results_view');
        }

        if (path.indexOf('select-plan') !== -1 || path.indexOf('sales') !== -1 ||
            path.indexOf('30-day-plan') !== -1 || path.indexOf('90-day-plan') !== -1 ||
            path.indexOf('digital-plan') !== -1) {
            document.addEventListener('click', function (e) {
                var t = e.target;
                if (!t || !t.closest) return;
                var hit = t.closest('[data-plan], .plan-card, .plan-select, a[href*="plan"], button');
                if (hit) {
                    track('plan_select', { label: (hit.textContent || '').trim().substring(0, 80) });
                }
            }, true);
            // checkout_init: primary call is in flutterwave-integration.js immediately before
            // FlutterwaveCheckout(). This form-submit fallback covers structural A/B variants
            // where flutterwave-integration.js may be replaced; track() dedup prevents
            // double-firing when both paths are active (Alpine emits a real submit event).
            document.addEventListener('submit', function () {
                track('checkout_init');
            }, { once: true, capture: true });
        }
    }

    // Public API
    window.AB = {
        sessionId: sessionId,
        funnel: funnel,
        track: track,
        applyOverrides: applyOverrides
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            applyOverrides();
            if (funnel) autoWire();
        });
    } else {
        applyOverrides();
        if (funnel) autoWire();
    }
})();
