/**
 * Centralized Webhook Manager for 1wellness
 * Manages all API endpoints, payment keys, and configuration
 * Dynamically resolves relative paths for portable subdomains/folders
 */

class WebhookManager {
    constructor() {
        try {
            const path = window.location.pathname.toLowerCase();
            const folders = ['/pcos/', '/acne/', '/weight/', '/mens/', '/egbon/', '/member/'];
            const isInSubfolder = folders.some(folder => path.includes(folder));
            const dynamicLocalBaseUrl = isInSubfolder ? '../backend/api' : './backend/api';

            this.config = {
                baseUrl: 'https://n8n.ai20.city',
                localBaseUrl: dynamicLocalBaseUrl,
                fallbackBaseUrl: 'https://n8n.ai20.city',
                endpoints: {
                    productConfig: '/webhook/product-config',
                    flutterwaveKeys: '/webhook/flutterwave-keys',
                    pricing: '/webhook/pricing',
                    pcosLeads: '/webhook/pcos-leads',
                    salesLeads: '/webhook/sales-leads',
                    acneLeads: '/webhook/acne-leads',
                    weightLeads: '/webhook/weight-leads',
                    testimonials: '/webhook/testimonials',
                    conversionTracking: '/webhook/conversion-tracking',
                    processPayment: '/webhook/process-payment',
                    pcosAssessment: '/webhook/pcos-assessment',
                    pcosSalesVisit: '/webhook/pcos-sales-visit',
                    pcosPurchaseIntent: '/webhook/pcos-purchase-intent',
                    pcosPurchase: '/webhook/pcos-purchase',
                    egbonAssessment: '/webhook/egbon-assessment',
                    egbonSalesVisit: '/webhook/egbon-sales-visit',
                    egbonPurchaseIntent: '/webhook/egbon-purchase-intent',
                    egbonPurchase: '/webhook/egbon-purchase'
                },
                payment: {
                    flutterwavePublicKey: null,
                    flutterwaveSecretKey: null,
                    pricing: {}
                },
                loaded: false,
                errors: []
            };

            this.sessionId = this.getSessionId();
            this.config.loaded = true;
            console.log('✅ WebhookManager initialized with Session:', this.sessionId, 'and localBaseUrl:', this.config.localBaseUrl);

            // Auto-fetch and apply pricing
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.autoFetchPricing());
            } else {
                setTimeout(() => this.autoFetchPricing(), 100);
            }

        } catch (error) {
            console.error('❌ WebhookManager init failed:', error);
            this.config = this.getFailsafeConfig();
        }
    }

    getSessionId() {
        let sid = localStorage.getItem('1w_session_id');
        if (!sid) {
            sid = '1w_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now().toString(36);
            localStorage.setItem('1w_session_id', sid);
        }
        return sid;
    }

    getFailsafeConfig() {
        const path = window.location.pathname.toLowerCase();
        const folders = ['/pcos/', '/acne/', '/weight/', '/mens/', '/egbon/', '/member/'];
        const isInSubfolder = folders.some(folder => path.includes(folder));
        const dynamicLocalBaseUrl = isInSubfolder ? '../backend/api' : './backend/api';

        return {
            baseUrl: 'https://n8n.ai20.city',
            localBaseUrl: dynamicLocalBaseUrl,
            fallbackBaseUrl: 'https://n8n.ai20.city',
            endpoints: {},
            payment: {},
            loaded: false,
            errors: ['Init failed']
        };
    }

    getUrl(endpointKey) {
        if (!this.config.endpoints[endpointKey]) {
            console.warn(`❌ Unknown endpoint: ${endpointKey}`);
            return null;
        }
        return `${this.config.baseUrl}${this.config.endpoints[endpointKey]}`;
    }

    getCurrentFunnel() {
        const path = window.location.pathname;
        if (path.includes('mens')) return 'mens';
        if (path.includes('pcos')) return 'pcos';
        if (path.includes('acne')) return 'acne';
        if (path.includes('weight')) return 'weight';
        return 'pcos';
    }

    /**
     * Submit to Local Backend - Non-blocking background save
     */
    async _submitToLocal(formType, data) {
        try {
            const payload = { ...data, form_type: formType };
            console.log(`💾 Saving ${formType} to local DB...`);

            const response = await fetch(`${this.config.localBaseUrl}/form-handler.php`, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            });

            if (response.ok) {
                const result = await response.json();
                if (result.success) {
                    console.log(`✅ Saved ${formType} to local DB`);
                    return { success: true, message: result.message };
                } else {
                    throw new Error(result.error || result.message || 'Operation failed');
                }
            }
            
            const errorData = await response.json().catch(() => ({}));
            throw new Error(errorData.error || `HTTP ${response.status}`);
        } catch (error) {
            console.error(`⚠️ Local save failed:`, error.message);
            return { success: false, error: error.message };
        }
    }

    async submitForm(endpointKey, formData, options = {}) {
        const url = this.getUrl(endpointKey);
        if (!url) throw new Error(`Invalid endpoint: ${endpointKey}`);

        const requestOptions = {
            method: 'POST',
            body: formData,
            ...options
        };

        if (!(formData instanceof FormData) && !requestOptions.headers) {
            requestOptions.headers = { 'Content-Type': 'application/json' };
        }

        const response = await fetch(url, requestOptions);

        if (!response.ok) {
            let errorMessage = `HTTP ${response.status}`;
            try {
                const errorData = await response.json();
                if (errorData.error) errorMessage = errorData.error;
            } catch (e) { }
            throw new Error(errorMessage);
        }

        return await response.json();
    }

    /**
     * Submit Assessment - Resilient to N8N failures
     */
    async submitAssessment(assessmentData) {
        const funnel = this.getCurrentFunnel();
        const n8nData = {
            ...assessmentData,
            timestamp: new Date().toISOString(),
            userAgent: navigator.userAgent,
            url: window.location.href,
            source: `${funnel}-funnel`
        };

        const localData = {
            email: assessmentData.contactInfo?.email || '',
            name: assessmentData.contactInfo?.name || '',
            phone: assessmentData.contactInfo?.phone || '',
            assessment_data: assessmentData.answers || assessmentData,
            assessment_type: funnel,
            url: window.location.href
        };

        let endpointKey = `${funnel}Assessment`;
        if (!this.config.endpoints[endpointKey]) {
            endpointKey = 'pcosAssessment';
        }

        const n8nPromise = this.submitForm(endpointKey, JSON.stringify(n8nData), {
            headers: { 'Content-Type': 'application/json' }
        }).then(result => {
            console.log('✅ Assessment sent to N8N');
            return { success: true, result };
        }).catch(error => {
            console.warn('⚠️ N8N submission failed:', error.message);
            return { success: false, error };
        });

        const localPromise = this._submitToLocal('assessment', localData)
            .then(result => {
                if (result.success) {
                    console.log('✅ Assessment saved locally');
                    return { success: true };
                }
                console.error('⚠️ Local save failed:', result.error);
                return { success: false, error: result.error };
            })
            .catch(error => {
                console.error('⚠️ Local save failed with error:', error);
                return { success: false, error: error.message || error };
            });

        const [n8nResult, localResult] = await Promise.all([n8nPromise, localPromise]);

        if (!n8nResult.success && !localResult.success) {
            const combinedError = localResult.error || n8nResult.error?.message || 'Assessment submission failed on all channels.';
            throw new Error(combinedError);
        }

        return n8nResult.success ? n8nResult.result : { success: true, local_only: true };
    }

    /**
     * Confirm Purchase - Resilient
     */
    async confirmPurchase(purchaseData) {
        // Try N8N
        let n8nResult = null;
        try {
            const funnel = this.getCurrentFunnel();
            // CRITICAL: Call local webhook to create user account immediately (for ALL funnels)
            const localWebhookMap = {
                'pcos': 'webhook_pcos.php',
                'acne': 'webhook_acne.php',
                'weight': 'webhook_weight.php',
                'mens': 'webhook_mens.php'
            };
            const webhookFile = localWebhookMap[funnel] || 'webhook_pcos.php';

            console.log(`🚀 Triggering direct ${funnel} account creation...`);

            const localPayload = {
                ...purchaseData,
                email: purchaseData.customer?.email || '',
                name: purchaseData.customer?.name || '',
                order_id: purchaseData.transactionId,
                // A/B engine: attribute the purchase to this session's variant assignments
                session_id: (window.AB && window.AB.sessionId) || this.sessionId,
                funnel: funnel,
            };

            // Add funnel-specific assessment data from localStorage
            const localStorageKeys = {
                'pcos': 'pcosAssessmentData',
                'acne': 'acneAssessmentData',
                'weight': 'weightAssessmentData',
                'mens': 'mensAssessmentData'
            };
            const assessmentKey = localStorageKeys[funnel] || 'pcosAssessmentData';
            try {
                const ad = JSON.parse(localStorage.getItem(assessmentKey) || '{}');
                localPayload.assessment_data = ad;
                // Also add the type field the backend expects
                localPayload[`${funnel}_type`] = ad[`${funnel}Type`]?.primary || localStorage.getItem(`${funnel}Type`) || 'General';
            } catch(e) {}

            // Call local webhook directly
            const webhookResponse = await fetch(`${this.config.localBaseUrl}/${webhookFile}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(localPayload)
            });

            if (webhookResponse.ok) {
                const result = await webhookResponse.json();
                console.log(`✅ ${funnel} Account Created:`, result);
                return result; // Return this result as it contains credentials
            }

            const n8nData = {
                ...purchaseData,
                timestamp: new Date().toISOString(),
                userAgent: navigator.userAgent,
                url: window.location.href,
                source: `${funnel}-funnel`
            };

            let endpointKey = `${funnel}Purchase`;
            if (!this.config.endpoints[endpointKey]) endpointKey = 'pcosPurchase';

            n8nResult = await this.submitForm(endpointKey, JSON.stringify(n8nData), {
                headers: { 'Content-Type': 'application/json' }
            });
        } catch (error) {
            console.warn('⚠️ N8N purchase confirmation failed:', error);
        }

        // Local save (background)
        try {
            const funnel = this.getCurrentFunnel();
            const localData = {
                email: purchaseData.customer?.email || '',
                name: purchaseData.customer?.name || '',
                phone: purchaseData.customer?.phone || '',
                product_type: funnel,
                product_name: purchaseData.plan || '',
                amount: purchaseData.amount || 0,
                currency: purchaseData.currency || 'USD',
                transaction_id: purchaseData.transactionId || '',
                tx_ref: purchaseData.flutterwaveRef || '',
                payment_status: purchaseData.status || 'completed',
                url: window.location.href
            };
            await this._submitToLocal('sales', localData);
        } catch (localError) {
            console.error('⚠️ Local sales save failed:', localError);
        }

        return n8nResult || { success: true };
    }

    async trackSalesVisit(visitData) {
        return this._trackEvent('SalesVisit', visitData);
    }

    async trackPurchaseIntent(intentData) {
        return this._trackEvent('PurchaseIntent', intentData);
    }

    async trackPurchaseFailed(failureData) {
        return this._trackEvent('PurchaseFailed', failureData);
    }

    async trackAbandonment(abandonmentData) {
        return this._trackEvent('PurchaseAbandonment', abandonmentData);
    }

    async trackConversion(eventType, eventData) {
        return this._trackEvent('conversionTracking', { eventType, eventData });
    }

    async _trackEvent(eventType, data) {
        try {
            const funnel = this.getCurrentFunnel();
            const trackingData = {
                ...data,
                timestamp: new Date().toISOString(),
                userAgent: navigator.userAgent,
                url: window.location.href,
                source: `${funnel}-funnel`
            };

            let endpointKey = eventType.includes('conversion') ? eventType : `${funnel}${eventType}`;
            if (!this.config.endpoints[endpointKey]) {
                endpointKey = `pcos${eventType}`;
            }

            // Determine the correct event type for local tracking
            let localEventType = 'view';
            if (eventType.toLowerCase().includes('purchase') || eventType.toLowerCase().includes('conversion')) {
                localEventType = 'conversion';
            } else if (eventType.toLowerCase().includes('intent')) {
                localEventType = 'intent';
            } else if (eventType.toLowerCase().includes('visit')) {
                localEventType = 'visit';
            }

            // Send to local tracking
            const funnelName = this.getCurrentFunnel();
            this._submitToLocal('tracking', {
                session_id: this.sessionId,
                email: data.email || data.contactInfo?.email || data.customer?.email || null,
                funnel_name: funnelName,
                step_name: eventType,
                event_type: localEventType,
                metadata: data,
                url: window.location.href
            }).catch(e => console.warn('Local tracking err', e));

            return await this.submitForm(endpointKey, JSON.stringify(trackingData), {
                headers: { 'Content-Type': 'application/json' }
            });
        } catch (error) {
            console.warn(`⚠️ Tracking failed (non-critical):`, error);
            return null;
        }
    }

    /**
     * Get payment config
     */
    async getPaymentConfig() {
        try {
            const response = await fetch(`${this.config.localBaseUrl}/get-pricing.php`);
            if (response.ok) {
                const data = await response.json();
                if (data.success && data.data.config?.flutterwavePublicKey) {
                    this.config.payment.flutterwavePublicKey = data.data.config.flutterwavePublicKey;
                    return {
                        success: true,
                        data: {
                            publicKey: data.data.config.flutterwavePublicKey,
                            flutterwavePublicKey: data.data.config.flutterwavePublicKey
                        }
                    };
                }
            }
        } catch (e) {
            console.warn('Local key fetch failed, trying N8N');
        }

        // Fallback to N8N
        try {
            const url = this.getUrl('flutterwaveKeys');
            const response = await fetch(url);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            let data = await response.json();
            if (Array.isArray(data) && data.length > 0) data = data[0];

            if (data.success && data.data) {
                this.config.payment.flutterwavePublicKey = data.data.publicKey;
                return {
                    success: true,
                    data: {
                        publicKey: data.data.publicKey,
                        flutterwavePublicKey: data.data.publicKey
                    }
                };
            }
        } catch (error) {
            console.error('❌ Payment config failed:', error);
        }

        return null;
    }

    async getFlutterwaveConfig() {
        return this.getPaymentConfig();
    }

    /**
     * Get pricing from local DB
     */
    async getPricing(productId) {
        try {
            const response = await fetch(`${this.config.localBaseUrl}/get-pricing.php`);
            if (!response.ok) throw new Error('Fetch failed');

            const data = await response.json();
            if (data.success && data.data.plans) {
                const plans = data.data.plans;

                // Search for plan
                for (const funnel in plans) {
                    for (const planId in plans[funnel]) {
                        if (planId === productId || `${funnel}-${planId}` === productId) {
                            return plans[funnel][planId];
                        }
                    }
                }
            }
            return null;
        } catch (error) {
            console.error('❌ Pricing fetch failed:', error);
            return null;
        }
    }

    /**
     * Auto-fetch and apply pricing with CONTEXT AWARENESS
     * Handles upsells, downsells, and cross-sells correctly
     */
    async autoFetchPricing() {
        const debugMode = window.location.search.includes('debug=true');
        this.log(debugMode, '🚀 Auto-Fetch Pricing Started');

        try {
            const funnel = this.getCurrentFunnel();
            this.log(debugMode, `📁 Detected Funnel: ${funnel}`);

            // 1. Fetch ALL plans for this funnel
            const plans = await this.getFunnelPlans(funnel);

            if (!plans) {
                this.log(debugMode, '❌ No plans fetched from API', 'error');
                return;
            }

            this.log(debugMode, `📦 Plans fetched: ${Object.keys(plans).join(', ')}`);

            // 2. Identify the "Default" plan for this page
            let defaultPlanId = '30-day';
            const path = window.location.pathname;

            if (funnel === 'egbon') {
                defaultPlanId = 'egbon-single';
            } else if (funnel === 'acne') {
                defaultPlanId = /90[-\s]?day/i.test(path) ? '90-day' : '30-day';
            } else {
                if (/90[-\s]?day/i.test(path)) defaultPlanId = '90-day';
            }

            this.log(debugMode, `🎯 Default plan ID: ${defaultPlanId}`);

            // 3. Create Price Map
            const priceMap = {};
            for (const [id, plan] of Object.entries(plans)) {
                priceMap[id] = {
                    ...plan,
                    formatted: parseInt(plan.price).toLocaleString()
                };
            }

            this.log(debugMode, `💰 Price Map: ${Object.keys(priceMap).map(k => `${k}: $${priceMap[k].formatted}`).join(', ')}`);

            // 4. Store default pricing for payment
            if (priceMap[defaultPlanId]) {
                window.currentPricing = priceMap[defaultPlanId];
            }

            // 5. Update Prices with Context Awareness
            const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
            const nodes = [];
            let node;
            while (node = walker.nextNode()) {
                // Check for Dollar/Pound/Euro/Naira symbols OR letters/digits
                if ((node.nodeValue.includes('$') || node.nodeValue.includes('£') || node.nodeValue.includes('€') || node.nodeValue.includes('₦')) && /\d/.test(node.nodeValue)) {
                    nodes.push(node);
                }
            }

            this.log(debugMode, `🔍 Found ${nodes.length} price nodes via TreeWalker`);
            let updateCount = 0;

            nodes.forEach((node, index) => {
                const parent = node.parentElement;
                const grandParent = parent.parentElement;
                const context = (parent.innerText + " " + (grandParent ? grandParent.innerText : "")).toLowerCase();

                // EXCLUSION 1: Skip elements managed by AlpineJS (x-text binding)
                if (parent.hasAttribute('x-text') || (grandParent && grandParent.hasAttribute('x-text'))) {
                    return; // Skip - Alpine is managing this
                }

                // EXCLUSION 2: Skip elements explicitly marked to not be updated
                if (parent.hasAttribute('data-price-static') || (grandParent && grandParent.hasAttribute('data-price-static'))) {
                    return; // Skip - marked as static
                }

                // EXCLUSION 3: Do not update "Value", "Save", or "Regular Price" fields
                const textToCheck = (node.nodeValue + " " + parent.innerText).toLowerCase();
                if (/value:|save:|regular price:/i.test(textToCheck)) {
                    if (!/your price today/i.test(textToCheck)) {
                        return; // Skip this node
                    }
                }

                let targetPlanId = defaultPlanId;

                // Context Heuristics
                if (/90[-\s]?day/i.test(context) || /complete plan/i.test(context)) {
                    targetPlanId = '90-day';
                } else if (/30[-\s]?day/i.test(context) || /starter plan/i.test(context)) {
                    targetPlanId = '30-day';
                }

                if (funnel === 'egbon') targetPlanId = 'egbon-single';

                if (priceMap[targetPlanId]) {
                    const newPrice = priceMap[targetPlanId].formatted;
                    const currencySymbol = window.FlutterwaveIntegration ? window.FlutterwaveIntegration.getSupportedCurrencies()[window.FlutterwaveIntegration.config.currency].symbol : '$';
                    
                    const regex = /[\$£€₦]\s*[\d,]+/;
                    if (regex.test(node.nodeValue)) {
                        const oldValue = node.nodeValue.match(regex)[0];
                        const oldNum = oldValue.replace(/[^\d]/g, '');
                        const newNum = newPrice.replace(/[^\d]/g, '');

                        if (oldNum !== newNum) {
                            const newValue = node.nodeValue.replace(regex, `${currencySymbol}${newPrice}`);
                            node.nodeValue = newValue;
                            parent.setAttribute('data-price-updated', 'true');
                            updateCount++;
                            this.log(debugMode, `✏️ Updated: ${oldValue} → ${currencySymbol}${newPrice} (plan: ${targetPlanId})`);
                        }
                    }
                }
            });

            this.log(debugMode, `✅ Price update complete: ${updateCount} prices updated`);

        } catch (error) {
            this.log(debugMode, `❌ Error: ${error.message}`, 'error');
        }
    }

    log(debug, message, type = 'info') {
        console[type](message);
        if (debug) {
            let logContainer = document.getElementById('webhook-debug-log');
            if (!logContainer) {
                logContainer = document.createElement('div');
                logContainer.id = 'webhook-debug-log';
                logContainer.style.cssText = 'position:fixed;bottom:0;left:0;right:0;height:200px;background:rgba(0,0,0,0.9);color:#0f0;font-family:monospace;font-size:12px;overflow-y:scroll;z-index:9999;padding:10px;pointer-events:none;';
                document.body.appendChild(logContainer);
            }
            const line = document.createElement('div');
            line.textContent = `[${new Date().toLocaleTimeString()}] ${message}`;
            if (type === 'error') line.style.color = '#f00';
            logContainer.appendChild(line);
            logContainer.scrollTop = logContainer.scrollHeight;
        }
    }

    async getFunnelPlans(funnelId) {
        try {
            const response = await fetch(`${this.config.localBaseUrl}/get-pricing.php`);
            if (!response.ok) return null;
            const data = await response.json();
            if (data.success && data.data.plans && data.data.plans[funnelId]) {
                return data.data.plans[funnelId];
            }
        } catch (e) { return null; }
        return null;
    }

    /**
     * Schedule nurture
     */
    async scheduleNurture(leadData) {
        try {
            const nurture = {
                email: leadData.email || '',
                name: leadData.name || '',
                phone: leadData.phone || '',
                pcos_type: leadData.pcosType || '',
                confidence: leadData.confidence || '',
                funnel: this.getCurrentFunnel(),
                assessment_completed_at: leadData.completedAt || new Date().toISOString(),
                sales_page_viewed_at: new Date().toISOString(),
                session_id: this.sessionId,
                source: `${this.getCurrentFunnel()}-funnel-nurture`
            };

            const nurtureKey = `1w_nurture_${nurture.email}`;
            if (localStorage.getItem(nurtureKey)) return;
            localStorage.setItem(nurtureKey, Date.now().toString());

            await this._submitToLocal('nurture_queue', nurture);

            const url = `${this.config.baseUrl}/webhook/pcos-nurture`;
            await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(nurture)
            }).catch(e => console.warn('N8N nurture failed', e));

            console.log('✅ Nurture sequence scheduled for:', nurture.email);
        } catch (error) {
            console.warn('⚠️ Nurture scheduling failed', error);
        }
    }

    scheduleAbandonmentNurture() {
        const assessmentData = localStorage.getItem('pcosAssessmentData');
        if (!assessmentData) return;

        try {
            const data = JSON.parse(assessmentData);
            if (!data.contactInfo?.email) return;

            const purchased = localStorage.getItem('1w_purchased');
            if (purchased) return;

            this.scheduleNurture({
                email: data.contactInfo.email,
                name: data.contactInfo.name,
                phone: data.contactInfo.phone,
                pcosType: data.pcosType?.primary || '',
                confidence: data.pcosType?.confidence || '',
                completedAt: data.completedAt
            });
        } catch (e) {
            console.warn('Abandonment nurture failed:', e);
        }
    }

    updatePriceDisplays(price) { }
}

// Initialize
window.WebhookManager = new WebhookManager();

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = WebhookManager;
}

console.log('🚀 WebhookManager ready');
