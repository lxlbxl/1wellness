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
