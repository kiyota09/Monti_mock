<?php

namespace App\Support;

/**
 * Opaque, tamper-evident route keys for client inquiries / conversations.
 *
 * URLs like /dashboard/crm/inquiries/2 leak sequential IDs, letting anyone
 * guess or enumerate other conversations. These keys hide the numeric ID
 * (XOR with an app-key-derived pad) and carry a short HMAC checksum so
 * forged or mistyped keys 404 instead of resolving.
 *
 * This is obfuscation, not authorization: CheckPagePermission (CRM side)
 * and authorizeClient (client portal) still enforce who may open a
 * conversation. The checksum simply keeps honest URLs honest.
 */
class InquiryHash
{
    /**
     * Encode a numeric inquiry ID into a short URL-safe key (~10 chars).
     */
    public static function encode(int $id): string
    {
        $pad = self::pad();
        $scrambled = ($id ^ $pad) & 0xFFFFFFFF;
        $raw = pack('N', $scrambled) . hex2bin(self::checksum($id));

        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /**
     * Decode a key back to its inquiry ID, or null when malformed/forged.
     * Raw numeric IDs are rejected on purpose — every link must be hashed.
     */
    public static function decode(string $key): ?int
    {
        if ($key === '' || ctype_digit($key)) {
            return null;
        }

        $raw = base64_decode(strtr($key, '-_', '+/'), true);
        if (! is_string($raw) || strlen($raw) !== 7) {
            return null;
        }

        $parts = unpack('Nscrambled', $raw);
        if ($parts === false) {
            return null;
        }

        $id = ($parts['scrambled'] ^ self::pad()) & 0xFFFFFFFF;
        if ($id <= 0) {
            return null;
        }

        if (! hash_equals(self::checksum($id), bin2hex(substr($raw, 4, 3)))) {
            return null;
        }

        return $id;
    }

    /**
     * Decode or abort with a 404 (for controllers resolving a route key).
     */
    public static function decodeOrFail(string $key): int
    {
        $id = self::decode($key);
        if ($id === null) {
            abort(404);
        }

        return $id;
    }

    /**
     * 32-bit pad derived from the app key (stable per deployment).
     */
    protected static function pad(): int
    {
        $digest = hash('sha256', 'inquiry-route-pad|' . config('app.key'), true);

        return unpack('N', $digest)[1];
    }

    /**
     * 6-hex-char (24-bit) checksum binding the key to its ID.
     */
    protected static function checksum(int $id): string
    {
        return substr(hash_hmac('sha256', (string) $id, config('app.key') . '|inquiry-route-chk'), 0, 6);
    }
}
