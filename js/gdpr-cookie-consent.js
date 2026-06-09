/**
 * GDPR Cookie Consent Banner
 * Handles cookie consent management in compliance with GDPR
 */

(function () {
    'use strict';

    // Cookie consent configuration
    const CONFIG = {
        consentCookieName: 'gdpr_cookie_consent',
        cookieExpiryDays: 365,
        consentVersion: '1.0',
        essentialCookies: ['session', 'csrf', 'consent'],
        analyticsCookies: ['_ga', '_gid', '_gat'],
        functionalCookies: ['language', 'currency', 'preferences']
    };

    // Consent state
    let consentState = {
        essential: true,
        analytics: false,
        functional: false,
        marketing: false,
        timestamp: null,
        version: CONFIG.consentVersion
    };

    /**
     * Check if consent has been given
     */
    function hasConsent() {
        const saved = getCookie(CONFIG.consentCookieName);
        if (saved) {
            try {
                consentState = JSON.parse(saved);
                return true;
            } catch (e) {
                return false;
            }
        }
        return false;
    }

    /**
     * Get cookie value by name
     */
    function getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return null;
    }

    /**
     * Set cookie with expiry
     */
    function setCookie(name, value, days) {
        const expires = new Date();
        expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
        document.cookie = `${name}=${value};expires=${expires.toUTCString()};path=/;SameSite=Lax`;
    }

    /**
     * Delete cookie
     */
    function deleteCookie(name) {
        document.cookie = `${name}=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/`;
    }

    /**
     * Save consent state
     */
    function saveConsent() {
        consentState.timestamp = new Date().toISOString();
        setCookie(CONFIG.consentCookieName, JSON.stringify(consentState), CONFIG.cookieExpiryDays);
    }

    /**
     * Load saved consent
     */
    function loadConsent() {
        const saved = getCookie(CONFIG.consentCookieName);
        if (saved) {
            try {
                consentState = JSON.parse(saved);
            } catch (e) {
                resetConsent();
            }
        }
    }

    /**
     * Reset consent state
     */
    function resetConsent() {
        consentState = {
            essential: true,
            analytics: false,
            functional: false,
            marketing: false,
            timestamp: null,
            version: CONFIG.consentVersion
        };
        deleteCookie(CONFIG.consentCookieName);
    }

    /**
     * Apply consent settings - enable/disable tracking scripts
     */
    function applyConsent() {
        if (!consentState.analytics) {
            // Disable Google Analytics if consent not given
            window['ga-disable-UA-XXXXXXXXX-X'] = true;
            // Remove any existing analytics scripts
            document.querySelectorAll('script[src*="google-analytics"], script[src*="ga-analytics"]').forEach(script => {
                script.remove();
            });
        }

        if (!consentState.functional) {
            // Remove functional cookies that aren't essential
            CONFIG.functionalCookies.forEach(cookie => deleteCookie(cookie));
        }

        if (!consentState.marketing) {
            // Remove marketing/tracking pixels
            document.querySelectorAll('img[src*="facebook.com/tr"], img[src*="pixel"]').forEach(pixel => {
                pixel.remove();
            });
        }
    }

    /**
     * Create the cookie consent banner HTML
     */
    function createBanner() {
        const banner = document.createElement('div');
        banner.id = 'gdpr-cookie-banner';
        banner.innerHTML = `
            <div class="gdpr-cookie-banner-fixed">
                <div class="gdpr-cookie-banner-content">
                    <p class="gdpr-cookie-banner-message">
                        We use cookies to improve your experience. By continuing, you accept our 
                        <a href="/gdpr/cookie-policy.html" class="gdpr-cookie-link">Cookie Policy</a>.
                    </p>
                    <div class="gdpr-cookie-banner-buttons">
                        <button class="gdpr-btn gdpr-btn-settings" id="gdpr-settings-btn">Settings</button>
                        <button class="gdpr-btn gdpr-btn-accept" id="gdpr-accept-btn">Accept</button>
                    </div>
                </div>
            </div>
        `;
        return banner;
    }

    /**
     * Create the cookie preferences modal
     */
    function createPreferencesModal() {
        const modal = document.createElement('div');
        modal.id = 'gdpr-preferences-modal';
        modal.innerHTML = `
            <div class="gdpr-modal-overlay">
                <div class="gdpr-modal-content">
                    <div class="gdpr-modal-header">
                        <h3>Cookie Preferences</h3>
                        <button class="gdpr-modal-close" id="gdpr-modal-close">&times;</button>
                    </div>
                    <div class="gdpr-modal-body">
                        <p class="gdpr-modal-description">
                            We use different types of cookies to improve your experience. 
                            You can choose which cookies to allow:
                        </p>
                        
                        <div class="gdpr-cookie-category">
                            <div class="gdpr-category-header">
                                <div>
                                    <h4>Essential Cookies</h4>
                                    <span class="gdpr-required-badge">Required</span>
                                </div>
                                <label class="gdpr-toggle disabled">
                                    <input type="checkbox" checked disabled>
                                    <span class="gdpr-toggle-slider"></span>
                                </label>
                            </div>
                            <p class="gdpr-category-description">
                                Necessary for the website to function properly. These cookies enable basic functions 
                                like page navigation, secure access, and form submission.
                            </p>
                        </div>

                        <div class="gdpr-cookie-category">
                            <div class="gdpr-category-header">
                                <div>
                                    <h4>Analytics Cookies</h4>
                                </div>
                                <label class="gdpr-toggle">
                                    <input type="checkbox" id="gdpr-analytics-toggle">
                                    <span class="gdpr-toggle-slider"></span>
                                </label>
                            </div>
                            <p class="gdpr-category-description">
                                Help us understand how visitors use the website by collecting anonymous information. 
                                This helps us improve our website and services.
                            </p>
                        </div>

                        <div class="gdpr-cookie-category">
                            <div class="gdpr-category-header">
                                <div>
                                    <h4>Functional Cookies</h4>
                                </div>
                                <label class="gdpr-toggle">
                                    <input type="checkbox" id="gdpr-functional-toggle">
                                    <span class="gdpr-toggle-slider"></span>
                                </label>
                            </div>
                            <p class="gdpr-category-description">
                                Remember your choices and settings (like language preference, currency, 
                                and login information) to provide enhanced features.
                            </p>
                        </div>

                        <div class="gdpr-cookie-category">
                            <div class="gdpr-category-header">
                                <div>
                                    <h4>Marketing Cookies</h4>
                                </div>
                                <label class="gdpr-toggle">
                                    <input type="checkbox" id="gdpr-marketing-toggle">
                                    <span class="gdpr-toggle-slider"></span>
                                </label>
                            </div>
                            <p class="gdpr-category-description">
                                Used to deliver relevant advertisements and track the effectiveness 
                                of marketing campaigns. May be shared with advertising partners.
                            </p>
                        </div>
                    </div>
                    <div class="gdpr-modal-footer">
                        <button class="gdpr-btn gdpr-btn-reject" id="gdpr-reject-btn">Reject Non-Essential</button>
                        <button class="gdpr-btn gdpr-btn-save" id="gdpr-save-btn">Save Preferences</button>
                    </div>
                </div>
            </div>
        `;
        return modal;
    }

    /**
     * Show the cookie banner
     */
    function showBanner() {
        if (hasConsent()) {
            applyConsent();
            return;
        }

        const banner = createBanner();
        document.body.appendChild(banner);

        // Add styles
        addStyles();

        // Show banner with animation
        setTimeout(() => {
            banner.querySelector('.gdpr-cookie-banner-fixed').classList.add('gdpr-show');
        }, 100);

        // Bind events
        document.getElementById('gdpr-accept-btn').addEventListener('click', acceptAll);
        document.getElementById('gdpr-settings-btn').addEventListener('click', showPreferences);
    }

    /**
     * Show preferences modal
     */
    function showPreferences() {
        loadConsent();

        const modal = createPreferencesModal();
        document.body.appendChild(modal);

        // Set toggle states based on saved consent
        document.getElementById('gdpr-analytics-toggle').checked = consentState.analytics;
        document.getElementById('gdpr-functional-toggle').checked = consentState.functional;
        document.getElementById('gdpr-marketing-toggle').checked = consentState.marketing;

        // Bind events
        document.getElementById('gdpr-modal-close').addEventListener('click', hidePreferences);
        document.getElementById('gdpr-save-btn').addEventListener('click', savePreferences);
        document.getElementById('gdpr-reject-btn').addEventListener('click', rejectNonEssential);
        document.querySelector('.gdpr-modal-overlay').addEventListener('click', (e) => {
            if (e.target === document.querySelector('.gdpr-modal-overlay')) {
                hidePreferences();
            }
        });

        // Show modal
        setTimeout(() => {
            modal.querySelector('.gdpr-modal-overlay').classList.add('gdpr-show');
        }, 100);
    }

    /**
     * Hide preferences modal
     */
    function hidePreferences() {
        const modal = document.getElementById('gdpr-preferences-modal');
        if (modal) {
            modal.querySelector('.gdpr-modal-overlay').classList.remove('gdpr-show');
            setTimeout(() => modal.remove(), 300);
        }
    }

    /**
     * Accept all cookies
     */
    function acceptAll() {
        consentState = {
            essential: true,
            analytics: true,
            functional: true,
            marketing: true,
            timestamp: new Date().toISOString(),
            version: CONFIG.consentVersion
        };
        saveConsent();
        hideBanner();
        applyConsent();

        // Track consent event
        trackConsentEvent('all_accepted');
    }

    /**
     * Reject non-essential cookies
     */
    function rejectNonEssential() {
        consentState = {
            essential: true,
            analytics: false,
            functional: false,
            marketing: false,
            timestamp: new Date().toISOString(),
            version: CONFIG.consentVersion
        };
        saveConsent();
        hidePreferences();
        hideBanner();
        applyConsent();

        // Track consent event
        trackConsentEvent('essential_only');
    }

    /**
     * Save user preferences
     */
    function savePreferences() {
        consentState = {
            essential: true,
            analytics: document.getElementById('gdpr-analytics-toggle').checked,
            functional: document.getElementById('gdpr-functional-toggle').checked,
            marketing: document.getElementById('gdpr-marketing-toggle').checked,
            timestamp: new Date().toISOString(),
            version: CONFIG.consentVersion
        };
        saveConsent();
        hidePreferences();
        hideBanner();
        applyConsent();

        // Track consent event
        trackConsentEvent('preferences_saved');
    }

    /**
     * Hide the cookie banner
     */
    function hideBanner() {
        const banner = document.getElementById('gdpr-cookie-banner');
        if (banner) {
            banner.querySelector('.gdpr-cookie-banner-fixed').classList.remove('gdpr-show');
            setTimeout(() => banner.remove(), 300);
        }
    }

    /**
     * Track consent event (using internal tracking only)
     */
    function trackConsentEvent(eventType) {
        console.log('Cookie consent event:', eventType, consentState);
        // Could integrate with analytics if consent is given
    }

    /**
     * Add CSS styles for the banner and modal
     */
    function addStyles() {
        const styles = document.createElement('style');
        styles.id = 'gdpr-cookie-styles';
        styles.textContent = `
            .gdpr-cookie-banner-fixed {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                z-index: 9999;
                background: rgba(26,26,24,0.97);
                backdrop-filter: blur(12px);
                padding: 10px 16px;
                transform: translateY(100%);
                transition: transform 0.3s ease;
            }
            .gdpr-cookie-banner-fixed.gdpr-show { transform: translateY(0); }
            .gdpr-cookie-banner-content {
                max-width: 1100px;
                margin: 0 auto;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
            }
            .gdpr-cookie-banner-message {
                font-family: 'Lora', Georgia, serif;
                font-size: 13px;
                color: rgba(255,255,255,0.75);
                line-height: 1.4;
                margin: 0;
            }
            .gdpr-cookie-link {
                color: #8FB89A;
                text-decoration: underline;
                text-underline-offset: 3px;
            }
            .gdpr-cookie-link:hover { color: #D4EBDA; }
            .gdpr-cookie-banner-buttons {
                display: flex;
                gap: 8px;
                flex-shrink: 0;
                align-items: center;
            }
            .gdpr-btn {
                font-family: 'Syne', sans-serif;
                font-size: 11px;
                font-weight: 600;
                letter-spacing: 0.05em;
                text-transform: uppercase;
                padding: 8px 18px;
                border-radius: 6px;
                border: none;
                cursor: pointer;
                transition: all 0.2s;
                white-space: nowrap;
            }
            .gdpr-btn-settings {
                background: rgba(255,255,255,0.08);
                color: rgba(255,255,255,0.7);
            }
            .gdpr-btn-settings:hover { background: rgba(255,255,255,0.15); color: #fff; }
            .gdpr-btn-accept {
                background: #1D4532;
                color: #fff;
            }
            .gdpr-btn-accept:hover { background: #153525; }

            /* Mobile */
            @media (max-width: 640px) {
                .gdpr-cookie-banner-fixed { padding: 8px 12px; }
                .gdpr-cookie-banner-content {
                    flex-direction: row;
                    align-items: center;
                    gap: 8px;
                }
                .gdpr-cookie-banner-message {
                    font-size: 11px;
                    flex: 1;
                    min-width: 0;
                }
                .gdpr-btn {
                    padding: 7px 12px;
                    font-size: 10px;
                }
                .gdpr-btn-settings { display: none; }
            }

            /* Modal */
            .gdpr-modal-overlay {
                position: fixed; inset: 0;
                background: rgba(26,26,24,0.6);
                backdrop-filter: blur(4px);
                z-index: 10000;
                display: flex; align-items: center; justify-content: center;
                padding: 16px;
                opacity: 0; visibility: hidden;
                transition: all 0.25s ease;
            }
            .gdpr-modal-overlay.gdpr-show { opacity: 1; visibility: visible; }
            .gdpr-modal-content {
                background: #F9F5EE;
                border-radius: 16px;
                max-width: 520px; width: 100%;
                max-height: 80vh; overflow-y: auto;
            }
            .gdpr-modal-header {
                display: flex; justify-content: space-between; align-items: center;
                padding: 20px 24px;
                border-bottom: 1px solid #E8E0D5;
            }
            .gdpr-modal-header h3 {
                font-family: 'Cormorant Garamond', Georgia, serif;
                font-size: 22px; font-weight: 600;
                color: #1A1A18; margin: 0;
            }
            .gdpr-modal-close {
                background: none; border: none;
                font-size: 22px; color: #8FB89A; cursor: pointer;
            }
            .gdpr-modal-close:hover { color: #1D4532; }
            .gdpr-modal-body { padding: 20px 24px; }
            .gdpr-modal-description {
                font-family: 'Lora', Georgia, serif;
                color: #6B6560; font-size: 14px;
                margin-bottom: 20px; line-height: 1.6;
            }
            .gdpr-cookie-category {
                border: 1px solid #E8E0D5;
                border-radius: 12px; padding: 14px;
                margin-bottom: 10px;
            }
            .gdpr-category-header {
                display: flex; justify-content: space-between; align-items: center;
                margin-bottom: 4px;
            }
            .gdpr-category-header h4 {
                font-family: 'Syne', sans-serif;
                font-size: 13px; font-weight: 600;
                color: #1A1A18; margin: 0;
            }
            .gdpr-required-badge {
                font-size: 10px; background: #1D4532; color: #D4EBDA;
                padding: 2px 8px; border-radius: 4px; font-weight: 500;
            }
            .gdpr-category-description {
                font-size: 12px; color: #8FB89A; line-height: 1.5; margin: 0;
                font-family: 'Lora', Georgia, serif;
            }
            .gdpr-toggle { position: relative; width: 44px; height: 24px; display: inline-block; }
            .gdpr-toggle input { opacity: 0; width: 0; height: 0; }
            .gdpr-toggle-slider {
                position: absolute; cursor: pointer; inset: 0;
                background: #CDD6CD; border-radius: 24px; transition: 0.25s;
            }
            .gdpr-toggle-slider:before {
                position: absolute; content: "";
                height: 18px; width: 18px;
                left: 3px; bottom: 3px;
                background: white; border-radius: 50%;
                transition: 0.25s;
            }
            .gdpr-toggle input:checked + .gdpr-toggle-slider { background: #1D4532; }
            .gdpr-toggle input:checked + .gdpr-toggle-slider:before { transform: translateX(20px); }
            .gdpr-toggle.disabled { opacity: 0.5; }
            .gdpr-modal-footer {
                display: flex; gap: 10px;
                padding: 16px 24px;
                border-top: 1px solid #E8E0D5;
            }
            .gdpr-modal-footer .gdpr-btn { flex: 1; justify-content: center; }
            .gdpr-btn-reject {
                background: #E8E0D5; color: #1A1A18;
            }
            .gdpr-btn-reject:hover { background: #D4D0C8; }
            .gdpr-btn-save {
                background: #1D4532; color: #fff;
            }
            .gdpr-btn-save:hover { background: #153525; }
        `;
        document.head.appendChild(styles);
    }

    /**
     * Initialize cookie consent
     */
    function init() {
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
            return;
        }

        // Don't show banner for bots/crawlers
        if (navigator.userAgent.match(/bot|crawler|spider|crawl|spider/i)) {
            return;
        }

        // Show the consent banner
        showBanner();

        // Make functions available globally
        window.GDPRConsent = {
            showPreferences,
            acceptAll,
            rejectNonEssential,
            getConsentState: () => ({ ...consentState }),
            hasConsent,
            resetConsent
        };
    }

    // Auto-initialize
    init();
})();