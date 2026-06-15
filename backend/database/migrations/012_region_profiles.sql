-- Migration 012: Region profiles table for geo-adaptive plan generation
-- Stores curated and AI-generated region profiles for localization

CREATE TABLE IF NOT EXISTS region_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    country_code CHAR(2) NOT NULL COMMENT 'ISO 3166-1 alpha-2',
    country_name VARCHAR(80) NOT NULL,
    region_city VARCHAR(120) NULL COMMENT 'Optional city/region for more specific profiles',
    climate_zone VARCHAR(40) NULL,
    measurement_system ENUM('metric','imperial') DEFAULT 'metric',
    profile_data JSON NOT NULL COMMENT 'Full region profile: staple_foods, herbs, sourcing, etc.',
    source ENUM('curated','ai_generated') DEFAULT 'ai_generated' COMMENT 'curated = human-reviewed, ai_generated = needs review',
    review_status ENUM('pending','reviewed','approved') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_country_region (country_code, region_city),
    INDEX idx_source (source),
    INDEX idx_review_status (review_status)
);

-- Master herb safety table - global rules that never vary by region
CREATE TABLE IF NOT EXISTS herb_safety (
    id INT AUTO_INCREMENT PRIMARY KEY,
    herb_name VARCHAR(100) NOT NULL,
    scientific_name VARCHAR(150) NULL,
    max_daily_dose_mg DECIMAL(10,2) NULL COMMENT 'Maximum safe daily dose in mg',
    pregnancy_safe TINYINT(1) DEFAULT 0 COMMENT '0=unsafe, 1=safe, 2=caution',
    breastfeeding_safe TINYINT(1) DEFAULT 0,
    common_interactions TEXT NULL COMMENT 'JSON array of drug interactions',
    contraindications TEXT NULL COMMENT 'JSON array of conditions where herb is contraindicated',
    warnings TEXT NULL COMMENT 'General safety warnings',
    evidence_level ENUM('strong','moderate','traditional','insufficient') DEFAULT 'traditional',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_herb_name (herb_name),
    INDEX idx_pregnancy_safe (pregnancy_safe)
);

-- Seed common herb safety data
INSERT INTO herb_safety (herb_name, scientific_name, max_daily_dose_mg, pregnancy_safe, breastfeeding_safe, contraindications, warnings) VALUES
('Berberine', 'Berberis vulgaris', 1500, 0, 0, '["diabetes medications", "blood pressure medications", "cyclosporine"]', 'May cause GI upset. Do not use in pregnancy.'),
('Ashwagandha', 'Withania somnifera', 600, 0, 0, '["thyroid medications", "immunosuppressants", "sedatives"]', 'May increase thyroid hormone levels. Avoid in hyperthyroidism.'),
('Inositol', NULL, 4000, 1, 1, '[]', 'Generally well-tolerated. May cause mild GI effects at high doses.'),
('DIM', 'Diindolylmethane', 300, 0, 0, '["hormone-sensitive conditions"]', 'May affect estrogen metabolism. Avoid in pregnancy.'),
('Zinc', NULL, 50, 1, 1, '["antibiotics (tetracyclines)", "penicillamine"]', 'Long-term high doses may cause copper deficiency.'),
('Omega-3', NULL, 3000, 1, 1, '["blood thinners"]', 'High doses may increase bleeding risk.'),
('Turmeric/Curcumin', 'Curcuma longa', 3000, 0, 0, '["blood thinners", "gallbladder disease"]', 'May increase bleeding risk. Avoid with gallstones.'),
('Vitex/Chaste Tree', 'Vitex agnus-castus', 400, 0, 0, '["hormone medications", "antipsychotics"]', 'Do not use with hormonal contraceptives or in pregnancy.'),
('Milk Thistle', 'Silybum marianum', 420, 0, 0, '["diabetes medications"]', 'May lower blood sugar. Monitor if diabetic.'),
('Probiotics', NULL, NULL, 1, 1, '["immunocompromised"]', 'Generally safe. Avoid in severely immunocompromised.'),
('Magnesium', NULL, 350, 1, 1, '["kidney disease", "certain antibiotics"]', 'High doses may cause diarrhea.'),
('Vitamin D', NULL, 4000, 1, 1, '["hypercalcemia"]', 'Toxicity possible at very high doses. Monitor blood levels.'),
('NAC', 'N-Acetyl Cysteine', 1800, 0, 0, '["nitroglycerin"]', 'May cause GI upset. Rare allergic reactions.'),
('Chromium', NULL, 1000, 0, 0, '["diabetes medications", "antacids"]', 'May enhance effects of diabetes medications.'),
('Fenugreek', 'Trigonella foenum-graecum', 6000, 0, 0, '["diabetes medications", "blood thinners"]', 'May lower blood sugar. Avoid in pregnancy (uterine stimulant).'),
('Spearmint', 'Mentha spicata', NULL, 0, 0, '[]', 'Anti-androgenic effects. Avoid in pregnancy.'),
('Nettle', 'Urtica dioica', NULL, 0, 0, '["blood thinners", "blood pressure medications", "diuretics"]', 'May affect blood sugar and blood pressure.'),
('Chamomile', 'Matricaria chamomilla', NULL, 0, 0, '["blood thinners", "sedatives"]', 'May cause allergic reactions in ragweed-sensitive individuals.'),
('St Johns Wort', 'Hypericum perforatum', 900, 0, 0, '["antidepressants", "birth control", "blood thinners", "cyclosporine", "HIV medications"]', 'MAJOR drug interactions. Reduces effectiveness of many medications.'),
('Yarrow', 'Achillea millefolium', NULL, 0, 0, '["blood thinners", "blood pressure medications"]', 'Uterine stimulant. Avoid in pregnancy.'),
('Neem', 'Azadirachta indica', NULL, 0, 0, '["immunosuppressants"]', 'Avoid in pregnancy. May affect fertility.'),
('Moringa', 'Moringa oleifera', NULL, 0, 0, '["thyroid medications", "blood pressure medications"]', 'Root bark may be toxic. Use leaf preparations only.'),
('Bitter Leaf', 'Vernonia amygdalina', NULL, 0, 0, '["diabetes medications"]', 'May lower blood sugar significantly.'),
('Bitter Kola', 'Garcinia kola', NULL, 0, 0, '[]', 'Limited safety data. Use cautiously.'),
('Holy Basil/Tulsi', 'Ocimum sanctum', NULL, 0, 0, '["blood thinners", "diabetes medications"]', 'May affect blood sugar and clotting.'),
('Ginger', 'Zingiber officinale', 4000, 0, 0, '["blood thinners", "diabetes medications"]', 'High doses may increase bleeding risk.'),
('Scent Leaf', 'Ocimum gratissimum', NULL, 0, 0, '[]', 'Limited safety data for concentrated forms.'),
('Dong Quai', 'Angelica sinensis', NULL, 0, 0, '["blood thinners", "hormone medications"]', 'Photosensitizing. Avoid in pregnancy and with bleeding disorders.'),
('Dandelion Root', 'Taraxacum officinale', NULL, 0, 0, '["diuretics", "lithium", "blood thinners"]', 'May affect blood sugar. Avoid with gallbladder issues.'),
('Rhodiola', 'Rhodiola rosea', 600, 0, 0, '["antidepressants", "blood pressure medications"]', 'May cause insomnia if taken late. Avoid in bipolar disorder.');