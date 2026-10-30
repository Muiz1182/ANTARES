<?php

use App\Support\NfcUidNormalizer;

dataset('valid_reader_samples', [
    'CARD-01 decimal 10-digit with CRLF' => ["0077705923\r\n", '04A1B2C3', 'C3B2A104'],
    'CARD-02 decimal 10-digit high bit' => ['3283263748', 'C3B2A104', '04A1B2C3'],
    'CARD-03 decimal 10-digit leading zeros' => ['0004512345', '0044DA59', '59DA4400'],
    'CARD-04 decimal max uint32' => ['4294967295', 'FFFFFFFF', 'FFFFFFFF'],
    'CARD-05 lowercase hex with LF' => ["04a1b2c3\n", '04A1B2C3', 'C3B2A104'],
    'CARD-06 colon-separated hex' => ['04:a1:b2:c3', '04A1B2C3', 'C3B2A104'],
    'CARD-07 dash-separated hex' => ['C3-B2-A1-04', 'C3B2A104', '04A1B2C3'],
    'CARD-08 7-byte UID hex with colons' => ['04:A1:B2:C3:D4:E5:F6', '04A1B2C3D4E5F6', 'F6E5D4C3B2A104'],
]);

test('normalizes every valid sample from docs/reader-sample.md', function (
    string $raw,
    string $expectedNormal,
    string $expectedReversed,
) {
    expect(NfcUidNormalizer::normalize($raw, false))->toBe($expectedNormal)
        ->and(NfcUidNormalizer::normalize($raw, true))->toBe($expectedReversed)
        ->and(NfcUidNormalizer::tryNormalize($raw, false))->toBe($expectedNormal)
        ->and(NfcUidNormalizer::isValid($raw))->toBeTrue();
})->with('valid_reader_samples');

dataset('invalid_reader_samples', [
    'empty string' => [''],
    'whitespace and CRLF only' => ["   \r\n"],
    'all-zero 10-digit decimal' => ['0000000000'],
    'all-zero 8-char hex' => ['00000000'],
    'decimal overflow above uint32' => ['4294967296'],
    'too short decimal' => ['12345'],
    'odd-length 7-char hex' => ['04A1B2C'],
    'non-hex character' => ['04A1B2C3G5'],
]);

test('rejects every invalid sample from docs/reader-sample.md', function (string $invalidRaw) {
    expect(NfcUidNormalizer::tryNormalize($invalidRaw))->toBeNull()
        ->and(NfcUidNormalizer::isValid($invalidRaw))->toBeFalse()
        ->and(fn () => NfcUidNormalizer::normalize($invalidRaw))
        ->toThrow(InvalidArgumentException::class);
})->with('invalid_reader_samples');
