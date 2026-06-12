/**
 * Centralized Data Manager for 1wellness Sales Pages
 * This approach embeds all configuration data directly in the script
 * to avoid loading issues with external config files
 */

window.DataManager = {
    // SINGLE SOURCE OF TRUTH FOR PRICING: backend/api/get-pricing.php
    // Never hardcode amounts here — stale NGN/USD copies of this table were the
    // root cause of the price-inconsistency audit finding (§0.2). Use
    // DataManager.fetchPricing() (async) instead.
    pricing: {},

    fetchPricing: async function () {
        if (this._pricingPromise) return this._pricingPromise;
        this._pricingPromise = fetch('../backend/api/get-pricing.php')
            .then(r => r.json())
            .then(result => {
                if (result.success && result.data && result.data.plans) {
                    this.pricing = result.data.plans;
                }
                return this.pricing;
            })
            .catch(err => {
                console.warn('⚠️ Pricing fetch failed:', err);
                return this.pricing;
            });
        return this._pricingPromise;
    },

    // Testimonials for all product categories
    testimonials: {
        pcos: [
            {
                name: "Sarah M.",
                location: "London, UK",
                rating: 5,
                text: "After struggling with PCOS for years, this treatment plan completely changed my life. My cycles are regular now and I feel amazing!",
                image: "images/testimonials/sarah.jpg",
                verified: true
            },
            {
                name: "Jessica T.",
                location: "Toronto, Canada", 
                rating: 5,
                text: "I was skeptical at first, but the results speak for themselves. Lost 15kg and my PCOS symptoms are gone!",
                image: "images/testimonials/amina.jpg",
                verified: true
            },
            {
                name: "Emma L.",
                location: "Sydney, Australia",
                rating: 5,
                text: "The herbal supplements worked better than any medication I tried. Highly recommend to anyone with PCOS!",
                image: "images/testimonials/grace.jpg",
                verified: true
            }
        ]
    },

    // Payment configuration
    paymentConfig: {
        flutterwave: {
            publicKey: "FLWPUBK_TEST-SANDBOXDEMOKEY-X",
            secretKey: "FLWSECK_TEST-SANDBOXDEMOKEY-X",
            apiKey: "FLWPUBK_TEST-SANDBOXDEMOKEY-X",
            currency: "USD",
            country: "US",
            paymentOptions: "card,banktransfer",
            redirectUrl: window.location.origin + "/payment-success.html",
            meta: {
                source: "1wellness-website",
                medium: "sales-page"
            }
        }
    },

    // Utility methods to get data
    getPricing: function(category, planId) {
        const pricing = this.pricing[category] && this.pricing[category][planId];
        if (!pricing) {
            console.warn(`⚠️ No pricing loaded for ${category}/${planId} — call fetchPricing() first`);
            return null;
        }
        return pricing;
    },

    getTestimonials: function(category) {
        console.log(`💬 Getting testimonials for: ${category}`);
        const testimonials = this.testimonials[category];
        if (testimonials && testimonials.length > 0) {
            console.log(`✅ Found ${testimonials.length} testimonials for ${category}`);
            return testimonials;
        } else {
            console.warn(`⚠️ No testimonials found for category: ${category}`);
            return [];
        }
    },

    getPaymentConfig: function() {
        console.log(`💳 Getting payment configuration`);
        if (this.paymentConfig) {
            console.log(`✅ Payment config loaded:`, this.paymentConfig);
            return this.paymentConfig;
        } else {
            console.warn(`⚠️ No payment configuration found`);
            return null;
        }
    }
};

// Make it globally available
window.CONFIG = window.DataManager;

console.log('🚀 DataManager loaded successfully!');
