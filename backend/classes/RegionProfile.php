<?php
/**
 * RegionProfile — geo-adaptive localization for plan generation.
 *
 * Given a user's country (+ optional city), returns a structured profile
 * the prompt builder injects for localized meals, herbs, sourcing, and units.
 *
 * Source order: curated region pack → AI-generated profile (cached) for uncovered regions.
 * Herb *safety* rules are global; *which herbs* are suggested is local.
 */
class RegionProfile
{
    private $db;
    private $settings;

    /**
     * Curated region packs for top markets.
     * These are clinician/nutritionist-reviewable and higher quality.
     */
    private const CURATED_PACKS = [
        // Nigeria - default market
        'NG' => [
            'country' => 'Nigeria',
            'country_code' => 'NG',
            'climate_zone' => 'tropical',
            'measurement_system' => 'metric',
            'staple_foods' => [
                'Ofada rice',
                'Amala',
                'Efo Riro',
                'Jollof Rice',
                'Moi Moi',
                'Beans Porridge',
                'Pepper Soup',
                'Ukwa',
                'Abacha',
                'Yam Porridge',
                'Plantain',
                'Egusi soup',
                'Ogbono soup',
                'Banga soup',
                'Edikang Ikong',
                'Ewedu',
                'Okra soup',
                'Bitter Leaf soup',
                'Groundnut soup'
            ],
            'common_proteins' => [
                'Chicken',
                'Goat meat',
                'Beef',
                'Fish (Tilapia, Mackerel, Catfish)',
                'Eggs',
                'Locust beans (Iru)',
                'Cow liver',
                'Snail',
                'Stockfish'
            ],
            'locally_available_herbs' => [
                ['name' => 'Bitter Leaf', 'local_name' => 'Ewuro', 'use' => 'Blood sugar support, digestive'],
                ['name' => 'Fenugreek', 'local_name' => 'Ewedu seed', 'use' => 'Insulin sensitivity, hormonal balance'],
                ['name' => 'Turmeric', 'local_name' => 'Ata-ile pupa', 'use' => 'Anti-inflammatory'],
                ['name' => 'Ginger', 'local_name' => 'Ata-ile', 'use' => 'Digestive, anti-inflammatory'],
                ['name' => 'Moringa', 'local_name' => 'Zogale', 'use' => 'Nutrient-dense, anti-inflammatory'],
                ['name' => 'Scent Leaf', 'local_name' => 'Efirin', 'use' => 'Digestive, antimicrobial'],
                ['name' => 'Bitter Kola', 'local_name' => 'Orogbo', 'use' => 'Appetite suppressant, antimicrobial'],
                ['name' => 'Garlic', 'local_name' => 'Ata-takali', 'use' => 'Immune support, cardiovascular'],
            ],
            'where_to_source' => 'Local markets (Balogun market, Mile 12), herbal pharmacies (Agbo sellers), health food stores, supermarkets (Shoprite, Spar)',
            'typical_cost_band' => 'NGN - Nigerian Naira',
            'dietary_norms' => 'Heavy use of palm oil, peppers, tomatoes. Starchy staples common. Large portions typical.',
            'language_for_local_names' => 'Yoruba/Igbo/Hausa (English widely understood)',
        ],

        // Kenya
        'KE' => [
            'country' => 'Kenya',
            'country_code' => 'KE',
            'climate_zone' => 'tropical',
            'measurement_system' => 'metric',
            'staple_foods' => [
                'Ugali',
                'Sukuma wiki (collard greens)',
                'Githeri',
                'Rice',
                'Chapati',
                'Mukimo',
                'Irio',
                'Matoke',
                'Uji (porridge)',
                'Wali (coconut rice)',
                'Maharagwe (beans)',
                'Kachumbari',
                'Muthokoi'
            ],
            'common_proteins' => [
                'Beef',
                'Goat',
                'Chicken',
                'Tilapia',
                'Beans',
                'Ndengu (green grams)',
                'Eggs',
                'Mursik (fermented milk)'
            ],
            'locally_available_herbs' => [
                ['name' => 'Moringa', 'local_name' => 'Moringa', 'use' => 'Nutrient-dense, energy support'],
                ['name' => 'Neem', 'local_name' => 'Muarobaini', 'use' => 'Blood purification, antimicrobial'],
                ['name' => 'Aloe Vera', 'local_name' => ' Korosho', 'use' => 'Digestive, skin healing'],
                ['name' => 'Lemongrass', 'local_name' => 'Mchaichai', 'use' => 'Digestive, calming'],
                ['name' => 'Hibiscus', 'local_name' => 'Bissap', 'use' => 'Blood pressure, antioxidant'],
                ['name' => 'Ginger', 'local_name' => 'Tangawizi', 'use' => 'Digestive, anti-inflammatory'],
                ['name' => 'Garlic', 'local_name' => 'Kitunguu saumu', 'use' => 'Immune support'],
                ['name' => 'Turmeric', 'local_name' => 'Manjano', 'use' => 'Anti-inflammatory'],
            ],
            'where_to_source' => 'Local markets (Marikiti, Gikomba), pharmacies, health shops, supermarkets (Naivas, Quickmart)',
            'typical_cost_band' => 'KES - Kenyan Shilling',
            'dietary_norms' => 'Ugali as staple with vegetables. Tea culture strong. Beef and goat common.',
            'language_for_local_names' => 'Swahili/English',
        ],

        // Serbia
        'RS' => [
            'country' => 'Serbia',
            'country_code' => 'RS',
            'climate_zone' => 'continental',
            'measurement_system' => 'metric',
            'staple_foods' => [
                'Whole-grain bread',
                'Beans (pasulj)',
                'Cabbage',
                'Peppers',
                'Yogurt/Kajmak',
                'Plums',
                'Walnuts',
                'Freshwater fish',
                'Cornmeal (proja)',
                'Potatoes',
                'Beetroot',
                'Sauerkraut (kiseli kupus)',
                'Ajvar',
                'Kajmak'
            ],
            'common_proteins' => [
                'Chicken',
                'Pork',
                'Freshwater fish',
                'Eggs',
                'Legumes',
                'Beef',
                'Lamb',
                'Dairy products'
            ],
            'locally_available_herbs' => [
                ['name' => 'Nettle', 'local_name' => 'kopriva', 'use' => 'Anti-inflammatory, mineral-rich, blood building'],
                ['name' => 'Chamomile', 'local_name' => 'kamilica', 'use' => 'Calming, sleep, digestive'],
                ['name' => 'St Johns Wort', 'local_name' => 'kantarion', 'use' => 'Mood support — FLAG: major drug interactions'],
                ['name' => 'Yarrow', 'local_name' => 'hajdučka trava', 'use' => 'Cycle support, anti-inflammatory'],
                ['name' => 'Peppermint', 'local_name' => 'nana', 'use' => 'Digestive, calming'],
                ['name' => 'Linden', 'local_name' => 'lipa', 'use' => 'Calming, sleep support'],
                ['name' => 'Wild Thyme', 'local_name' => 'majčina dušica', 'use' => 'Respiratory, antimicrobial'],
                ['name' => 'Dandelion', 'local_name' => 'maslačak', 'use' => 'Liver support, digestive'],
            ],
            'where_to_source' => 'Green markets (pijaca), apoteka (herbal pharmacies), health food shops (prodavnica zdrave hrane)',
            'typical_cost_band' => 'RSD - Serbian Dinar',
            'dietary_norms' => 'Pork common; large dairy presence; Orthodox fasting periods (vegan periods). Heavy winter cuisine.',
            'language_for_local_names' => 'Serbian',
        ],

        // Germany
        'DE' => [
            'country' => 'Germany',
            'country_code' => 'DE',
            'climate_zone' => 'temperate',
            'measurement_system' => 'metric',
            'staple_foods' => [
                'Whole-grain bread (Vollkornbrot)',
                'Potatoes',
                'Oats',
                'Rye',
                'Spelt',
                'Cabbage',
                'Kale',
                'Beets',
                'Apples',
                'Berries',
                'Legumes',
                'Quark',
                'Sauerkraut',
                'Muesli'
            ],
            'common_proteins' => [
                'Chicken',
                'Fish (salmon, trout)',
                'Eggs',
                'Legumes',
                'Tofu',
                'Beef',
                'Pork',
                'Dairy products'
            ],
            'locally_available_herbs' => [
                ['name' => 'Nettle', 'local_name' => 'Brennnessel', 'use' => 'Mineral-rich, anti-inflammatory'],
                ['name' => 'Chamomile', 'local_name' => 'Kamille', 'use' => 'Calming, digestive'],
                ['name' => 'Peppermint', 'local_name' => 'Pfefferminze', 'use' => 'Digestive, headache'],
                ['name' => 'Lemon Balm', 'local_name' => 'Zitronenmelisse', 'use' => 'Calming, sleep'],
                ['name' => 'St Johns Wort', 'local_name' => 'Johanniskraut', 'use' => 'Mood — FLAG: major drug interactions'],
                ['name' => 'Valerian', 'local_name' => 'Baldrian', 'use' => 'Sleep, anxiety'],
                ['name' => 'Lady\'s Mantle', 'local_name' => 'Frauenmantel', 'use' => 'Cycle support'],
                ['name' => 'Yarrow', 'local_name' => 'Schafgarbe', 'use' => 'Digestive, cycle support'],
            ],
            'where_to_source' => 'Apotheke (pharmacies), Reformhaus (health food stores), Bioladen (organic shops), Drogerie (dm, Rossmann)',
            'typical_cost_band' => 'EUR - Euro',
            'dietary_norms' => 'Bread-centric cuisine. Growing vegetarian/vegan movement. Organic (Bio) widely available.',
            'language_for_local_names' => 'German',
        ],

        // USA
        'US' => [
            'country' => 'United States',
            'country_code' => 'US',
            'climate_zone' => 'varies',
            'measurement_system' => 'imperial',
            'staple_foods' => [
                'Brown rice',
                'Quinoa',
                'Sweet potatoes',
                'Oats',
                'Whole wheat',
                'Leafy greens',
                'Berries',
                'Avocados',
                'Nuts',
                'Legumes',
                'Salmon',
                'Chicken breast',
                'Greek yogurt'
            ],
            'common_proteins' => [
                'Chicken',
                'Turkey',
                'Salmon',
                'Tuna',
                'Eggs',
                'Legumes',
                'Tofu',
                'Greek yogurt',
                'Cottage cheese'
            ],
            'locally_available_herbs' => [
                ['name' => 'Ashwagandha', 'local_name' => 'Ashwagandha', 'use' => 'Adaptogen, stress support'],
                ['name' => 'Maca', 'local_name' => 'Maca', 'use' => 'Energy, hormonal balance'],
                ['name' => 'Red Raspberry Leaf', 'local_name' => 'Red Raspberry Leaf', 'use' => 'Cycle support, uterine tone'],
                ['name' => 'Vitex/Chaste Tree', 'local_name' => 'Vitex', 'use' => 'Hormonal balance'],
                ['name' => 'Black Cohosh', 'local_name' => 'Black Cohosh', 'use' => 'Hormonal support'],
                ['name' => 'Milk Thistle', 'local_name' => 'Milk Thistle', 'use' => 'Liver support'],
                ['name' => 'Dandelion Root', 'local_name' => 'Dandelion Root', 'use' => 'Liver, digestive'],
                ['name' => 'Spearmint', 'local_name' => 'Spearmint', 'use' => 'Anti-androgen (for hormonal acne/PCOS)'],
            ],
            'where_to_source' => 'Whole Foods, Trader Joe\'s, Sprouts, Amazon, iHerb, local health food stores, pharmacies (CVS, Walgreens)',
            'typical_cost_band' => 'USD - US Dollar',
            'dietary_norms' => 'Diverse cuisine. Strong health food movement. Wide availability of international ingredients.',
            'language_for_local_names' => 'English',
        ],

        // Philippines
        'PH' => [
            'country' => 'Philippines',
            'country_code' => 'PH',
            'climate_zone' => 'tropical',
            'measurement_system' => 'metric',
            'staple_foods' => [
                'Rice',
                'Mongo (mung beans)',
                'Kangkong (water spinach)',
                'Malunggay',
                'Sayote',
                'Upo',
                'Kalabasa (squash)',
                'Sweet potato',
                'Taro (gabi)',
                'Banana (saba)',
                'Jackfruit',
                'Coconut'
            ],
            'common_proteins' => [
                'Chicken',
                'Pork',
                'Fish (bangus, tilapia)',
                'Shrimp',
                'Tofu',
                'Eggs',
                'Mongo beans'
            ],
            'locally_available_herbs' => [
                ['name' => 'Moringa', 'local_name' => 'Malunggay', 'use' => 'Nutrient-dense, energy, lactation support'],
                ['name' => 'Ginger', 'local_name' => 'Luya', 'use' => 'Digestive, anti-inflammatory, warming'],
                ['name' => 'Turmeric', 'local_name' => 'Luyang Dilaw', 'use' => 'Anti-inflammatory, antioxidant'],
                ['name' => 'Lemongrass', 'local_name' => 'Tanglad', 'use' => 'Digestive, calming'],
                ['name' => 'Sambong', 'local_name' => 'Sambong', 'use' => 'Kidney support, diuretic'],
                ['name' => 'Ampalaya', 'local_name' => 'Ampalaya', 'use' => 'Blood sugar support (bitter melon)'],
                ['name' => 'Guyabano', 'local_name' => 'Guyabano', 'use' => 'Immune support'],
                ['name' => 'Calamansi', 'local_name' => 'Calamansi', 'use' => 'Vitamin C, immune support'],
            ],
            'where_to_source' => 'Wet markets (palengke), Mercury Drug, Watsons, Healthy Options, supermarkets (SM, Robinsons)',
            'typical_cost_band' => 'PHP - Philippine Peso',
            'dietary_norms' => 'Rice-centric. Vinegar and fish sauce common. Fried foods popular. Growing health consciousness.',
            'language_for_local_names' => 'Filipino/Tagalog',
        ],

        // Brazil
        'BR' => [
            'country' => 'Brazil',
            'country_code' => 'BR',
            'climate_zone' => 'tropical',
            'measurement_system' => 'metric',
            'staple_foods' => [
                'Rice and beans',
                'Cassava (mandioca)',
                'Corn',
                'Plantains',
                'Açaí',
                'Tropical fruits',
                'Collard greens (couve)',
                'Okra',
                'Pumpkin'
            ],
            'common_proteins' => [
                'Chicken',
                'Beef',
                'Fish',
                'Black beans',
                'Chickpeas',
                'Eggs',
                'Pork'
            ],
            'locally_available_herbs' => [
                ['name' => 'Spearmint', 'local_name' => 'Hortelã', 'use' => 'Digestive, calming'],
                ['name' => 'Boldo', 'local_name' => 'Boldo', 'use' => 'Liver, digestive'],
                ['name' => 'Carqueja', 'local_name' => 'Carqueja', 'use' => 'Digestive, liver support'],
                ['name' => 'Espinheira-santa', 'local_name' => 'Espinheira-santa', 'use' => 'Stomach, digestive'],
                ['name' => 'Passionflower', 'local_name' => 'Maracujá (leaves)', 'use' => 'Calming, sleep'],
                ['name' => 'Guaco', 'local_name' => 'Guaco', 'use' => 'Respiratory, anti-inflammatory'],
                ['name' => 'Ginger', 'local_name' => 'Gengibre', 'use' => 'Digestive, warming'],
                ['name' => 'Turmeric', 'local_name' => 'Cúrcuma', 'use' => 'Anti-inflammatory'],
            ],
            'where_to_source' => 'Farmácias de manipulação (compounding pharmacies), lojas de produtos naturais, feiras (street markets), supermarkets',
            'typical_cost_band' => 'BRL - Brazilian Real',
            'dietary_norms' => 'Rice and beans daily. Heavy use of tropical fruits. Feijoada traditional. Growing vegan movement.',
            'language_for_local_names' => 'Portuguese',
        ],
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->settings = Settings::getInstance();
    }

    /**
     * Resolve a region profile for a user.
     *
     * @param array $user User record with country_code, region_city, etc.
     * @return array Region profile data
     */
    public function resolve(array $user): array
    {
        $countryCode = strtoupper($user['country_code'] ?? '');
        $regionCity = $user['region_city'] ?? '';

        // Default to Nigeria if no location set
        if (empty($countryCode)) {
            $countryCode = 'NG';
        }

        // Try curated pack first
        if (isset(self::CURATED_PACKS[$countryCode])) {
            $profile = self::CURATED_PACKS[$countryCode];
            // Override measurement system if user has preference
            if (!empty($user['measurement_system'])) {
                $profile['measurement_system'] = $user['measurement_system'];
            }
            return $profile;
        }

        // Try cached AI-generated profile from database
        $cached = $this->getCachedProfile($countryCode, $regionCity);
        if ($cached) {
            return $cached;
        }

        // Generate new profile via AI
        return $this->generateProfile($countryCode, $regionCity);
    }

    /**
     * Get a cached profile from the database.
     */
    private function getCachedProfile(string $countryCode, string $regionCity): ?array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT profile_data FROM region_profiles
                 WHERE country_code = ? AND (region_city = ? OR region_city IS NULL)
                 ORDER BY region_city DESC LIMIT 1"
            );
            $stmt->execute([$countryCode, $regionCity]);
            $row = $stmt->fetch();

            if ($row && !empty($row['profile_data'])) {
                return json_decode($row['profile_data'], true);
            }
        } catch (Exception $e) {
            error_log("[RegionProfile] Cache lookup failed: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Generate a region profile via AI and cache it.
     */
    private function generateProfile(string $countryCode, string $regionCity): array
    {
        $countryName = $this->getCountryName($countryCode);

        $prompt = "Generate a region profile for $countryName" . ($regionCity ? " (specifically $regionCity)" : "") . ".
Return ONLY valid JSON with this exact structure:
{
  \"country\": \"$countryName\",
  \"country_code\": \"$countryCode\",
  \"climate_zone\": \"temperate|tropical|arid|continental|mediterranean\",
  \"measurement_system\": \"metric|imperial\",
  \"staple_foods\": [\"food1\", \"food2\", ...],
  \"common_proteins\": [\"protein1\", \"protein2\", ...],
  \"locally_available_herbs\": [
    {\"name\": \"English name\", \"local_name\": \"local language name\", \"use\": \"traditional use\"}
  ],
  \"where_to_source\": \"Where to buy herbs and health foods in this country\",
  \"typical_cost_band\": \"Currency code and name\",
  \"dietary_norms\": \"Brief description of typical dietary patterns\",
  \"language_for_local_names\": \"Primary language for local herb/food names\"
}

Include 10-15 staple foods, 6-8 common proteins, and 6-10 locally available medicinal herbs with their local names.
Focus on herbs that are traditionally used and locally available, not imported supplements.";

        try {
            $ai = new AIOrchestrator();
            $response = $ai->complete($prompt, [
                'max_tokens' => 2000,
                'temperature' => 0.3,
            ]);

            $json = $this->extractJson($response);
            $profile = json_decode($json, true);

            if (is_array($profile) && isset($profile['staple_foods'])) {
                // Cache the profile
                $this->cacheProfile($profile, 'ai_generated');
                return $profile;
            }
        } catch (Exception $e) {
            error_log("[RegionProfile] AI generation failed: " . $e->getMessage());
        }

        // Fallback to a generic profile
        return $this->getFallbackProfile($countryCode, $countryName);
    }

    /**
     * Cache a profile in the database.
     */
    private function cacheProfile(array $profile, string $source): void
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO region_profiles (country_code, country_name, region_city, climate_zone, measurement_system, profile_data, source)
                 VALUES (?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE profile_data = VALUES(profile_data), source = VALUES(source)"
            );
            $stmt->execute([
                $profile['country_code'] ?? '',
                $profile['country'] ?? '',
                $profile['region_city'] ?? null,
                $profile['climate_zone'] ?? null,
                $profile['measurement_system'] ?? 'metric',
                json_encode($profile),
                $source,
            ]);
        } catch (Exception $e) {
            error_log("[RegionProfile] Cache write failed: " . $e->getMessage());
        }
    }

    /**
     * Get a fallback profile when AI generation fails.
     */
    private function getFallbackProfile(string $countryCode, string $countryName): array
    {
        return [
            'country' => $countryName,
            'country_code' => $countryCode,
            'climate_zone' => 'unknown',
            'measurement_system' => 'metric',
            'staple_foods' => ['Local grains', 'Local vegetables', 'Local fruits', 'Legumes', 'Root vegetables'],
            'common_proteins' => ['Local meat', 'Fish', 'Eggs', 'Legumes', 'Dairy'],
            'locally_available_herbs' => [
                ['name' => 'Ginger', 'local_name' => 'local name', 'use' => 'Digestive, anti-inflammatory'],
                ['name' => 'Turmeric', 'local_name' => 'local name', 'use' => 'Anti-inflammatory'],
                ['name' => 'Garlic', 'local_name' => 'local name', 'use' => 'Immune support'],
                ['name' => 'Peppermint', 'local_name' => 'local name', 'use' => 'Digestive'],
            ],
            'where_to_source' => 'Local markets, pharmacies, health food stores',
            'typical_cost_band' => 'Local currency',
            'dietary_norms' => 'Traditional local cuisine',
            'language_for_local_names' => 'Local language',
            '_fallback' => true,
        ];
    }

    /**
     * Get country name from ISO code.
     */
    private function getCountryName(string $code): string
    {
        $names = [
            'NG' => 'Nigeria',
            'KE' => 'Kenya',
            'RS' => 'Serbia',
            'DE' => 'Germany',
            'US' => 'United States',
            'PH' => 'Philippines',
            'BR' => 'Brazil',
            'GB' => 'United Kingdom',
            'CA' => 'Canada',
            'AU' => 'Australia',
            'ZA' => 'South Africa',
            'GH' => 'Ghana',
            'IN' => 'India',
            'PK' => 'Pakistan',
            'MX' => 'Mexico',
            'AR' => 'Argentina',
            'CO' => 'Colombia',
            'EG' => 'Egypt',
            'FR' => 'France',
            'IT' => 'Italy',
            'ES' => 'Spain',
            'NL' => 'Netherlands',
            'PL' => 'Poland',
            'CZ' => 'Czech Republic',
            'RO' => 'Romania',
            'HU' => 'Hungary',
            'JP' => 'Japan',
            'KR' => 'South Korea',
            'CN' => 'China',
            'ID' => 'Indonesia',
            'TH' => 'Thailand',
            'VN' => 'Vietnam',
            'MY' => 'Malaysia',
            'SG' => 'Singapore',
        ];
        return $names[$code] ?? $code;
    }

    /**
     * Extract JSON from AI response.
     */
    private function extractJson(string $text): string
    {
        if (preg_match('/```(?:json)?\s*([\s\S]+?)\s*```/', $text, $m)) {
            return $m[1];
        }
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false) {
            return substr($text, $start, $end - $start + 1);
        }
        return $text;
    }

    /**
     * Get all curated region codes.
     */
    public static function getCuratedRegions(): array
    {
        return array_keys(self::CURATED_PACKS);
    }

    /**
     * Check if a region has a curated pack.
     */
    public static function hasCuratedPack(string $countryCode): bool
    {
        return isset(self::CURATED_PACKS[strtoupper($countryCode)]);
    }

    /**
     * Get herb safety data for a specific herb.
     */
    public function getHerbSafety(string $herbName): ?array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM herb_safety WHERE herb_name = ? LIMIT 1"
            );
            $stmt->execute([$herbName]);
            $row = $stmt->fetch();

            if ($row) {
                return [
                    'herb_name' => $row['herb_name'],
                    'scientific_name' => $row['scientific_name'],
                    'max_daily_dose_mg' => $row['max_daily_dose_mg'],
                    'pregnancy_safe' => (bool) $row['pregnancy_safe'],
                    'breastfeeding_safe' => (bool) $row['breastfeeding_safe'],
                    'common_interactions' => json_decode($row['common_interactions'] ?? '[]', true),
                    'contraindications' => json_decode($row['contraindications'] ?? '[]', true),
                    'warnings' => $row['warnings'],
                    'evidence_level' => $row['evidence_level'],
                ];
            }
        } catch (Exception $e) {
            error_log("[RegionProfile] Herb safety lookup failed: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Filter herbs by safety for a specific user profile.
     *
     * @param array $herbs List of herbs from region profile
     * @param array $userProfile User assessment data (pregnancy, medications, etc.)
     * @return array Filtered herbs that pass safety checks
     */
    public function filterHerbsBySafety(array $herbs, array $userProfile): array
    {
        $isPregnant = !empty($userProfile['pregnant'] ?? $userProfile['is_pregnant'] ?? false);
        $isBreastfeeding = !empty($userProfile['breastfeeding'] ?? $userProfile['is_breastfeeding'] ?? false);
        $medications = $userProfile['medications'] ?? $userProfile['current_medications'] ?? [];

        if (is_string($medications)) {
            $medications = array_map('trim', explode(',', $medications));
        }

        $safeHerbs = [];
        foreach ($herbs as $herb) {
            $herbName = $herb['name'] ?? '';
            $safety = $this->getHerbSafety($herbName);

            // If no safety data, include with a note
            if (!$safety) {
                $herb['_safety_note'] = 'Limited safety data available. Consult healthcare provider.';
                $safeHerbs[] = $herb;
                continue;
            }

            // Check pregnancy safety
            if ($isPregnant && !$safety['pregnancy_safe']) {
                continue; // Skip unsafe herbs
            }

            // Check breastfeeding safety
            if ($isBreastfeeding && !$safety['breastfeeding_safe']) {
                continue;
            }

            // Check medication interactions
            $hasInteraction = false;
            foreach ($medications as $med) {
                foreach ($safety['common_interactions'] as $interaction) {
                    if (stripos($med, $interaction) !== false || stripos($interaction, $med) !== false) {
                        $hasInteraction = true;
                        $herb['_interaction_warning'] = "May interact with $med";
                        break 2;
                    }
                }
            }

            // Include herb (with warning if interaction found)
            $safeHerbs[] = $herb;
        }

        return $safeHerbs;
    }
}