<?php

namespace App\Services;

final class TotpService
{
    public static function generateSecret(int $length = 32): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        $max = strlen($chars) - 1;

        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[random_int(0, $max)];
        }

        return $secret;
    }

    public static function getOtpAuthUri(string $secret, string $email, string $issuer): string
    {
        $label = rawurlencode($issuer . ':' . $email);
        $issuerEncoded = rawurlencode($issuer);
        return "otpauth://totp/{$label}?secret={$secret}&issuer={$issuerEncoded}&algorithm=SHA1&digits=6&period=30";
    }

    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($code) !== 6) {
            return false;
        }

        $current = intdiv(time(), 30);
        for ($i = -$window; $i <= $window; $i++) {
            $expected = self::hotp($secret, $current + $i);
            if (hash_equals($expected, $code)) {
                return true;
            }
        }

        return false;
    }

    private static function hotp(string $secret, int $counter): string
    {
        $binarySecret = self::base32Decode($secret);
        if ($binarySecret === '') {
            return '000000';
        }

        $counterBin = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $counterBin, $binarySecret, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $part = substr($hash, $offset, 4);
        $value = unpack('N', $part)[1] & 0x7FFFFFFF;
        $mod = $value % 1000000;

        return str_pad((string) $mod, 6, '0', STR_PAD_LEFT);
    }

    private static function base32Decode(string $input): string
    {
        $input = strtoupper(preg_replace('/[^A-Z2-7]/', '', $input) ?? '');
        if ($input === '') {
            return '';
        }

        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        $output = '';

        for ($i = 0, $len = strlen($input); $i < $len; $i++) {
            $val = strpos($alphabet, $input[$i]);
            if ($val === false) {
                return '';
            }
            $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
        }

        for ($i = 0, $len = strlen($bits); $i + 8 <= $len; $i += 8) {
            $output .= chr(bindec(substr($bits, $i, 8)));
        }

        return $output;
    }
}
