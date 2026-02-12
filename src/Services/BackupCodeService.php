<?php

namespace App\Services;

final class BackupCodeService
{
    public static function generate(int $count = 10): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4)));
        }
        return $codes;
    }

    public static function hashCodes(array $plainCodes): array
    {
        $hashed = [];
        foreach ($plainCodes as $code) {
            $hashed[] = password_hash((string) $code, PASSWORD_DEFAULT);
        }
        return $hashed;
    }

    public static function consume(string $inputCode, array $hashedCodes): array
    {
        $input = strtoupper(trim($inputCode));
        $matched = false;
        $remaining = [];

        foreach ($hashedCodes as $hash) {
            if (!$matched && is_string($hash) && password_verify($input, $hash)) {
                $matched = true;
                continue;
            }
            if (is_string($hash) && $hash !== '') {
                $remaining[] = $hash;
            }
        }

        return ['matched' => $matched, 'remaining' => $remaining];
    }
}
