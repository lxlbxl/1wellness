# OJG Wellness - Deployment Instructions

## Moving This Folder to a New Server

This `ojg-usd` folder is **completely standalone**. You can move it to any server and it will work independently.

## Step-by-Step Deployment

### 1. Prepare the Folder

The entire `ojg-usd` folder should be moved as-is. The folder name can be:
- Kept as `ojg-usd` (for subdirectory deployment: `domain.com/ojg-usd/`)
- Renamed to root contents (for root deployment: `domain.com/`)

### 2. Upload to Server

#### Via FTP/SFTP:
```
1. Connect to your server
2. Navigate to your web root (usually /public_html or /var/www/html)
3. Upload the entire ojg-usd folder
4. Ensure all files are transferred
```

#### Via cPanel File Manager:
```
1. Zip the ojg-usd folder
2. Upload the ZIP to cPanel File Manager
3. Extract in your web root
```

#### Via SSH:
```bash
# On your local machine, create a zip
zip -r ojg-usd.zip ojg-usd/

# Upload to server
scp ojg-usd.zip user@yourserver:/tmp/

# On the server
cd /var/www/html
unzip /tmp/ojg-usd.zip
# OR move contents to root:
mv ojg-usd/* .
rmdir ojg-usd
```

### 3. Set Permissions

```bash
# Make database directory writable
chmod 755 backend/database/

# Make config directory writable (for db_config.php creation)
chmod 755 backend/config/

# Protect sensitive files
chmod 600 backend/config/config.php
```

### 4. Access and Initialize

1. Navigate to `http://yourdomain.com/backend/admin/`
2. Login with default credentials: `admin` / `admin123`
3. The system will auto-create the SQLite database on first access

### 5. Create Admin User (if needed)

If the admin user doesn't exist, run this PHP script:

```php
<?php
// Save as create_admin_temp.php in backend/
require_once 'config/config.php';
require_once 'classes/Database.php';

$db = Database::getPDO();
$stmt = $db->prepare("INSERT OR IGNORE INTO admins (username, password_hash) VALUES (?, ?)");
$stmt->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT)]);
echo "Admin user created!";
?>
```

Then access `http://yourdomain.com/backend/create_admin_temp.php` once, then delete it.

## Configuration for Production

### 1. Update Payment Keys

Edit `js/config.js`:
```javascript
payment: {
    flutterwave: {
        publicKey: "FLWPUBK_LIVE_YOUR_ACTUAL_KEY",
        environment: "production", // Changed from "sandbox"
    }
}
```

### 2. Update API Keys

Edit `backend/config/config.php`:
```php
define('N8N_API_KEY', 'your-actual-n8n-api-key');
```

### 3. Update Domain References

Edit `backend/config/config.php`:
```php
define('CORS_ALLOWED_ORIGINS', [
    'https://your-actual-domain.com',
    'https://www.your-actual-domain.com',
]);
```

### 4. Set Up SSL

Obtain an SSL certificate for HTTPS:
- Use Let's Encrypt (free) via Certbot
- Or use your hosting provider's SSL

## Testing After Deployment

1. **Test Admin Login**: `http://yourdomain.com/backend/admin/`
2. **Test Funnels**: 
   - `http://yourdomain.com/pcos/`
   - `http://yourdomain.com/acne/`
   - `http://yourdomain.com/weight/`
   - `http://yourdomain.com/mens/`
3. **Test Assessment Flow**: Complete an assessment
4. **Test Payment**: Use test mode first
5. **Test Member Area**: Login with a test member account

## Troubleshooting

### Database Not Creating
```bash
# Check permissions
ls -la backend/database/
# Should be writable by web server
chmod 755 backend/database/
```

### Admin Login Fails
```bash
# Check if admin table exists
# Run: php backend/create_admin.php (if exists)
# Or manually create via PHP script
```

### 404 Errors
- Ensure `.htaccess` files are uploaded
- Enable mod_rewrite on Apache
- Check Nginx configuration for PHP handling

### Payment Not Working
- Verify Flutterwave keys are correct
- Check if environment is set to "production"
- Ensure webhook URLs are accessible

## Quick Reference

| Component | URL Path |
|-----------|----------|
| Admin Panel | `/backend/admin/` |
| Member Login | `/member/login.php` |
| Member Dashboard | `/member/` |
| PCOS Funnel | `/pcos/` |
| Acne Funnel | `/acne/` |
| Weight Funnel | `/weight/` |
| Men's Funnel | `/mens/` |

## Support

For issues, contact: support@ojg-wellness.com