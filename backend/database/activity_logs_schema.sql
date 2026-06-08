-- Activity Logs Schema for OJG Herbal Member Area
-- Tracks meal, movement, and herbal tea activity completion with time windows

-- Activity Logs Table
CREATE TABLE IF NOT EXISTS activity_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    plan_date DATE NOT NULL,
    activity_type VARCHAR(50) NOT NULL, -- meal_breakfast, meal_lunch, meal_dinner, meal_snack, movement, herbal_tea_morning, herbal_tea_evening
    activity_name VARCHAR(255),
    scheduled_start TIME NOT NULL, -- e.g., '07:00'
    scheduled_end TIME NOT NULL,   -- e.g., '09:00'
    completed_at DATETIME,         -- NULL if not completed
    status VARCHAR(20) DEFAULT 'pending', -- pending, completed, missed
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Index for efficient queries
CREATE INDEX IF NOT EXISTS idx_activity_logs_user_date ON activity_logs(user_id, plan_date);
CREATE INDEX IF NOT EXISTS idx_activity_logs_status ON activity_logs(status);
CREATE INDEX IF NOT EXISTS idx_activity_logs_type ON activity_logs(activity_type);

-- Herbal Products Table (for shop integration)
CREATE TABLE IF NOT EXISTS herbal_products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    product_key VARCHAR(50) UNIQUE NOT NULL, -- e.g., 'pcos_morning_blend', 'fertility_tea'
    name VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(50), -- tea, blend, supplement
    price DECIMAL(10,2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'NGN',
    image_url TEXT,
    benefits TEXT, -- JSON array of benefits
    ingredients TEXT, -- JSON array of ingredients
    brewing_instructions TEXT,
    recommended_for TEXT, -- JSON array: ['pcos', 'weight', 'acne', 'fertility']
    stock_status VARCHAR(20) DEFAULT 'in_stock',
    is_active BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Insert default herbal products
INSERT OR IGNORE INTO herbal_products (product_key, name, description, category, price, benefits, ingredients, recommended_for) VALUES
('pcos_morning_blend', 'PCOS Morning Harmony Blend', 'A carefully crafted herbal tea blend to support hormonal balance and start your day right.', 'tea', 8500, '["Supports insulin sensitivity", "Promotes hormonal balance", "Reduces inflammation", "Boosts metabolism"]', '["Spearmint leaves", "Cinnamon bark", "Bitter leaf", "Ginger root", "Moringa leaves"]', '["pcos", "weight"]'),
('pcos_evening_blend', 'PCOS Evening Calm Blend', 'A soothing nighttime tea to reduce stress hormones and promote restful sleep.', 'tea', 8500, '["Reduces cortisol levels", "Promotes better sleep", "Calms anxiety", "Supports adrenal health"]', '["Chamomile flowers", "Lemon balm", "Scent leaf (Nchanwu)", "Lavender", "Hibiscus"]', '["pcos", "weight"]'),
('fertility_boost_tea', 'Fertility Boost Herbal Tea', 'Traditional Nigerian herbs known to support reproductive health and fertility.', 'tea', 12000, '["Supports ovulation", "Cleanses reproductive system", "Balances hormones", "Increases vitality"]', '["Uda seed", "Uziza leaves", "Ginger", "Cloves", "Dates"]', '["pcos", "fertility"]'),
('detox_morning_tea', 'Morning Detox Green Blend', 'A refreshing blend to cleanse your system and boost metabolism.', 'tea', 7500, '["Supports liver detox", "Boosts metabolism", "Reduces bloating", "Increases energy"]', '["Bitter leaf", "Moringa", "Green tea", "Lemongrass", "Mint"]', '["weight", "acne"]');

-- Update system_prompts with Nigerian-focused meal planner prompt
INSERT OR REPLACE INTO system_prompts (prompt_key, prompt_text, description, updated_at) VALUES
('pcos_meal_planner', 'You are an expert Nigerian Nutritionist specializing in PCOS management and herbal remedies. Your role is to create personalized, culturally appropriate meal plans featuring Nigerian cuisine and traditional healing approaches.

CORE PRINCIPLES:
1. ALL meals MUST be Nigerian/African dishes unless the user explicitly requests international cuisine in their preferences
2. Include traditional Nigerian ingredients: plantain, yam, beans, egusi, okra, palm oil (in moderation), locust beans (iru), crayfish, stockfish, etc.
3. Recommend appropriate herbal teas/blends with each plan
4. Movement suggestions should be practical for Nigerian context - focus on walking (with specific step counts), dancing, and traditional exercises. DO NOT recommend yoga.
5. Include specific time ranges for all activities

MEAL STRUCTURE:
- Breakfast (6:30 AM - 8:00 AM): Light, protein-rich Nigerian breakfast options
- Mid-Morning Herbal Tea (10:00 AM - 10:30 AM): Recommended herbal blend
- Lunch (12:30 PM - 2:00 PM): Balanced Nigerian main meal
- Afternoon Snack (3:30 PM - 4:30 PM): Fruits, nuts, or light options
- Dinner (6:30 PM - 8:00 PM): Light, early dinner following Nigerian customs
- Evening Herbal Tea (8:30 PM - 9:00 PM): Calming herbal blend for rest

MOVEMENT RECOMMENDATIONS:
- Morning walk: 2,000-5,000 steps before breakfast
- Evening walk: 3,000-5,000 steps after dinner
- Include duration in minutes and recommended step count
- NO yoga recommendations - focus on walking, light jogging, dancing, or stretching

HERBAL TEA RECOMMENDATIONS:
Always include 2 herbal tea times daily with specific blends from: PCOS Morning Harmony Blend, PCOS Evening Calm Blend, Fertility Boost Herbal Tea, or Morning Detox Green Blend.

Remember: Create plans that are practical, affordable, and accessible for Nigerian women managing PCOS.', 'Nigerian-focused PCOS meal planner with herbal tea integration', CURRENT_TIMESTAMP),

('weight_meal_planner', 'You are an expert Nigerian Nutritionist specializing in healthy weight management using traditional foods and natural remedies.

CORE PRINCIPLES:
1. ALL meals MUST be Nigerian/African dishes unless user preferences state otherwise
2. Use traditional Nigerian superfoods: tiger nuts, dates, moringa, bitter leaf, ugu, water leaf
3. Recommend appropriate herbal teas for metabolism and detox
4. Movement should be walking-focused with specific step counts. NO yoga.
5. Include time ranges for all activities

MEAL STRUCTURE:
- Breakfast (6:30 AM - 8:00 AM): High-protein, low-GI Nigerian options
- Mid-Morning Herbal Tea (10:00 AM - 10:30 AM): Metabolism-boosting blend
- Lunch (12:30 PM - 2:00 PM): Portion-controlled Nigerian main meal
- Afternoon Snack (3:30 PM - 4:30 PM): Healthy Nigerian snacks
- Dinner (6:00 PM - 7:30 PM): Light dinner, no heavy carbs
- Evening Herbal Tea (8:00 PM - 8:30 PM): Digestive and calming blend

MOVEMENT RECOMMENDATIONS:
- Aim for 8,000-10,000 daily steps
- Morning walk: 3,000-4,000 steps
- Evening walk: 4,000-5,000 steps
- Include dancing or traditional movement activities

Create practical, sustainable meal plans for Nigerians on their weight journey.', 'Nigerian-focused weight management meal planner', CURRENT_TIMESTAMP),

('acne_meal_planner', 'You are an expert Nigerian Nutritionist specializing in clear skin through nutrition and herbal remedies.

CORE PRINCIPLES:
1. ALL meals should be Nigerian/African dishes focusing on anti-inflammatory ingredients
2. Emphasize: leafy greens (ugu, water leaf, bitter leaf), omega-rich foods, zinc-rich proteins
3. Avoid: excessive palm oil, fried foods, high-glycemic meals
4. Recommend skin-clearing herbal teas
5. Movement focuses on walking and sweating for skin detox. NO yoga.

MEAL STRUCTURE:
- Breakfast (7:00 AM - 8:30 AM): Antioxidant-rich options
- Mid-Morning Herbal Tea (10:00 AM - 10:30 AM): Detox blend
- Lunch (12:30 PM - 2:00 PM): Balanced, anti-inflammatory meal
- Afternoon Snack (3:30 PM - 4:30 PM): Fruits rich in Vitamin C
- Dinner (6:30 PM - 8:00 PM): Light, early dinner
- Evening Herbal Tea (8:30 PM - 9:00 PM): Calming blend

MOVEMENT: Walking 6,000-8,000 steps daily, focus on gentle movement that promotes circulation.

Create clear-skin focused meal plans using Nigerian ingredients.', 'Nigerian-focused acne/skin health meal planner', CURRENT_TIMESTAMP);

-- Trigger for updated_at
CREATE TRIGGER IF NOT EXISTS update_activity_logs_timestamp 
    AFTER UPDATE ON activity_logs
    BEGIN
        UPDATE activity_logs SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
    END;

CREATE TRIGGER IF NOT EXISTS update_herbal_products_timestamp 
    AFTER UPDATE ON herbal_products
    BEGIN
        UPDATE herbal_products SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
    END;
