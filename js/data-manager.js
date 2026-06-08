/**
 * Centralized Data Manager for 1wellness Sales Pages
 * This approach embeds all configuration data directly in the script
 * to avoid loading issues with external config files
 */

window.DataManager = {
    // Pricing configurations for all products
    pricing: {
        'pcos-90-day-plan': {
            originalPrice: 197,
            salePrice: 97,
            currency: 'USD',
            features: [
                'Complete 90-day PCOS treatment plan',
                'Natural herbal supplements',
                'Personalized diet guide',
                'Exercise recommendations',
                'WhatsApp support group',
                'Free consultation with herbalist',
                'Money-back guarantee'
            ]
        },
        'weight-loss-plan': {
            originalPrice: 147,
            salePrice: 77,
            currency: 'USD',
            features: [
                'Effective weight loss supplements',
                'Customized meal plans',
                'Workout routines',
                'Progress tracking tools',
                'Community support',
                'Expert guidance',
                '30-day money-back guarantee'
            ]
        },
        'acne-treatment-plan': {
            originalPrice: 137,
            salePrice: 67,
            currency: 'USD',
            features: [
                'Natural acne treatment products',
                'Skincare routine guide',
                'Dietary recommendations',
                'Before/after tracking',
                'Expert dermatologist consultation',
                'WhatsApp support',
                'Satisfaction guarantee'
            ]
        }
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
    getPricing: function(planId) {
        console.log(`📊 Getting pricing for: ${planId}`);
        const pricing = this.pricing[planId];
        if (pricing) {
            console.log(`✅ Found pricing:`, pricing);
            return pricing;
        } else {
            console.warn(`⚠️ No pricing found for plan: ${planId}`);
            return null;
        }
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
