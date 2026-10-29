export interface NfcUidOptions {
    reverseBytes?: boolean;
}

const MAX_UINT32 = 0xffffffff;

export function normalizeNfcUid(rawUid: string, options: NfcUidOptions = {}): string {
    const cleaned = rawUid.trim().replace(/[\s:\-]+/g, '');

    if (cleaned === '') {
        throw new Error('NFC UID cannot be empty.');
    }

    const hex = resolveToUppercaseHex(cleaned);

    if (/^0+$/.test(hex)) {
        throw new Error('NFC UID cannot be all zeros.');
    }

    if (options.reverseBytes) {
        const bytes = hex.match(/.{1,2}/g) ?? [];
        return bytes.reverse().join('');
    }

    return hex;
}

export function tryNormalizeNfcUid(
    rawUid: string | null | undefined,
    options: NfcUidOptions = {},
): string | null {
    if (typeof rawUid !== 'string') {
        return null;
    }

    try {
        return normalizeNfcUid(rawUid, options);
    } catch {
        return null;
    }
}

export function isValidNfcUid(rawUid: string | null | undefined): boolean {
    return tryNormalizeNfcUid(rawUid) !== null;
}

function resolveToUppercaseHex(cleaned: string): string {
    if (/^\d{10}$/.test(cleaned)) {
        const numericValue = Number(cleaned);

        if (!Number.isInteger(numericValue) || numericValue <= 0 || numericValue > MAX_UINT32) {
            throw new Error('Decimal NFC UID is out of unsigned 32-bit range.');
        }

        return numericValue.toString(16).toUpperCase().padStart(8, '0');
    }

    if (/^[0-9A-Fa-f]{8}([0-9A-Fa-f]{6})?$/.test(cleaned)) {
        return cleaned.toUpperCase();
    }

    throw new Error('Invalid NFC UID format or length.');
}

