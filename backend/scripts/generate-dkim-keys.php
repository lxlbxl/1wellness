<?php
/**
 * DKIM Key Generator
 *
 * Generates a 2048-bit RSA key pair for DKIM email signing.
 * Run once on your server, then add the DNS TXT record it prints.
 *
 * Usage:
 *   php backend/scripts/generate-dkim-keys.php
 *
 * Output:
 *   backend/config/dkim/private.key  — kept on server, never shared
 *   backend/config/dkim/public.key   — for reference
 *   Prints the DNS TXT record to add to your domain registrar
 */

$domain   = getenv('DKIM_DOMAIN')   ?: '1wellness.club';
$selector = getenv('DKIM_SELECTOR') ?: 'mail';
$keyDir   = dirname(__DIR__) . '/config/dkim';

if (!is_dir($keyDir)) {
    mkdir($keyDir, 0750, true);
}

$privatePath = $keyDir . '/private.key';
$publicPath  = $keyDir . '/public.key';

if (file_exists($privatePath)) {
    echo "Private key already exists at: $privatePath\n";
    echo "Delete it first if you want to regenerate.\n";
    exit(1);
}

// Generate 2048-bit RSA key pair
// On Windows, PHP's openssl_pkey_new() may fail if openssl.cnf is not found.
// In that case, we fall back to generating keys via the openssl CLI.
$opensslConfig = [
    'digest_alg'       => 'sha256',
    'private_key_bits' => 2048,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
];

// Try to locate openssl.cnf on Windows (common XAMPP/WAMP paths)
if (PHP_OS_FAMILY === 'Windows') {
    foreach ([
        'C:/xampp/apache/conf/openssl.cnf',
        'C:/wamp64/bin/apache/apache2.4.51/conf/openssl.cnf',
        'C:/Program Files/OpenSSL-Win64/bin/cnf/openssl.cnf',
        'C:/Program Files/OpenSSL/ssl/openssl.cnf',
    ] as $candidate) {
        if (file_exists($candidate)) {
            $opensslConfig['config'] = $candidate;
            break;
        }
    }
}

$res = openssl_pkey_new($opensslConfig);
if (!$res) {
    // Fallback: generate via CLI openssl (works on Linux/Mac/Windows with openssl in PATH)
    echo "PHP openssl_pkey_new() unavailable — trying CLI openssl...\n";
    $privateKeyOut = escapeshellarg($privatePath);
    $publicKeyOut  = escapeshellarg($publicPath);
    $ret = null;
    exec("openssl genrsa -out $privateKeyOut 2048 2>&1", $out, $ret);
    if ($ret !== 0) {
        echo "ERROR: Both PHP openssl and CLI openssl failed.\n";
        echo "On Linux: sudo apt-get install openssl\n";
        echo "On Windows: download from slproweb.com/products/Win32OpenSSL.html\n\n";
        // Print manual DNS instructions anyway
        echo "When you have openssl available, run:\n";
        echo "  openssl genrsa -out $privatePath 2048\n";
        echo "  openssl rsa -in $privatePath -pubout -out $publicPath\n\n";
        echo "Then set:\n";
        echo "  DKIM_PRIVATE_KEY_PATH=$privatePath\n  DKIM_DOMAIN=$domain\n  DKIM_SELECTOR=$selector\n";
        exit(1);
    }
    exec("openssl rsa -in $privateKeyOut -pubout -out $publicKeyOut 2>&1");
    chmod($privatePath, 0600);
    $publicPem    = file_get_contents($publicPath);
    $privateKey   = file_get_contents($privatePath);
    $publicBase64 = preg_replace('/-----[^-]+-----|\s/', '', $publicPem);
    goto print_output;
}

// Export private key (PEM)
openssl_pkey_export($res, $privateKey);
file_put_contents($privatePath, $privateKey);
chmod($privatePath, 0600);

// Export public key details
$details   = openssl_pkey_get_details($res);
$publicPem = $details['key'];
file_put_contents($publicPath, $publicPem);

// Strip PEM headers to get bare base64 for DNS TXT record
$publicBase64 = preg_replace('/-----[^-]+-----|\s/', '', $publicPem);

print_output:
echo "=============================================================\n";
echo " DKIM Keys Generated\n";
echo "=============================================================\n";
echo "Private key: $privatePath  (do NOT share this)\n";
echo "Public key:  $publicPath\n\n";
echo "Add this TXT record to your DNS at $domain:\n\n";
echo "  Name:  {$selector}._domainkey.{$domain}\n";
echo "  Type:  TXT\n";
echo "  Value: \"v=DKIM1; k=rsa; p={$publicBase64}\"\n\n";
echo "  (Some registrars split long TXT values — check max length limits.)\n\n";
echo "Also add these records for SPF and DMARC:\n\n";
echo "  SPF   Name: {$domain}  Type: TXT  Value: \"v=spf1 include:_spf.google.com ~all\"\n";
echo "  DMARC Name: _dmarc.{$domain}  Type: TXT  Value: \"v=DMARC1; p=quarantine; rua=mailto:dmarc@{$domain}; adkim=r; aspf=r\"\n\n";
echo "After DNS propagates (up to 24 hrs), verify with:\n";
echo "  dig TXT {$selector}._domainkey.{$domain}\n";
echo "  curl https://mxtoolbox.com/SuperTool.aspx?action=dkim%3a{$domain}%3a{$selector}\n";
echo "=============================================================\n";
