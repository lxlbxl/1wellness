<?php

/** Generates and verifies signed one-time tokens for get-credentials.php. */
class CredentialToken
{
    const TTL = 900; // 15 minutes

    /** Returns a signed URL to get-credentials.php for the given tx_ref. */
    public static function generateUrl(string $txRef, string $baseUrl = ''): string
    {
        $expiry = time() + self::TTL;
        $token  = self::sign($txRef, $expiry);
        $base   = $baseUrl ?: (defined('APP_URL') ? rtrim(APP_URL, '/') : '');
        return $base . '/backend/api/get-credentials.php'
            . '?tx_ref=' . urlencode($txRef)
            . '&expiry=' . $expiry
            . '&token=' . $token;
    }

    public static function sign(string $txRef, int $expiry): string
    {
        return hash_hmac('sha256', "{$txRef}|{$expiry}", JWT_SECRET);
    }

    public static function verify(string $txRef, string $token, int $expiry): bool
    {
        if (time() > $expiry) return false;
        return hash_equals(self::sign($txRef, $expiry), $token);
    }
}
