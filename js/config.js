// Configuration file for 1wellness (USD & Multi-Currency)
// Optimized for English-speaking countries and global markets
// Supports: USD, GBP, EUR, CAD, AUD via Flutterwave

const CONFIG = {
    // Brand Configuration
    brand: {
        name: "1wellness",
        tagline: "Personalized Health. Proven Results.",
        domain: "1wellness.club",
        supportEmail: "hello@1wellness.club",
        supportPhone: "+1 (800) 1-WELLNESS",
        region: "International",
        currency: "USD"
    },

    // Sub-Brand Identities (per funnel)
    subBrands: {
        pcos: {
            name: "CycleSync",
            fullName: "CycleSync by 1wellness",
            tagline: "Understand your cycle. Own your health.",
            subdomain: "pcos.1wellness.club",
            themeColor: "#7c3aed",
            themeColorLight: "#ede9fe",
            themeColorDark: "#5b21b6",
            icon: "🌸"
        },
        acne: {
            name: "GlowClear",
            fullName: "GlowClear by 1wellness",
            tagline: "Clear skin starts from within.",
            subdomain: "skin.1wellness.club",
            themeColor: "#059669",
            themeColorLight: "#d1fae5",
            themeColorDark: "#047857",
            icon: "✨"
        },
        weight: {
            name: "LeanFlow",
            fullName: "LeanFlow by 1wellness",
            tagline: "Burn smarter. Live lighter.",
            subdomain: "lean.1wellness.club",
            themeColor: "#ea580c",
            themeColorLight: "#ffedd5",
            themeColorDark: "#c2410c",
            icon: "🔥"
        },
        mens: {
            name: "Vitale",
            fullName: "Vitale by 1wellness",
            tagline: "Built different. Fuelled natural.",
            subdomain: "men.1wellness.club",
            themeColor: "#2563eb",
            themeColorLight: "#dbeafe",
            themeColorDark: "#1d4ed8",
            icon: "⚡"
        }
    },

    // Payment Gateway Configuration - Multi-Currency Support
    payment: {
        flutterwave: {
            publicKey: "FLWPUBK_TEST-SANDBOXDEMOKEY-X", // Replace with live key for production
            environment: "sandbox", // Change to "production" for live
            defaultCurrency: "USD",
            defaultCountry: "US",
            // Supported currencies for Flutterwave
            supportedCurrencies: ["USD", "GBP", "EUR", "CAD", "AUD"],
            supportedCountries: ["US", "GB", "CA", "AU", "IE", "NZ"],
            paymentMethods: ["card", "banktransfer", "apple_pay", "google_pay"],
            // Currency display settings
            currencyDisplay: {
                USD: { symbol: "$", locale: "en-US", name: "US Dollar" },
                GBP: { symbol: "£", locale: "en-GB", name: "British Pound" },
                EUR: { symbol: "€", locale: "en-EU", name: "Euro" },
                CAD: { symbol: "C$", locale: "en-CA", name: "Canadian Dollar" },
                AUD: { symbol: "A$", locale: "en-AU", name: "Australian Dollar" }
            }
        },
        // Currency conversion rates (update regularly or use API)
        exchangeRates: {
            USD: 1.0,
            GBP: 0.79,
            EUR: 0.92,
            CAD: 1.36,
            AUD: 1.52
        }
    },

    // Regional Settings
    regions: {
        US: { currency: "USD", name: "United States", phonePrefix: "+1" },
        GB: { currency: "GBP", name: "United Kingdom", phonePrefix: "+44" },
        CA: { currency: "CAD", name: "Canada", phonePrefix: "+1" },
        AU: { currency: "AUD", name: "Australia", phonePrefix: "+61" },
        IE: { currency: "EUR", name: "Ireland", phonePrefix: "+353" },
        NZ: { currency: "AUD", name: "New Zealand", phonePrefix: "+64" }
    },

    // Pricing Configuration - USD Base Prices
    pricing: {
        "pcos-90-day-plan": {
            originalPrice: 197,
            salePrice: 97,
            currency: "USD",
            discount: 51,
            features: [
                "Personalized 90-Day Hormone Balance Protocol",
                "Evidence-Based Herbal Supplement Guide",
                "Weekly Progress Tracking Templates",
                "24/7 Private Community Access",
                "Hormone-Friendly Recipe Collection",
                "Exercise & Lifestyle Optimization Guide",
                "Monthly Live Q&A Sessions"
            ]
        },
        "acne-treatment-plan": {
            originalPrice: 147,
            salePrice: 67,
            currency: "USD",
            discount: 54,
            features: [
                "Personalized Acne Clear Skin Protocol",
                "Natural Ingredient Sourcing Guide",
                "Daily Skincare Routine Framework",
                "Anti-Inflammatory Nutrition Plan",
                "Progress Tracking Dashboard",
                "Expert Email Support",
                "Lifetime Plan Updates"
            ]
        },
        "weight-loss-plan": {
            originalPrice: 167,
            salePrice: 77,
            currency: "USD",
            discount: 54,
            features: [
                "Personalized Metabolic Reset Protocol",
                "Natural Weight Management Guide",
                "Meal Planning System",
                "Movement & Exercise Framework",
                "Habit Tracking Tools",
                "Community Accountability Group",
                "Monthly Progress Reviews"
            ]
        },
        "mens-vitality-plan": {
            originalPrice: 157,
            salePrice: 87,
            currency: "USD",
            discount: 45,
            features: [
                "Men's Natural Vitality Protocol",
                "Energy & Performance Optimization",
                "Stress Management Techniques",
                "Sleep Quality Enhancement Guide",
                "Herbal Supplement Protocol",
                "Weekly Progress Check-ins",
                "Private Men's Community Access"
            ]
        }
    },

    // Testimonials - International English-speaking customers
    testimonials: {
        pcos: [
            {
                name: "Sarah M.",
                location: "London, UK",
                condition: "PCOS",
                result: "Regular cycles in 3 months",
                testimonial: "After struggling with irregular periods for years, this protocol helped me achieve regular cycles naturally. My doctor was amazed at my progress!",
                rating: 5,
                verified: true
            },
            {
                name: "Jessica T.",
                location: "Toronto, Canada",
                condition: "PCOS",
                result: "Lost 30lbs and conceived",
                testimonial: "The holistic approach changed everything. I lost weight sustainably and finally conceived after 2 years of trying. Forever grateful!",
                rating: 5,
                verified: true
            },
            {
                name: "Emma L.",
                location: "Sydney, Australia",
                condition: "PCOS",
                result: "Hormones balanced naturally",
                testimonial: "My endocrinologist confirmed my hormone levels are now in the normal range. No more medications needed!",
                rating: 5,
                verified: true
            },
            {
                name: "Rachel K.",
                location: "New York, USA",
                condition: "PCOS",
                result: "Clear skin and energy back",
                testimonial: "Not only did my cycles regulate, but my acne cleared up and I have energy I haven't had in years. Worth every penny!",
                rating: 5,
                verified: true
            }
        ],
        acne: [
            {
                name: "Amanda P.",
                location: "Los Angeles, USA",
                condition: "Severe Acne",
                result: "Clear skin in 8 weeks",
                testimonial: "Dermatologists wanted to put me on harsh medications. This natural approach cleared my skin without any side effects!",
                rating: 5,
                verified: true
            },
            {
                name: "Sophie W.",
                location: "Manchester, UK",
                condition: "Hormonal Acne",
                result: "No more monthly breakouts",
                testimonial: "I used to dread that time of the month because of breakouts. Now my skin stays clear all month long!",
                rating: 5,
                verified: true
            },
            {
                name: "Olivia H.",
                location: "Vancouver, Canada",
                condition: "Cystic Acne",
                result: "Painful cysts gone",
                testimonial: "The painful cysts that plagued me for years are completely gone. I can finally look in the mirror with confidence.",
                rating: 5,
                verified: true
            }
        ],
        weight: [
            {
                name: "Michelle B.",
                location: "Chicago, USA",
                condition: "Weight Management",
                result: "Lost 40lbs in 5 months",
                testimonial: "I've tried every diet out there. This is the first approach that addressed my metabolism and actually worked long-term!",
                rating: 5,
                verified: true
            },
            {
                name: "Kate D.",
                location: "Melbourne, Australia",
                condition: "Stubborn Weight",
                result: "Lost 25kg naturally",
                testimonial: "The herbal protocols and meal plans made weight loss feel effortless. No counting calories or extreme workouts!",
                rating: 5,
                verified: true
            },
            {
                name: "Laura S.",
                location: "Dublin, Ireland",
                condition: "Postpartum Weight",
                result: "Back to pre-baby weight",
                testimonial: "After two kids, I thought I'd never get my body back. This program helped me lose the weight and keep it off!",
                rating: 5,
                verified: true
            }
        ],
        mens: [
            {
                name: "James R.",
                location: "Austin, USA",
                condition: "Low Energy",
                result: "Energy levels restored",
                testimonial: "I was skeptical about natural solutions, but my energy and focus have improved dramatically. Feel like I'm in my 20s again!",
                rating: 5,
                verified: true
            },
            {
                name: "Michael T.",
                location: "Birmingham, UK",
                condition: "Stress & Fatigue",
                result: "Sleep and vitality improved",
                testimonial: "The stress management techniques alone were worth it. Combined with the herbal protocol, I feel like a new man.",
                rating: 5,
                verified: true
            }
        ]
    },

    // Copy & Messaging - International English
    copy: {
        // Trust signals for international audience
        trust: {
            guarantee: "30-Day Money-Back Guarantee",
            secure: "256-bit SSL Secure Checkout",
            support: "24/7 Customer Support",
            verified: "Verified Customer Reviews",
            natural: "100% Natural Ingredients",
            science: "Science-Backed Protocols"
        },
        // Urgency and scarcity
        urgency: {
            limited: "Limited Time Offer",
            spots: "Only {count} spots remaining at this price",
            timer: "Offer expires in",
            popular: "Most Popular Choice"
        },
        // Call-to-action buttons
        cta: {
            primary: "Get My Personalized Plan",
            secondary: "Start Free Assessment",
            payment: "Complete Secure Payment",
            download: "Download My Plan Now",
            access: "Access Member Portal"
        },
        // Social proof
        socialProof: {
            customers: "Join 50,000+ customers worldwide",
            countries: "Trusted in 15+ countries",
            rating: "4.9/5 average rating",
            success: "94% success rate"
        }
    },

    // API Configuration
    api: {
        baseUrl: "https://api.1wellness.club",
        endpoints: {
            leads: "/webhook/leads",
            pricing: "/api/pricing",
            testimonials: "/api/testimonials",
            paymentConfig: "/api/payment-config",
            conversion: "/webhook/conversion",
            currency: "/api/currency-rates"
        }
    },

    // Legal & Compliance
    legal: {
        termsUrl: "/terms",
        privacyUrl: "/privacy",
        refundPolicy: "/refund-policy",
        disclaimer: "Results may vary. These statements have not been evaluated by the FDA. This product is not intended to diagnose, treat, cure, or prevent any disease.",
        medicalDisclaimer: "Always consult with a healthcare provider before starting any new health program."
    }
};

// Helper functions to access config data
const ConfigManager = {
    // Get pricing data with currency conversion
    getPricing: function (productId, targetCurrency = 'USD') {
        const basePricing = CONFIG.pricing[productId];
        if (!basePricing) return null;

        // If requesting base currency, return as-is
        if (targetCurrency === basePricing.currency) {
            return basePricing;
        }

        // Convert pricing to target currency
        const rate = CONFIG.payment.exchangeRates[targetCurrency] || 1;
        const convertedPrice = Math.round(basePricing.salePrice * rate);
        const convertedOriginal = Math.round(basePricing.originalPrice * rate);

        return {
            ...basePricing,
            salePrice: convertedPrice,
            originalPrice: convertedOriginal,
            displayCurrency: targetCurrency
        };
    },

    // Get testimonials
    getTestimonials: function (category) {
        return CONFIG.testimonials[category] || [];
    },

    // Get payment configuration
    getPaymentConfig: function () {
        return CONFIG.payment.flutterwave;
    },

    // Get currency display settings
    getCurrencyDisplay: function (currency) {
        return CONFIG.payment.flutterwave.currencyDisplay[currency] ||
            CONFIG.payment.flutterwave.currencyDisplay.USD;
    },

    // Format amount for display
    formatAmount: function (amount, currency = 'USD') {
        const display = this.getCurrencyDisplay(currency);
        return new Intl.NumberFormat(display.locale, {
            style: 'currency',
            currency: currency,
            minimumFractionDigits: 0
        }).format(amount);
    },

    // Detect user's region/currency
    detectRegion: function () {
        // Try to detect from browser
        const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
        const language = navigator.language || navigator.userLanguage;

        // Simple timezone-based detection
        if (timezone.includes('London') || timezone.includes('Europe/London')) return 'GB';
        if (timezone.includes('Sydney') || timezone.includes('Melbourne')) return 'AU';
        if (timezone.includes('Toronto') || timezone.includes('Vancouver')) return 'CA';
        if (timezone.includes('Dublin')) return 'IE';
        if (timezone.includes('Auckland')) return 'NZ';

        // Default to US
        return 'US';
    },

    // Get region settings
    getRegionSettings: function (regionCode) {
        return CONFIG.regions[regionCode] || CONFIG.regions.US;
    }
};

// Make ConfigManager available globally
if (typeof window !== 'undefined') {
    window.ConfigManager = ConfigManager;
    window.CONFIG = CONFIG;
}

// Export for Node.js environments
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { CONFIG, ConfigManager };
}

console.log('🌿 1wellness Config loaded - Multi-currency support enabled');

// Auto-load Flutterwave keys from backend settings
(async function loadPaymentSettings() {
    try {
        const res = await fetch('../backend/api/get-settings.php');
        const json = await res.json();
        if (json.success && json.data) {
            const d = json.data;
            if (d.flutterwave_public_key && d.flutterwave_public_key !== '' && !d.flutterwave_public_key.includes('xxxxxxxxx')) {
                CONFIG.payment.flutterwave.publicKey = d.flutterwave_public_key;
            }
            if (d.flutterwave_environment) {
                CONFIG.payment.flutterwave.environment = d.flutterwave_environment;
                // Update Flutterwave SDK environment
                if (typeof FlutterwaveCheckout !== 'undefined') {
                    FlutterwaveCheckout({
                        public_key: d.flutterwave_public_key,
                        env: d.flutterwave_environment
                    });
                }
            }
            console.log('💰 Payment settings loaded from admin');
        }
    } catch (e) {
        console.log('Using default payment settings');
    }
})();
