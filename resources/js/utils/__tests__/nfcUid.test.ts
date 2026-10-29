import assert from 'node:assert/strict';
import test from 'node:test';
import { isValidNfcUid, normalizeNfcUid, tryNormalizeNfcUid } from '../nfcUid.ts';

const validSamples: Array<[string, string, string, string]> = [
    ['CARD-01', '0077705923\r\n', '04A1B2C3', 'C3B2A104'],
    ['CARD-02', '3283263748', 'C3B2A104', '04A1B2C3'],
    ['CARD-03', '0004512345', '0044DA59', '59DA4400'],
    ['CARD-04', '4294967295', 'FFFFFFFF', 'FFFFFFFF'],
    ['CARD-05', '04a1b2c3\n', '04A1B2C3', 'C3B2A104'],
    ['CARD-06', '04:a1:b2:c3', '04A1B2C3', 'C3B2A104'],
    ['CARD-07', 'C3-B2-A1-04', 'C3B2A104', '04A1B2C3'],
    ['CARD-08', '04:A1:B2:C3:D4:E5:F6', '04A1B2C3D4E5F6', 'F6E5D4C3B2A104'],
];

test('normalizes every valid sample from docs/reader-sample.md identically to PHP', () => {
    for (const [id, raw, expectedNormal, expectedReversed] of validSamples) {
        assert.equal(normalizeNfcUid(raw, { reverseBytes: false }), expectedNormal, `${id} normal`);
        assert.equal(normalizeNfcUid(raw, { reverseBytes: true }), expectedReversed, `${id} reversed`);
        assert.equal(tryNormalizeNfcUid(raw), expectedNormal, `${id} tryNormalize`);
        assert.equal(isValidNfcUid(raw), true, `${id} isValid`);
    }
});

const invalidSamples: string[] = [
    '',
    '   \r\n',
    '0000000000',
    '00000000',
    '4294967296',
    '12345',
    '04A1B2C',
    '04A1B2C3G5',
];

test('rejects every invalid sample from docs/reader-sample.md identically to PHP', () => {
    for (const invalidRaw of invalidSamples) {
        assert.equal(tryNormalizeNfcUid(invalidRaw), null);
        assert.equal(isValidNfcUid(invalidRaw), false);
        assert.throws(() => normalizeNfcUid(invalidRaw));
    }
    assert.equal(tryNormalizeNfcUid(null), null);
    assert.equal(tryNormalizeNfcUid(undefined), null);
});

