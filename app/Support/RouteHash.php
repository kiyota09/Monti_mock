<?php

namespace App\Support;

/**
 * Opaque, tamper-evident route keys that hide sequential numeric IDs.
 *
 * Same construction as InquiryHash (XOR with an app-key-derived pad plus a
 * short HMAC checksum, base64url-encoded), but bound to a context string
 * (e.g. 'client') so a key minted for one resource can never be replayed
 * as another — decoding with the wrong context returns null.
 *
 * This is obfuscation, not authorization: the usual permission gates
 * still enforce who may open the page. The checksum simply keeps honest
 * URLs honest.
 */
class RouteHash
{
    /**
     * Encode a numeric ID into a short URL-safe key (~10 chars).
     */
    public static function encode(int $id, string $context): string
    {
        $pad = self::pad($context);
        $scrambled = ($id ^ $pad) & 0xFFFFFFFF;
        $raw = pack('N', $scrambled) . hex2bin(self::checksum($id, $context));

        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /**
     * Decode a key back to its ID, or null when malformed, forged, minted
     * for another context, or a raw numeric ID (those must stay hidden).
     */
    public static function decode(string $key, string $context): ?int
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

        $id = ($parts['scrambled'] ^ self::pad($context)) & 0xFFFFFFFF;
        if ($id <= 0) {
            return null;
        }

        if (! hash_equals(self::checksum($id, $context), bin2hex(substr($raw, 4, 3)))) {
            return null;
        }

        return $id;
    }

    /**
     * Decode or abort with a 404 (for controllers resolving a route key).
     */
    public static function decodeOrFail(string $key, string $context): int
    {
        $id = self::decode($key, $context);
        if ($id === null) {
            abort(404);
        }

        return $id;
    }

    /**
     * 32-bit pad derived from the app key + context (stable per deployment).
     */
    protected static function pad(string $context): int
    {
        $digest = hash('sha256', 'route-pad|' . $context . '|' . config('app.key'), true);

        return unpack('N', $digest)[1];
    }

    /**
     * 6-hex-char (24-bit) checksum binding the key to its ID + context.
     */
    protected static function checksum(int $id, string $context): string
    {
        return substr(hash_hmac('sha256', $context . ':' . $id, config('app.key') . '|route-chk'), 0, 6);
    }
}
