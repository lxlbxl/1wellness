/**
 * 1wellness Personalization Engine
 * Reads weightAssessmentData from localStorage and exposes rich, answer-driven
 * copy snippets for use across all post-assessment pages.
 *
 * Usage:
 *   const ctx = window.Personalization.getContext();
 *   // ctx.firstName, ctx.topInsights, ctx.statusMessages, etc.
 */

(function (window) {
    'use strict';

    // ─── Answer-to-copy maps ────────────────────────────────────────────────────

    const FAT_LOCATION = {
        belly:       { short: 'belly and waist', insight: 'Your weight accumulates mainly around your belly and waist — a hallmark of insulin and cortisol-driven metabolic disruption.' },
        hips_thighs: { short: 'hips and thighs', insight: 'Your weight settles in your hips and thighs — a classic estrogen-transition pattern that calorie cutting alone cannot shift.' },
        even_puffy:  { short: 'full body with puffiness', insight: 'Your weight is evenly distributed with puffiness — a signature of systemic inflammation causing your body to retain fluid and fat.' },
        upper_body:  { short: 'upper body and neck', insight: 'You carry weight in your upper body and neck — strongly associated with elevated cortisol and adrenal stress hormones.' }
    };

    const SLEEP = {
        waking_3am:   { short: 'waking at 3–4am wired', insight: 'You wake between 3–4am feeling wired — this is a cortisol spike, not insomnia. Your adrenals are misfiring in the early hours.' },
        night_sweats: { short: 'restless sleep with night sweats', insight: 'Your sleep is broken by warmth or night sweats — a clear hormonal transition signal disrupting your recovery cycle.' },
        exhausted:    { short: 'sleeping 8+ hours but waking tired', insight: 'You sleep long hours but wake exhausted — systemic inflammation is preventing your body from entering deep restorative sleep.' },
        good:         null
    };

    const ENERGY = {
        post_meal_crash: { short: 'energy crashes after meals', insight: 'You crash 1–2 hours after heavy meals — your blood sugar is spiking and crashing, a textbook insulin resistance pattern.' },
        tired_wired:     { short: '"tired but wired" pattern', insight: 'You\'re exhausted all day but hyperactive at bedtime — this cortisol see-saw is the hallmark of adrenal exhaustion.' },
        constant_fog:    { short: 'constant low energy and brain fog', insight: 'You experience constant brain fog and low energy — gut-driven systemic inflammation is suppressing your cognitive and metabolic function.' },
        stable:          null
    };

    const CRAVINGS = {
        sugar_carbs:   { short: 'sugar and carb cravings', insight: 'You crave sugar, bread, and carbs intensely — your cells are starved of glucose due to insulin resistance, triggering these urgent signals.' },
        salty_coffee:  { short: 'salt cravings and caffeine dependence', insight: 'You crave salty foods and rely on caffeine — your adrenal glands are depleted and using salt and stimulants to maintain output.' },
        comfort_dairy: { short: 'comfort food cravings', insight: 'You crave dairy and processed comfort foods — this often signals gut dysbiosis and hidden food sensitivities fuelling inflammation.' },
        none:          null
    };

    const DIGESTION = {
        bloating_gas:          { short: 'frequent bloating and gas', insight: 'You experience frequent bloating and gas — your gut lining is irritated, disrupting the microbiome that regulates your metabolism.' },
        reflux_sensitivities:  { short: 'acid reflux and food sensitivities', insight: 'Acid reflux and food sensitivities point to systemic digestive distress that is actively blocking fat oxidation.' },
        stress_digestion:      { short: 'digestion that worsens under stress', insight: 'Your digestion flares under stress — your HPA axis is directly impacting gut motility, creating a cortisol-gut feedback loop.' },
        normal:                null
    };

    const STRESS = {
        chronic:          'chronic stress or burnout',
        high_manageable:  'high but manageable stress',
        low_moderate:     'low to moderate daily stress',
        physical_stress:  'stress triggered by physical symptoms'
    };

    const AGE_LABELS = {
        under_35:         'Under 35',
        early_transition: '35–40',
        perimenopause:    '41–50',
        menopause:        '51+'
    };

    const GOAL_COPY = {
        belly_fat:       { label: 'reversing stubborn belly fat', action: 'reset your metabolism and flatten that midsection' },
        flashes_hormones:{ label: 'resolving hot flashes and hormonal symptoms', action: 'calm your hormonal transition and stop the night sweats' },
        gut_inflammation:{ label: 'calming inflammation and healing your gut', action: 'reduce bloating and repair your gut lining' },
        sleep_stress:    { label: 'restoring deep sleep and energy', action: 'reset your cortisol rhythm and reclaim restorative sleep' }
    };

    const DIET_RESPONSE = {
        no_effect:           'Calorie cutting and dieting have had zero effect on your weight',
        bloated_exhausted:   'Intense exercise leaves you exhausted, sore, and more bloated',
        rebound_midsection:  'You lose weight occasionally, but it rebounds straight to your belly',
        normal_response:     null
    };

    const INFLAMMATION = {
        joint_stiff:      'joint stiffness and morning puffiness',
        slow_recovery:    'extreme muscle soreness and slow recovery',
        skin_tags_dark:   'skin tags or dark patches — a metabolic warning sign',
        none:             null
    };

    const TYPE_NAMES = {
        insulin:      'Metabolic / Insulin-Resistant Weight Gain',
        inflammatory: 'Inflammatory / Gut-Driven Weight Retention',
        adrenal:      'Adrenal / Cortisol-Driven Weight Gain',
        transition:   'Hormonal Transition Weight Gain'
    };

    const TYPE_SHORT = {
        insulin:      'Metabolic',
        inflammatory: 'Inflammatory',
        adrenal:      'Adrenal',
        transition:   'Hormonal Transition'
    };

    const TYPE_EMOJI = {
        insulin:      '🔴',
        inflammatory: '🟠',
        adrenal:      '🟡',
        transition:   '🌸'
    };

    // Type-specific status messages for the generating page
    const TYPE_STATUS_MESSAGES = {
        insulin: (name) => [
            `Mapping ${name}'s insulin sensitivity patterns...`,
            'Designing your glucose-sequencing meal order...',
            'Identifying your blood sugar trigger foods...',
            'Building your insulin-reset herbal protocol...',
            'Structuring your metabolic muscle activation plan...',
            'Calibrating your 12-week glucose reset phases...',
            'Generating your body-data tracking workbook...',
            'Finalising your personalised PDF protocol...'
        ],
        inflammatory: (name) => [
            `Mapping ${name}'s gut inflammation markers...`,
            'Designing your elimination and rebuild protocol...',
            'Identifying your top inflammatory trigger foods...',
            'Building your anti-inflammatory botanical stack...',
            'Structuring your lymphatic flow movement plan...',
            'Calibrating your 12-week gut-repair phases...',
            'Generating your symptom-tracking workbook...',
            'Finalising your personalised PDF protocol...'
        ],
        adrenal: (name) => [
            `Mapping ${name}'s cortisol rhythm patterns...`,
            'Designing your cortisol-timed meal schedule...',
            'Engineering your 3am sleep-repair protocol...',
            'Building your adaptogen and adrenal herbal blend...',
            'Structuring your adrenal-safe movement plan...',
            'Calibrating your 12-week cortisol reset phases...',
            'Generating your energy and sleep tracking workbook...',
            'Finalising your personalised PDF protocol...'
        ],
        transition: (name) => [
            `Mapping ${name}'s hormonal transition phase...`,
            'Designing your estrogen-balancing meal plan...',
            'Identifying your vasomotor trigger patterns...',
            'Building your phytoestrogenic herbal protocol...',
            'Structuring your phase-synced movement plan...',
            'Calibrating your 12-week transition support phases...',
            'Generating your cycle and symptom tracking workbook...',
            'Finalising your personalised PDF protocol...'
        ]
    };

    // ─── Main context builder ────────────────────────────────────────────────────

    function getContext() {
        let raw = {};
        try {
            raw = JSON.parse(localStorage.getItem('weightAssessmentData') || '{}');
        } catch (e) { /* use empty object */ }

        const answers    = raw.answers    || {};
        const contact    = raw.contactInfo || {};
        const wt         = raw.weightType || {};
        const type       = wt.primary     || 'insulin';
        const confidence = wt.confidence  || 'medium';

        const firstName = (contact.name || '').split(' ')[0] || null;
        const ageRange  = contact.ageRange || answers[7] && AGE_LABELS[answers[7]] || null;

        // Build top "we noticed" insights from specific answers
        const insights = [];

        if (answers[2] && SLEEP[answers[2]]) {
            insights.push({ icon: '😴', text: SLEEP[answers[2]].insight });
        }
        if (answers[3] && ENERGY[answers[3]]) {
            insights.push({ icon: '⚡', text: ENERGY[answers[3]].insight });
        }
        if (answers[4] && CRAVINGS[answers[4]]) {
            insights.push({ icon: '🍽️', text: CRAVINGS[answers[4]].insight });
        }
        if (answers[0] && FAT_LOCATION[answers[0]]) {
            insights.push({ icon: '📍', text: FAT_LOCATION[answers[0]].insight });
        }
        if (answers[5] && DIGESTION[answers[5]]) {
            insights.push({ icon: '🌿', text: DIGESTION[answers[5]].insight });
        }
        if (answers[10] && DIET_RESPONSE[answers[10]]) {
            insights.push({ icon: '🔁', text: DIET_RESPONSE[answers[10]] + ' — this is not a willpower failure. It\'s a hormonal blockade.' });
        }

        // Build empathy line using the most specific symptom
        let empathyLine = 'You\'ve tried calorie counting, exercise plans, and generic advice — and nothing has worked. That\'s not a willpower problem.';
        if (answers[3] === 'tired_wired') {
            empathyLine = 'You\'re exhausted all day but can\'t sleep at night. You\'ve tried everything and the scale won\'t move. That\'s not lack of effort — it\'s your cortisol working against you.';
        } else if (answers[3] === 'post_meal_crash') {
            empathyLine = 'You crash hard after every meal. You\'ve cut calories and nothing changes. That\'s not a discipline issue — it\'s insulin resistance hijacking your metabolism.';
        } else if (answers[3] === 'constant_fog') {
            empathyLine = 'You wake up exhausted and spend the day in a fog. Diet changes haven\'t helped. That\'s because inflammation — not calories — is running the show in your body.';
        } else if (answers[1] === 'multiple_daily') {
            empathyLine = 'The hot flashes, the night sweats, the weight that won\'t move — you\'ve been dealing with this alone. It\'s not ageing. It\'s a hormonal transition your body needs specific support for.';
        }

        // Build personalised headline
        const typeName = TYPE_NAMES[type] || TYPE_NAMES.insulin;
        const typeShort = TYPE_SHORT[type] || 'Metabolic';
        const fatShort = answers[0] && FAT_LOCATION[answers[0]] ? FAT_LOCATION[answers[0]].short : null;
        const sleepShort = answers[2] && SLEEP[answers[2]] ? SLEEP[answers[2]].short : null;

        let customHeadline = firstName
            ? `${firstName}, your results confirm ${typeName}`
            : `Your results confirm ${typeName}`;

        // Sub-headline using 1-2 specific symptoms
        let symptomConfirmation = '';
        const syms = [sleepShort, fatShort].filter(Boolean);
        if (syms.length) {
            symptomConfirmation = 'Your ' + syms.join(' and ') + ' ' + (syms.length > 1 ? 'are' : 'is') + ' direct evidence of this.';
        }

        // Goal copy
        const goalKey  = answers[11] || 'belly_fat';
        const goalInfo = GOAL_COPY[goalKey] || GOAL_COPY.belly_fat;

        // Stress level
        const stressLevel = answers[6] && STRESS[answers[6]] ? STRESS[answers[6]] : null;

        // Status messages for generating-plan page
        const msgFn = TYPE_STATUS_MESSAGES[type] || TYPE_STATUS_MESSAGES.insulin;
        const statusMessages = msgFn(firstName || 'your');

        // Hero subtext for select-plan
        const selectPlanSubtext = (() => {
            const parts = [];
            if (fatShort) parts.push(`your ${fatShort} fat accumulation`);
            if (sleepShort) parts.push(sleepShort);
            if (answers[4] && CRAVINGS[answers[4]]) parts.push(CRAVINGS[answers[4]].short);
            const sympStr = parts.length > 1
                ? parts.slice(0, -1).join(', ') + ' and ' + parts[parts.length - 1]
                : parts[0] || 'your specific metabolic pattern';
            return `Built to address ${sympStr} — in two delivery formats.`;
        })();

        // Thank-you page protocol description
        const thankYouDescription = (() => {
            const desc = {
                insulin:      `Everything inside is calibrated around your insulin-resistant pattern — glucose-sequencing meals, berberine-based herbal rituals, and metabolic muscle activation built to break your blood sugar cycle.`,
                inflammatory: `Everything inside targets your gut-driven inflammation — an elimination protocol to remove triggers, anti-inflammatory botanical rituals, and lymphatic movement designed to drain the puffiness and rebuild your gut lining.`,
                adrenal:      `Everything inside is built around your cortisol pattern — cortisol-timed meals, adaptogen rituals that repair your ${sleepShort || '3am wake-up cycle'}, and adrenal-safe movement that won't spike your stress hormones.`,
                transition:   `Everything inside supports your hormonal transition — phytoestrogenic meals, estrogen-balancing herbal rituals, and phase-synced movement designed to calm your hot flashes and rebalance your metabolism.`
            };
            return desc[type] || desc.insulin;
        })();

        return {
            // Core identity
            firstName,
            ageRange,
            type,
            typeName,
            typeShort,
            typeEmoji: TYPE_EMOJI[type] || '🔴',
            confidence,

            // Answer-derived copy
            fatLocation:   answers[0] && FAT_LOCATION[answers[0]]   ? FAT_LOCATION[answers[0]].short   : null,
            sleepPattern:  answers[2] && SLEEP[answers[2]]           ? SLEEP[answers[2]].short           : null,
            energyPattern: answers[3] && ENERGY[answers[3]]          ? ENERGY[answers[3]].short          : null,
            cravingPattern:answers[4] && CRAVINGS[answers[4]]        ? CRAVINGS[answers[4]].short        : null,
            stressLevel,
            inflammationNote: answers[9] && INFLAMMATION[answers[9]] ? INFLAMMATION[answers[9]]          : null,

            // Composed copy blocks
            topInsights: insights.slice(0, 4),  // 4 most impactful
            empathyLine,
            customHeadline,
            symptomConfirmation,

            // Goal
            goalKey,
            goalLabel: goalInfo.label,
            goalAction: goalInfo.action,

            // Page-specific copy
            statusMessages,          // for generating-plan.html
            selectPlanSubtext,       // for select-plan.html
            thankYouDescription,     // for thank-you.html

            // Raw data for fallback access
            _raw: raw
        };
    }

    // ─── Export ─────────────────────────────────────────────────────────────────

    window.Personalization = { getContext };

})(window);
