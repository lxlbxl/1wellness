# OJG Wellness - International Deployment (USD)

## Quick Start

This is a **standalone deployment package** for OJG Wellness (International/USD version). It can be moved to any server and will function independently.

## System Requirements

- PHP 7.4 or higher
- MySQL 5.7+ or SQLite 3
- Apache/Nginx web server
- PDO extension enabled

## Installation Steps

### Option 1: Web Installer (Recommended)

1. Upload all files to your web server
2. Navigate to `http://yourdomain.com/backend/`
3. The system will auto-initialize the SQLite database
4. Login to admin panel at `/backend/admin/`
5. Default credentials: `admin` / `admin123`

### Option 2: Manual SQLite Setup

1. Upload all files to your web server
2. Ensure `backend/database/` directory is writable (chmod 755)
3. Navigate to `/backend/admin/` to access admin panel

### Option 3: MySQL Setup

1. Create a MySQL database
2. Import the schema: `backend/database/schema_mysql.sql`
3. Create `backend/config/db_config.php` with your credentials:

```php
<?php
define('DB_TYPE', 'mysql');
define('DB_HOST', 'localhost');
define('DB_NAME', 'ojg_wellness');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('DB_PORT', '3306');
```

## File Structure

```
ojg-usd/
├── index.html              # Landing page
├── backend/                # PHP Backend & Admin Panel
│   ├── admin/              # Admin dashboard
│   ├── api/                # REST API endpoints
│   ├── classes/            # PHP classes
│   ├── config/             # Configuration files
│   ├── database/           # Database & schema
│   └── prompts/            # AI prompt templates
├── member/                 # Member Area
│   ├── login.php           # Member login
│   ├── index.php           # Member dashboard
│   ├── api/                # Member API
│   ├── components/         # Dashboard components
│   ├── css/                # Styles
│   └── js/                 # Scripts
├── pcos/                   # PCOS Funnel
├── acne/                   # Acne Funnel
├── weight/                 # Weight Loss Funnel
├── mens/                   # Men's Health Funnel
└── js/                     # Shared JavaScript
```

## Default Credentials

**Admin Panel:** `/backend/admin/`
- Username: `admin`
- Password: `admin123`

⚠️ **Change the default password immediately!**

## Funnels

| Funnel | URL Path | Currency |
|--------|----------|----------|
| PCOS | `/pcos/` | USD |
| Acne | `/acne/` | USD |
| Weight Loss | `/weight/` | USD |
| Men's Health | `/mens/` | USD |

## Pricing (USD Base)

| Product | Original | Sale | Discount |
|---------|----------|------|----------|
| PCOS 90-Day Plan | $197 | $97 | 51% |
| Acne Treatment | $147 | $67 | 54% |
| Weight Loss | $167 | $77 | 54% |
| Men's Vitality | $157 | $87 | 45% |

## Supported Currencies

Via Flutterwave:
- USD (US Dollar) - Default
- GBP (British Pound)
- EUR (Euro)
- CAD (Canadian Dollar)
- AUD (Australian Dollar)

## Configuration

### Payment Gateway

Edit `js/config.js` to update Flutterwave keys:

```javascript
payment: {
    flutterwave: {
        publicKey: "YOUR_LIVE_PUBLIC_KEY",
        environment: "production", // Change from "sandbox"
        defaultCurrency: "USD",
    }
}
```

### API Settings

Edit `backend/config/config.php`:

```php
define('N8N_API_KEY', 'your-n8n-api-key');
define('FROM_EMAIL', 'noreply@ojg-wellness.com');
```

### CORS Settings

Update allowed origins in `backend/config/config.php`:

```php
define('CORS_ALLOWED_ORIGINS', [
    'https://ojg-wellness.com',
    'https://www.ojg-wellness.com',
]);
```

## Post-Deployment Checklist

- [ ] Change admin password
- [ ] Update Flutterwave API keys (production)
- [ ] Configure N8N API key
- [ ] Set up SSL certificate
- [ ] Test all funnels
- [ ] Test member login
- [ ] Test payment flow
- [ ] Configure email notifications

## Support

Email: support@ojg-wellness.com

## License

© 2025 OJG Wellness. All rights reserved.