/**
 * Flutterwave Multi-Currency Integration for International Market
 * Supports USD, GBP, EUR, CAD, AUD for English-speaking countries
 */

class FlutterwaveIntegration {
    constructor() {
        this.config = {
            publicKey: null,
            currency: 'USD', // Default currency
            country: 'US',   // Default country
            loaded: false,
            scriptLoaded: false
        };

        this.pricingData = null;
        this.userRegion = this.detectUserRegion();
        this.loadFlutterwaveScript();
        this.fetchPricing();
    }

    /**
     * Detect user's region based on timezone and browser settings
     */
    detectUserRegion() {
        const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
        const language = navigator.language || navigator.userLanguage;

        // Timezone to country mapping for major English-speaking markets
        const timezoneMap = {
            // United States
            'America/New_York': { country: 'US', currency: 'USD', name: 'United States' },
            'America/Los_Angeles': { country: 'US', currency: 'USD', name: 'United States' },
            'America/Chicago': { country: 'US', currency: 'USD', name: 'United States' },
            'America/Denver': { country: 'US', currency: 'USD', name: 'United States' },
            'America/Phoenix': { country: 'US', currency: 'USD', name: 'United States' },
            'America/Anchorage': { country: 'US', currency: 'USD', name: 'United States' },
            'Pacific/Honolulu': { country: 'US', currency: 'USD', name: 'United States' },

            // United Kingdom
            'Europe/London': { country: 'GB', currency: 'GBP', name: 'United Kingdom' },

            // Canada
            'America/Toronto': { country: 'CA', currency: 'CAD', name: 'Canada' },
            'America/Vancouver': { country: 'CA', currency: 'CAD', name: 'Canada' },
            'America/Edmonton': { country: 'CA', currency: 'CAD', name: 'Canada' },
            'America/Winnipeg': { country: 'CA', currency: 'CAD', name: 'Canada' },
            'America/Halifax': { country: 'CA', currency: 'CAD', name: 'Canada' },

            // Australia
            'Australia/Sydney': { country: 'AU', currency: 'AUD', name: 'Australia' },
            'Australia/Melbourne': { country: 'AU', currency: 'AUD', name: 'Australia' },
            'Australia/Brisbane': { country: 'AU', currency: 'AUD', name: 'Australia' },
            'Australia/Perth': { country: 'AU', currency: 'AUD', name: 'Australia' },
            'Australia/Adelaide': { country: 'AU', currency: 'AUD', name: 'Australia' },

            // Ireland
            'Europe/Dublin': { country: 'IE', currency: 'EUR', name: 'Ireland' },

            // New Zealand
            'Pacific/Auckland': { country: 'NZ', currency: 'NZD', name: 'New Zealand' },
            'Pacific/Christchurch': { country: 'NZ', currency: 'NZD', name: 'New Zealand' },

            // South Africa (English-speaking)
            'Africa/Johannesburg': { country: 'ZA', currency: 'ZAR', name: 'South Africa' },
        };

        // Check timezone first
        if (timezoneMap[timezone]) {
            return timezoneMap[timezone];
        }

        // Fallback to language detection
        const langMap = {
            'en-US': { country: 'US', currency: 'USD', name: 'United States' },
            'en-GB': { country: 'GB', currency: 'GBP', name: 'United Kingdom' },
            'en-CA': { country: 'CA', currency: 'CAD', name: 'Canada' },
            'en-AU': { country: 'AU', currency: 'AUD', name: 'Australia' },
            'en-IE': { country: 'IE', currency: 'EUR', name: 'Ireland' },
            'en-NZ': { country: 'NZ', currency: 'NZD', name: 'New Zealand' },
            'en-ZA': { country: 'ZA', currency: 'ZAR', name: 'South Africa' },
        };

        if (langMap[language]) {
            return langMap[language];
        }

        // Default to US/USD
        return { country: 'US', currency: 'USD', name: 'United States' };
    }

    /**
     * Get supported currencies for Flutterwave
     */
    getSupportedCurrencies() {
        return {
            'USD': { symbol: '$', locale: 'en-US', name: 'US Dollar', countries: ['US'] },
            'GBP': { symbol: '£', locale: 'en-GB', name: 'British Pound', countries: ['GB'] },
            'EUR': { symbol: '€', locale: 'en-EU', name: 'Euro', countries: ['IE', 'DE', 'FR', 'NL', 'BE', 'AT', 'IT', 'ES', 'PT'] },
            'CAD': { symbol: 'C$', locale: 'en-CA', name: 'Canadian Dollar', countries: ['CA'] },
            'AUD': { symbol: 'A$', locale: 'en-AU', name: 'Australian Dollar', countries: ['AU'] },
            'NZD': { symbol: 'NZ$', locale: 'en-NZ', name: 'New Zealand Dollar', countries: ['NZ'] }
        };
    }

    /**
     * Get exchange rates (base: USD)
     */
    getExchangeRates() {
        return {
            'USD': 1.0,
            'GBP': 0.79,
            'EUR': 0.92,
            'CAD': 1.36,
            'AUD': 1.52,
            'NZD': 1.65
        };
    }

    /**
     * Convert price from USD to target currency
     */
    convertPrice(usdPrice, targetCurrency = this.config.currency) {
        const rates = this.getExchangeRates();
        const rate = rates[targetCurrency] || 1.0;
        return Math.round(usdPrice * rate);
    }

    /**
     * Set currency manually (e.g., from currency selector)
     */
    setCurrency(currency) {
        const supported = this.getSupportedCurrencies();
        if (supported[currency]) {
            this.config.currency = currency;
            // Update country based on currency
            this.config.country = supported[currency].countries[0];
            this.updateUIWithPrices();
            this.saveCurrencyPreference(currency);
            return true;
        }
        return false;
    }

    /**
     * Save currency preference to localStorage
     */
    saveCurrencyPreference(currency) {
        try {
            localStorage.setItem('1w_currency_preference', currency);
        } catch (e) {
            console.warn('Could not save currency preference');
        }
    }

    /**
     * Load currency preference from localStorage
     */
    loadCurrencyPreference() {
        try {
            return localStorage.getItem('1w_currency_preference');
        } catch (e) {
            return null;
        }
    }

    /**
     * Fetch pricing from backend
     */
    async fetchPricing() {
        try {
            let apiUrl = '/backend/api/get-pricing.php';

            if (window.WebhookManager && window.WebhookManager.config.localBaseUrl) {
                apiUrl = `${window.WebhookManager.config.localBaseUrl}/get-pricing.php`;
            } else {
                const path = window.location.pathname;
                if (path.includes('/pcos/') || path.includes('/acne/') || path.includes('/weight/') || path.includes('/mens/')) {
                    apiUrl = '../backend/api/get-pricing.php';
                }
            }

            const response = await fetch(apiUrl);
            const data = await response.json();

            if (data.success && data.data) {
                this.pricingData = data.data;

                if (this.pricingData.config && this.pricingData.config.flutterwavePublicKey) {
                    this.config.publicKey = this.pricingData.config.flutterwavePublicKey;
                    console.log('✅ Flutterwave public key loaded from backend');
                }

                console.log('✅ Pricing loaded from backend');
                this.updateUIWithPrices();
            }
        } catch (error) {
            console.warn('⚠️ Failed to fetch pricing, using defaults:', error);
        }
    }

    /**
     * Get current funnel identifier
     */
    getCurrentFunnel() {
        const path = window.location.pathname;
        if (path.includes('/mens/')) return 'mens';
        if (path.includes('/pcos/')) return 'pcos';
        if (path.includes('/acne/')) return 'acne';
        if (path.includes('/weight/')) return 'weight';
        return 'pcos';
    }

    /**
     * Update UI elements with dynamic prices
     */
    updateUIWithPrices() {
        const plans = this.getPaymentPlans();
        if (!plans) return;

        // Update elements with data-plan-price attribute
        document.querySelectorAll('[data-plan-price]').forEach(el => {
            const planType = el.getAttribute('data-plan-price');
            if (plans[planType]) {
                el.textContent = this.formatAmount(plans[planType].price);
            }
        });

        // Update elements with data-plan-name attribute
        document.querySelectorAll('[data-plan-name]').forEach(el => {
            const planType = el.getAttribute('data-plan-name');
            if (plans[planType]) {
                el.textContent = plans[planType].name;
            }
        });

        // Update currency display
        document.querySelectorAll('[data-currency-display]').forEach(el => {
            el.textContent = this.config.currency;
        });

        // Update region display
        document.querySelectorAll('[data-region-display]').forEach(el => {
            el.textContent = this.userRegion.name;
        });
    }

    /**
     * Get payment plans configuration with currency conversion
     */
    getPaymentPlans() {
        const funnel = this.getCurrentFunnel();
        const currency = this.config.currency;

        // Base USD prices
        const basePlans = {
            'pcos': {
                '90-day': {
                    name: '90-Day PCOS Complete Plan',
                    usdPrice: 197,
                    description: 'Complete 90-day PCOS management program',
                    features: []
                },
                '30-day': {
                    name: '30-Day PCOS Starter Plan',
                    usdPrice: 97,
                    description: 'Essential 30-day PCOS starter program',
                    features: []
                }
            },
            'acne': {
                'complete': {
                    name: 'Complete Acne Treatment Plan',
                    usdPrice: 147,
                    description: 'Complete acne treatment solution',
                    features: []
                },
                'starter': {
                    name: 'Starter Acne Treatment Plan',
                    usdPrice: 67,
                    description: 'Essential acne starter program',
                    features: []
                }
            },
            'weight': {
                '90-day': {
                    name: '90-Day Weight Loss Plan',
                    usdPrice: 167,
                    description: 'Complete 90-day weight loss program',
                    features: []
                },
                '30-day': {
                    name: '30-Day Weight Loss Starter',
                    usdPrice: 77,
                    description: 'Essential 30-day weight loss starter',
                    features: []
                }
            },
            'mens': {
                '90-day': {
                    name: "Men's 90-Day Vitality Plan",
                    usdPrice: 157,
                    description: "Complete men's vitality program",
                    features: []
                },
                '30-day': {
                    name: "Men's 30-Day Vitality Starter",
                    usdPrice: 87,
                    description: "Essential men's vitality starter",
                    features: []
                }
            }
        };

        // Convert prices to current currency
        const plans = basePlans[funnel] || {};
        const convertedPlans = {};

        for (const [key, plan] of Object.entries(plans)) {
            convertedPlans[key] = {
                ...plan,
                price: this.convertPrice(plan.usdPrice, currency),
                currency: currency
            };
        }

        return convertedPlans;
    }

    /**
     * Get formatted price for display
     */
    getFormattedPrice(planType) {
        const plans = this.getPaymentPlans();
        const plan = plans[planType];
        return plan ? this.formatAmount(plan.price) : null;
    }

    /**
     * Load Flutterwave script dynamically
     */
    async loadFlutterwaveScript() {
        if (this.config.scriptLoaded) return;

        try {
            const script = document.createElement('script');
            script.src = 'https://checkout.flutterwave.com/v3.js';
            script.async = true;

            script.onload = () => {
                this.config.scriptLoaded = true;
                console.log('✅ Flutterwave script loaded successfully');
            };

            script.onerror = () => {
                console.error('❌ Failed to load Flutterwave script');
            };

            document.head.appendChild(script);
        } catch (error) {
            console.error('❌ Error loading Flutterwave script:', error);
        }
    }

    /**
     * Initialize with public key from webhook manager or backend
     */
    async initialize() {
        // Check for saved currency preference
        const savedCurrency = this.loadCurrencyPreference();
        if (savedCurrency) {
            this.setCurrency(savedCurrency);
        } else {
            // Use detected region currency
            this.config.currency = this.userRegion.currency;
            this.config.country = this.userRegion.country;
        }

        if (this.config.publicKey) {
            this.config.loaded = true;
            return true;
        }

        try {
            if (!window.WebhookManager) {
                throw new Error('WebhookManager not available and no backend key found');
            }

            const paymentConfig = await window.WebhookManager.getFlutterwaveConfig();

            if (paymentConfig && paymentConfig.success && paymentConfig.data) {
                this.config.publicKey = paymentConfig.data.flutterwavePublicKey;
                this.config.loaded = true;
                console.log('✅ Flutterwave initialized with public key from WebhookManager');
                return true;
            } else {
                throw new Error('Failed to get Flutterwave configuration');
            }
        } catch (error) {
            console.error('❌ Flutterwave initialization failed:', error);
            return false;
        }
    }

    /**
     * Format amount for display based on currency
     */
    formatAmount(amount) {
        const currency = this.config.currency;
        const supported = this.getSupportedCurrencies();
        const config = supported[currency] || supported['USD'];

        return new Intl.NumberFormat(config.locale, {
            style: 'currency',
            currency: currency,
            minimumFractionDigits: 0
        }).format(amount);
    }

    /**
     * Process payment for plans
     */
    async processPayment(paymentData) {
        try {
            if (!this.config.loaded) {
                const initialized = await this.initialize();
                if (!initialized) {
                    throw new Error('Flutterwave not initialized');
                }
            }

            if (!this.config.scriptLoaded) {
                throw new Error('Flutterwave script not loaded');
            }

            // OVERRIDE PRICE FROM DB IF AVAILABLE
            const plans = this.getPaymentPlans();
            if (plans && plans[paymentData.plan]) {
                const dbPrice = plans[paymentData.plan].price;
                if (dbPrice && dbPrice != paymentData.amount) {
                    console.log(`💰 Overriding price ${paymentData.amount} with DB price ${dbPrice}`);
                    paymentData.amount = dbPrice;
                }
            }

            // Track purchase intent
            if (window.WebhookManager) {
                await window.WebhookManager.trackPurchaseIntent({
                    plan: paymentData.plan,
                    amount: paymentData.amount,
                    currency: this.config.currency,
                    email: paymentData.customer.email,
                    phone: paymentData.customer.phone,
                    country: this.config.country
                });
            }

            const flutterwaveConfig = {
                public_key: this.config.publicKey,
                tx_ref: this.generateTransactionRef(paymentData.plan),
                amount: paymentData.amount,
                currency: this.config.currency,
                country: this.config.country,
                payment_options: "card,banktransfer,applepay,googlepay",
                customer: {
                    email: paymentData.customer.email,
                    phone_number: paymentData.customer.phone,
                    name: paymentData.customer.name,
                },
                customizations: {
                    title: paymentData.title || this.getProductTitle(),
                    description: paymentData.description || this.getProductDescription(),
                    logo: this.getProductLogo()
                },
                callback: (response) => this.handlePaymentCallback(response, paymentData),
                onclose: () => this.handlePaymentClose(paymentData)
            };

            // Launch Flutterwave payment modal
            window.FlutterwaveCheckout(flutterwaveConfig);

        } catch (error) {
            console.error('❌ Payment processing failed:', error);
            throw error;
        }
    }

    /**
     * Handle successful payment callback
     */
    async handlePaymentCallback(response, originalPaymentData) {
        console.log('💳 Payment callback received:', response);

        if (response.status === 'successful' || response.status === 'completed') {
            console.log('✅ Payment successful! Transaction ID:', response.transaction_id);

            // Try to confirm purchase with webhook manager
            if (window.WebhookManager) {
                try {
                    const confirmResult = await window.WebhookManager.confirmPurchase({
                        transactionId: response.transaction_id,
                        flutterwaveRef: response.tx_ref,
                        amount: response.amount,
                        currency: response.currency,
                        plan: originalPaymentData.plan,
                        customer: originalPaymentData.customer,
                        status: 'completed',
                        country: this.config.country
                    });
                    console.log('✅ Purchase confirmation sent to backend');

                    if (confirmResult && confirmResult.credentials) {
                        localStorage.setItem('1w_new_user_creds', JSON.stringify(confirmResult.credentials));
                    }
                    if (confirmResult && confirmResult.auto_login_token) {
                        localStorage.setItem('1w_auto_login', confirmResult.auto_login_token);
                    }
                } catch (webhookError) {
                    console.warn('⚠️ Webhook confirmation failed (payment still successful):', webhookError);
                }
            }

            // Always redirect to thank you page
            const currentDir = window.location.origin + window.location.pathname.replace(/[^/]*$/, '');

            let redirectPage = 'thank-you.html';
            if (originalPaymentData.meta && originalPaymentData.meta.delivery_preference === 'pdf') {
                redirectPage = 'generating-plan.html';
            }

            const thankYouUrl = new URL(redirectPage, currentDir);
            thankYouUrl.searchParams.set('tx_ref', response.tx_ref);
            thankYouUrl.searchParams.set('transaction_id', response.transaction_id);
            thankYouUrl.searchParams.set('plan', originalPaymentData.plan);
            thankYouUrl.searchParams.set('amount', response.amount);
            thankYouUrl.searchParams.set('currency', response.currency);
            thankYouUrl.searchParams.set('status', 'success');
            thankYouUrl.searchParams.set('delivery', originalPaymentData.meta ? originalPaymentData.meta.delivery_preference : 'web');

            if (originalPaymentData.customer) {
                thankYouUrl.searchParams.set('name', originalPaymentData.customer.name || '');
                thankYouUrl.searchParams.set('email', originalPaymentData.customer.email || '');
                thankYouUrl.searchParams.set('phone', originalPaymentData.customer.phone || originalPaymentData.customer.phone_number || '');
            }

            console.log('🎉 Redirecting to thank you page:', thankYouUrl.toString());
            window.location.href = thankYouUrl.toString();

        } else {
            console.error('❌ Payment failed at Flutterwave:', response);

            if (window.WebhookManager) {
                window.WebhookManager.trackPurchaseFailed({
                    plan: originalPaymentData.plan,
                    amount: response.amount || originalPaymentData.amount,
                    currency: this.config.currency,
                    email: originalPaymentData.customer.email,
                    transaction_id: response.transaction_id,
                    status: response.status,
                    tx_ref: response.tx_ref
                });
            }

            alert('Payment was not successful. Status: ' + response.status + '. Please try again or contact support.');
        }
    }

    /**
     * Handle payment modal close
     */
    handlePaymentClose(paymentData) {
        console.log('💳 Payment modal closed by user');
        if (window.WebhookManager && paymentData) {
            window.WebhookManager.trackAbandonment({
                plan: paymentData.plan,
                amount: paymentData.amount,
                currency: this.config.currency,
                email: paymentData.customer.email,
                reason: 'Modal Closed'
            });
        }
    }

    /**
     * Get product title based on current funnel
     */
    getProductTitle() {
        const funnel = this.getCurrentFunnel();
        if (funnel === 'mens') return "Men's Vitality Plan";
        if (funnel === 'pcos') return "PCOS Treatment Plan";
        if (funnel === 'acne') return "Acne Treatment Plan";
        if (funnel === 'weight') return "Weight Loss Plan";
        return "Treatment Plan";
    }

    /**
     * Get product description based on current funnel
     */
    getProductDescription() {
        const funnel = this.getCurrentFunnel();
        if (funnel === 'mens') return "Natural men's vitality formula";
        if (funnel === 'pcos') return "Complete PCOS management solution";
        if (funnel === 'acne') return "Natural acne treatment solution";
        if (funnel === 'weight') return "Personalized weight loss program";
        return "Natural health solution";
    }

    /**
     * Get product logo based on current funnel
     */
    getProductLogo() {
        const funnel = this.getCurrentFunnel();
        if (funnel === 'mens') return "https://1wellness.club/assets/vitale-logo.png";
        if (funnel === 'pcos') return "https://1wellness.club/assets/cyclesync-logo.png";
        if (funnel === 'acne') return "https://1wellness.club/assets/glowclear-logo.png";
        if (funnel === 'weight') return "https://1wellness.club/assets/leanflow-logo.png";
        return "https://1wellness.club/assets/logo.png";
    }

    /**
     * Generate unique transaction reference
     */
    generateTransactionRef(planType) {
        const timestamp = Date.now();
        const random = Math.random().toString(36).substring(2, 8);
        const funnel = this.getCurrentFunnel().toUpperCase();
        const currency = this.config.currency;
        return `${funnel}_${currency}_${timestamp}_${random}`;
    }

    /**
     * Validate customer data before payment
     */
    validateCustomerData(customerData) {
        const errors = [];

        if (!customerData.name || customerData.name.trim().length < 2) {
            errors.push('Valid name is required');
        }

        if (!customerData.email || !this.isValidEmail(customerData.email)) {
            errors.push('Valid email address is required');
        }

        if (!customerData.phone || !this.isValidInternationalPhone(customerData.phone)) {
            errors.push('Valid phone number is required');
        }

        return errors;
    }

    /**
     * Validate email format
     */
    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    /**
     * Validate international phone number
     * Supports US, UK, Canada, Australia, Ireland, New Zealand
     */
    isValidInternationalPhone(phone) {
        const cleanPhone = phone.replace(/\D/g, '');

        // US/Canada: +1 (10 digits after country code)
        const usCanadaPattern = /^1?\d{10}$/;

        // UK: +44 (10-11 digits after country code)
        const ukPattern = /^44\d{9,10}$/;

        // Australia: +61 (9 digits after country code)
        const auPattern = /^61\d{9}$/;

        // Ireland: +353 (8-9 digits after country code)
        const iePattern = /^353\d{8,9}$/;

        // New Zealand: +64 (8-9 digits after country code)
        const nzPattern = /^64\d{8,9}$/;

        // Generic international (at least 10 digits, max 15)
        const genericPattern = /^\d{10,15}$/;

        return usCanadaPattern.test(cleanPhone) ||
            ukPattern.test(cleanPhone) ||
            auPattern.test(cleanPhone) ||
            iePattern.test(cleanPhone) ||
            nzPattern.test(cleanPhone) ||
            genericPattern.test(cleanPhone);
    }

    /**
     * Format phone number with international prefix
     */
    formatInternationalPhone(phone, country = this.config.country) {
        const cleanPhone = phone.replace(/\D/g, '');

        // Already has international prefix
        if (cleanPhone.startsWith('1') && cleanPhone.length === 11) {
            return '+' + cleanPhone; // US/Canada
        }
        if (cleanPhone.startsWith('44') && cleanPhone.length >= 11) {
            return '+' + cleanPhone; // UK
        }
        if (cleanPhone.startsWith('61') && cleanPhone.length >= 10) {
            return '+' + cleanPhone; // Australia
        }
        if (cleanPhone.startsWith('353') && cleanPhone.length >= 11) {
            return '+' + cleanPhone; // Ireland
        }
        if (cleanPhone.startsWith('64') && cleanPhone.length >= 10) {
            return '+' + cleanPhone; // New Zealand
        }

        // Add country code based on detected country
        const countryCodes = {
            'US': '1',
            'CA': '1',
            'GB': '44',
            'AU': '61',
            'IE': '353',
            'NZ': '64'
        };

        const code = countryCodes[country] || '1';

        // Remove leading 0 if present
        const localNumber = cleanPhone.startsWith('0') ? cleanPhone.substring(1) : cleanPhone;

        return '+' + code + localNumber;
    }

    /**
     * Create currency selector HTML
     */
    createCurrencySelector() {
        const currencies = this.getSupportedCurrencies();
        const currentCurrency = this.config.currency;

        let html = '<div class="currency-selector">';
        html += '<label for="currency-select">Currency:</label>';
        html += '<select id="currency-select" onchange="window.FlutterwaveIntegration.setCurrency(this.value)">';

        for (const [code, config] of Object.entries(currencies)) {
            const selected = code === currentCurrency ? 'selected' : '';
            html += `<option value="${code}" ${selected}>${config.symbol} ${code} - ${config.name}</option>`;
        }

        html += '</select></div>';
        return html;
    }

    /**
     * Render currency selector into element
     */
    renderCurrencySelector(selector) {
        const element = document.querySelector(selector);
        if (element) {
            element.innerHTML = this.createCurrencySelector();
        }
    }
}

// Global instance
window.FlutterwaveIntegration = new FlutterwaveIntegration();

// Export for module systems if needed
if (typeof module !== 'undefined' && module.exports) {
    module.exports = FlutterwaveIntegration;
}

console.log('💳 Multi-Currency Flutterwave Integration loaded for', window.FlutterwaveIntegration.userRegion.name);
