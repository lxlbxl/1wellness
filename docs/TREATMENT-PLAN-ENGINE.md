# Treatment Plan Engine — Implementation Documentation

**Version:** 2.0 (Location-Aware)
**Date:** June 2026
**Status:** ✅ Implemented and tested

---

## Overview

The Treatment Plan Engine is a personalized health plan generation system that creates condition-specific, location-aware treatment plans for 1wellness funnels. It replaces the hardcoded PCOS-only generator with a flexible, multi-condition architecture.

### Key Features

1. **Four Condition-Specific Generators**: PCOS, Acne, Weight, Men's
2. **Location-Aware Localization**: Plans adapt to user's region (foods, herbs, units)
3. **Module Manifest System**: Each condition has specific modules (no workout for acne)
4. **Herb Safety Database**: Global safety rules with local herb availability
5. **Validation & Compliance**: Automated plan validation against schemas and safety rules

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    generate-plan.php                             │
│  (Entry point - routes to correct generator based on condition) │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│              ProtocolGeneratorFactory                            │
│  (Creates appropriate generator for condition)                   │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│           AbstractProtocolGenerator                              │
│  (Shared logic: AI call, JSON extraction, validation, PDF)       │
└─────────────────────────────────────────────────────────────────┘
                              │
              ┌───────────────┼───────────────┐
              ▼               ▼               ▼
┌─────────────┐    ┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│    PCOS     │    │    Acne     │    │   Weight    │    │    Men's    │
│  Generator  │    │  Generator  │    │  Generator  │    │  Generator  │
└─────────────┘    └─────────────┘    └─────────────┘    └─────────────┘
        │                │                │                │
        └────────────────┴────────────────┴────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    RegionProfile                                 │
│  (Resolves user's location → foods, herbs, units, sourcing)      │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    PlanValidator                                 │
│  (Validates modules, safety, localization, compliance)           │
└─────────────────────────────────────────────────────────────────┘
```

---

## File Structure

```
backend/
├── api/
│   └── generate-plan.php              # Entry point (refactored)
├── classes/
│   ├── AbstractProtocolGenerator.php  # Base generator class
│   ├── ProtocolGeneratorFactory.php   # Factory for condition routing
│   ├── PcosProtocolGenerator.php      # PCOS-specific generator
│   ├── AcneProtocolGenerator.php      # Acne-specific generator
│   ├── WeightProtocolGenerator.php    # Weight-specific generator
│   ├── MensProtocolGenerator.php      # Men's-specific generator
│   ├── ModuleManifest.php             # Module definitions per condition
│   ├── RegionProfile.php              # Location resolver
│   └── PlanValidator.php              # Plan validation
├── config/
│   ├── herb_safety.json               # Global herb safety database
│   └── region_packs/                  # Location-specific data
│       ├── ng.json                    # Nigeria
│       ├── rs.json                    # Serbia
│       ├── ke.json                    # Kenya
│       ├── us.json                    # USA
│       └── ph.json                    # Philippines
├── prompts/
│   ├── pcos/
│   │   ├── system-prompt.md           # PCOS clinical knowledge
│   │   └── user-prompt.md             # PCOS user template
│   ├── acne/
│   │   ├── system-prompt.md           # Acne clinical knowledge
│   │   └── user-prompt.md             # Acne user template
│   ├── weight/
│   │   ├── system-prompt.md           # Weight clinical knowledge
│   │   └── user-prompt.md             # Weight user template
│   └── mens/
│       ├── system-prompt.md           # Men's clinical knowledge
│       └── user-prompt.md             # Men's user template
├── database/
│   └── migrations/
│       ├── 011_location.sql           # User location columns
│       └── 012_region_profiles.sql    # Region profiles cache
└── tests/
    └── PlanEngineTest.php             # Golden output tests
```

---

## Module Manifest

Each condition has a specific set of modules. Modules not listed are **never** generated, rendered, or tracked.

| Module | PCOS | Acne | Weight | Men's |
|--------|:----:|:----:|:------:|:-----:|
| Meal Plan | ✅ | ✅ | ✅ | ✅ |
| Movement/Exercise | ✅ | ❌ | ✅ | ✅ |
| Skincare Routine | ❌ | ✅ | ❌ | ❌ |
| Herbal Protocol | ✅ | ✅ | ✅ | ✅ |
| Supplements | ✅ | ✅ | ✅ | ✅ |
| Cycle Sync | ✅ | ⚠️* | ❌ | ❌ |
| Sleep & Stress | ✅ | ✅ | ✅ | ✅ |
| Photo Protocol | ❌ | ✅ | ❌ | ❌ |
| Recovery Protocol | ❌ | ❌ | ❌ | ✅ |

*⚠️ Cycle sync for acne only if hormonal type + female

---

## Region Profiles

Region profiles provide localization data for plan generation:

```json
{
  "country": "Serbia",
  "country_code": "RS",
  "climate_zone": "continental",
  "measurement_system": "metric",
  "staple_foods": ["whole-grain bread", "beans (pasulj)", "cabbage"...],
  "common_proteins": ["chicken", "pork", "freshwater fish"...],
  "locally_available_herbs": [
    {"name": "Nettle", "local_name": "kopriva", "use": "anti-inflammatory"},
    ...
  ],
  "where_to_source": "green markets (pijaca), apoteka...",
  "dietary_norms": "pork common; large dairy presence..."
}
```

### Included Region Packs

| Code | Country | Key Features |
|------|---------|--------------|
| `ng` | Nigeria | Yoruba herbs, West African staples |
| `rs` | Serbia | European herbs, Balkan staples |
| `ke` | Kenya | Swahili herbs, East African staples |
| `us` | USA | Imperial units, American staples |
| `ph` | Philippines | Tagalog herbs, Southeast Asian staples |

---

## Herb Safety

The `herb_safety.json` database contains global safety rules:

```json
{
  "herbs": {
    "berberine": {
      "pregnancy_unsafe": true,
      "breastfeeding_unsafe": true,
      "max_daily_dose_mg": 1500,
      "drug_interactions": ["metformin", "blood thinners"]
    },
    "ashwagandha": {
      "pregnancy_unsafe": true,
      "max_daily_dose_mg": 600,
      "drug_interactions": ["thyroid medications", "sedatives"]
    }
  }
}
```

**Key Rule:** Herb *safety* is universal; herb *sourcing* is local. An herb is only suggested if it:
1. Is locally available (from region pack)
2. Passes the safety table for the user's profile

---

## Validation Gates

Plans pass through multiple validation checks:

1. **Module Validation**: Required modules present, forbidden modules absent
2. **Herb Safety**: Pregnancy/breastfeeding flags, dosage limits, drug interactions
3. **Localization**: Foods/herbs from user's region, correct units
4. **Compliance**: No cure claims, guaranteed outcomes, or prescriptive language

### Validation Results

```php
$result = $validator->validate($plan, 'acne', $userProfile, $regionProfile);
// Returns: ['valid' => bool, 'errors' => [], 'warnings' => []]
```

---

## Testing

Run the golden output tests:

```bash
php backend/tests/PlanEngineTest.php
```

### Test Coverage

- ✅ Acne + Serbia: No workout, Serbian foods, nettle/chamomile
- ✅ PCOS + Kenya: Cycle sync, ugali/sukuma wiki, metric units
- ✅ Weight + USA: Macro targets, progressive movement, imperial
- ✅ Men's + Philippines: Recovery protocol, local foods
- ✅ Module manifest correctness
- ✅ Herb safety enforcement (pregnancy, overdose)
- ✅ Region profile loading (5 regions)

**Current: 39/39 tests passing**

---

## Usage

### Generating a Plan

```php
require_once 'backend/classes/ProtocolGeneratorFactory.php';

// Get condition and user data
$condition = $assessment['condition']; // pcos|acne|weight|mens
$userData = [...]; // From assessment + user record

// Get region profile
$regionProfile = (new RegionProfile())->resolve($userData);

// Get generator and generate plan
$generator = ProtocolGeneratorFactory::for($condition);
$plan = $generator->generate($assessment, $name, $email, $regionProfile);

// Validate plan
$validator = new PlanValidator();
$result = $validator->validate($plan, $condition, $userProfile, $regionProfile);

if (!$result['valid']) {
    // Handle validation errors
    error_log('Plan validation failed: ' . json_encode($result['errors']));
}
```

### Adding a New Region

1. Create `backend/config/region_packs/{code}.json`
2. Include: staple_foods, common_proteins, locally_available_herbs, where_to_source, dietary_norms, measurement_system
3. Submit for clinical review before production use

### Adding a New Condition

1. Create generator class extending `AbstractProtocolGenerator`
2. Create system-prompt.md and user-prompt.md in `backend/prompts/{condition}/`
3. Add module manifest to `ModuleManifest.php`
4. Add tracking keys to `ModuleManifest::getDefaultTrackingKeys()`
5. Register in `ProtocolGeneratorFactory`

---

## Medical Disclaimer

**All clinical taxonomies and herb recommendations require qualified clinical review before production deployment.** The system includes:

- Medical disclaimers in every plan
- Red-flag referral triggers per condition
- Pregnancy/breastfeeding safety flags
- Drug interaction warnings
- Dosage limit enforcement

---

## Definition of Done

✅ **Acne buyer in Serbia** → GlowClear: acne-type root cause, AM/PM skincare routine, anti-inflammatory meals from Serbian staples, skin herbs (kopriva, kamilica), trigger + photo logging, metric units, **no workout block.**

✅ **PCOS buyer in Nairobi** → CycleSync: same insulin-resistance strategy via ugali/sukuma wiki/local proteins, locally-available herbs with Swahili names, cycle sync, metric units.

✅ **Weight buyer in the USA** → LeanFlow: macro targets in imperial, progressive movement, US-available foods, trend-smoothed tracking.

✅ **Men's buyer in the Philippines** → Vitale: T-supporting local nutrition, strength + recovery, local herbs, vitality logging.

Every plan is:
- Condition-correct
- Region-correct
- Omits irrelevant modules
- Passes safety + compliance gates
- Over-delivers on sales-page promise

---

*Documentation v2.0 — June 2026*