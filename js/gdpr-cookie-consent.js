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
                    <div class="gdpr-cookie-banner-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                            <path d="M12 8v4"/>
                            <path d="M12 16h.01"/>
                        </svg>
                    </div>
                    <div class="gdpr-cookie-banner-text">
                        <h4 class="gdpr-cookie-banner-title">We Value Your Privacy</h4>
                        <p class="gdpr-cookie-banner-message">
                            We use cookies to enhance your browsing experience, analyze site traffic, 
                            and personalize content. By clicking "Accept All", you consent to our use of cookies. 
                            You can customize your preferences or learn more in our 
                            <a href="/gdpr/cookie-policy.html" class="gdpr-cookie-link">Cookie Policy</a> and 
                            <a href="/gdpr/privacy-policy.html" class="gdpr-cookie-link">Privacy Policy</a>.
                        </p>
                    </div>
                    <div class="gdpr-cookie-banner-buttons">
                        <button class="gdpr-btn gdpr-btn-settings" id="gdpr-settings-btn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="gdpr-icon">
                                <circle cx="12" cy="12" r="3"/>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                            </svg>
                            Settings
                        </button>
                        <button class="gdpr-btn gdpr-btn-accept" id="gdpr-accept-btn">Accept All</button>
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
            /* Cookie Banner Styles */
            .gdpr-cookie-banner-fixed {
                position: fixed;
                bottom: -100%;
                left: 0;
                right: 0;
                background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
                color: white;
                padding: 1.5rem;
                box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.15);
                z-index: 9999;
                transition: bottom 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .gdpr-cookie-banner-fixed.gdpr-show {
                bottom: 0;
            }

            .gdpr-cookie-banner-content {
                max-width: 1200px;
                margin: 0 auto;
                display: flex;
                align-items: flex-start;
                gap: 1.5rem;
            }

            .gdpr-cookie-banner-icon {
                flex-shrink: 0;
                width: 48px;
                height: 48px;
                background: rgba(255, 255, 255, 0.2);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .gdpr-cookie-banner-icon svg {
                width: 28px;
                height: 28px;
            }

            .gdpr-cookie-banner-text {
                flex: 1;
            }

            .gdpr-cookie-banner-title {
                font-size: 1.125rem;
                font-weight: 600;
                margin-bottom: 0.5rem;
            }

            .gdpr-cookie-banner-message {
                font-size: 0.9375rem;
                line-height: 1.6;
                opacity: 0.95;
            }

            .gdpr-cookie-link {
                color: white;
                text-decoration: underline;
                font-weight: 500;
            }

            .gdpr-cookie-link:hover {
                text-decoration: none;
            }

            .gdpr-cookie-banner-buttons {
                display: flex;
                gap: 0.75rem;
                flex-shrink: 0;
                padding-top: 0.5rem;
            }

            .gdpr-btn {
                padding: 0.75rem 1.5rem;
                border-radius: 0.5rem;
                font-weight: 600;
                font-size: 0.9375rem;
                cursor: pointer;
                transition: all 0.2s ease;
                border: none;
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }

            .gdpr-btn .gdpr-icon {
                width: 18px;
                height: 18px;
            }

            .gdpr-btn-settings {
                background: rgba(255, 255, 255, 0.2);
                color: white;
                backdrop-filter: blur(4px);
            }

            .gdpr-btn-settings:hover {
                background: rgba(255, 255, 255, 0.3);
            }

            .gdpr-btn-accept {
                background: white;
                color: #16a34a;
            }

            .gdpr-btn-accept:hover {
                background: #f0fdf4;
                transform: translateY(-1px);
            }

            .gdpr-btn-reject {
                background: #f3f4f6;
                color: #374151;
            }

            .gdpr-btn-reject:hover {
                background: #e5e7eb;
            }

            .gdpr-btn-save {
                background: #16a34a;
                color: white;
            }

            .gdpr-btn-save:hover {
                background: #15803d;
            }

            /* Modal Styles */
            .gdpr-modal-overlay {
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.6);
                backdrop-filter: blur(4px);
                z-index: 10000;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 1rem;
                opacity: 0;
                visibility: hidden;
                transition: all 0.3s ease;
            }

            .gdpr-modal-overlay.gdpr-show {
                opacity: 1;
                visibility: visible;
            }

            .gdpr-modal-content {
                background: white;
                border-radius: 1rem;
                max-width: 600px;
                width: 100%;
                max-height: 90vh;
                overflow-y: auto;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
                transform: scale(0.95) translateY(20px);
                transition: transform 0.3s ease;
            }

            .gdpr-modal-overlay.gdpr-show .gdpr-modal-content {
                transform: scale(1) translateY(0);
            }

            .gdpr-modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 1.5rem;
                border-bottom: 1px solid #e5e7eb;
            }

            .gdpr-modal-header h3 {
                font-size: 1.5rem;
                font-weight: 700;
                color: #111827;
                margin: 0;
            }

            .gdpr-modal-close {
                background: none;
                border: none;
                font-size: 2rem;
                color: #9ca3af;
                cursor: pointer;
                line-height: 1;
                padding: 0;
                width: 2rem;
                height: 2rem;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 0.5rem;
                transition: all 0.2s;
            }

            .gdpr-modal-close:hover {
                color: #374151;
                background: #f3f4f6;
            }

            .gdpr-modal-body {
                padding: 1.5rem;
            }

            .gdpr-modal-description {
                color: #6b7280;
                margin-bottom: 1.5rem;
                line-height: 1.6;
            }

            .gdpr-cookie-category {
                border: 1px solid #e5e7eb;
                border-radius: 0.75rem;
                padding: 1rem;
                margin-bottom: 1rem;
                transition: border-color 0.2s;
            }

            .gdpr-cookie-category:hover {
                border-color: #16a34a;
            }

            .gdpr-category-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 0.5rem;
            }

            .gdpr-category-header h4 {
                font-size: 1rem;
                font-weight: 600;
                color: #111827;
                margin: 0;
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }

            .gdpr-required-badge {
                font-size: 0.75rem;
                background: #fef3c7;
                color: #92400e;
                padding: 0.25rem 0.5rem;
                border-radius: 0.25rem;
                font-weight: 500;
            }

            .gdpr-category-description {
                font-size: 0.875rem;
                color: #6b7280;
                line-height: 1.6;
                margin: 0;
            }

            /* Toggle Switch */
            .gdpr-toggle {
                position: relative;
                width: 50px;
                height: 28px;
                display: inline-block;
            }

            .gdpr-toggle input {
                opacity: 0;
                width: 0;
                height: 0;
            }

            .gdpr-toggle-slider {
                position: absolute;
                cursor: pointer;
                inset: 0;
                background-color: #d1d5db;
                transition: 0.3s;
                border-radius: 28px;
            }

            .gdpr-toggle-slider:before {
                position: absolute;
                content: "";
                height: 22px;
                width: 22px;
                left: 3px;
                bottom: 3px;
                background-color: white;
                transition: 0.3s;
                border-radius: 50%;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            }

            .gdpr-toggle input:checked + .gdpr-toggle-slider {
                background-color: #16a34a;
            }

            .gdpr-toggle input:checked + .gdpr-toggle-slider:before {
                transform: translateX(22px);
            }

            .gdpr-toggle.disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }

            .gdpr-modal-footer {
                display: flex;
                gap: 1rem;
                padding: 1.5rem;
                border-top: 1px solid #e5e7eb;
                background: #f9fafb;
                border-radius: 0 0 1rem 1rem;
            }

            .gdpr-modal-footer .gdpr-btn {
                flex: 1;
                justify-content: center;
            }

            /* Mobile Responsive */
            @media (max-width: 768px) {
                .gdpr-cookie-banner-content {
                    flex-direction: column;
                    gap: 1rem;
                }

                .gdpr-cookie-banner-icon {
                    width: 40px;
                    height: 40px;
                }

                .gdpr-cookie-banner-buttons {
                    width: 100%;
                    flex-direction: column;
                }

                .gdpr-btn {
                    width: 100%;
                    justify-content: center;
                }

                .gdpr-modal-footer {
                    flex-direction: column;
                }
            }
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