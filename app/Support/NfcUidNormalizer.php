<?php

namespace App\Support;

use InvalidArgumentException;

final class NfcUidNormalizer
{
    public static function normalize(string $rawUid, bool $reverseBytes = false): string
    {
        $cleaned = preg_replace('/[\s:\-]+/', '', trim($rawUid)) ?? '';

        if ($cleaned === '') {
            throw new InvalidArgumentException('NFC UID cannot be empty.');
        }

        $hex = self::resolveToUppercaseHex($cleaned);

        if (preg_match('/^0+$/', $hex) === 1) {
            throw new InvalidArgumentException('NFC UID cannot be all zeros.');
        }

        if ($reverseBytes) {
            $bytes = str_split($hex, 2);

            return implode('', array_reverse($bytes));
        }

        return $hex;
    }

    public static function tryNormalize(?string $rawUid, bool $reverseBytes = false): ?string
    {
        if ($rawUid === null) {
            return null;
        }

        try {
            return self::normalize($rawUid, $reverseBytes);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public static function isValid(?string $rawUid): bool
    {
        return self::tryNormalize($rawUid) !== null;
    }

    private static function resolveToUppercaseHex(string $cleaned): string
    {
        if (preg_match('/^\d{10}$/', $cleaned) === 1) {
            $numericValue = (int) $cleaned;

            if ($numericValue <= 0 || $numericValue > 0xFFFFFFFF) {
                throw new InvalidArgumentException('Decimal NFC UID is out of unsigned 32-bit range.');
            }

            return strtoupper(str_pad(dechex($numericValue), 8, '0', STR_PAD_LEFT));
        }

        if (preg_match('/^[0-9A-Fa-f]{8}([0-9A-Fa-f]{6})?$/', $cleaned) === 1) {
            return strtoupper($cleaned);
        }

        throw new InvalidArgumentException('Invalid NFC UID format or length.');
    }
}
