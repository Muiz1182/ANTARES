import assert from 'node:assert/strict';
import test from 'node:test';
import { createNfcScannerEngine, type NfcInvalidScanEvent, type NfcScanEvent } from '../useNfcScanner.ts';

function feedBurst(
    engine: ReturnType<typeof createNfcScannerEngine>,
    text: string,
    startTimeMs: number,
    gapMs: number,
): number {
    let currentTime = startTimeMs;

    for (const char of text) {
        engine.handleKey({
            key: char,
            timeStampMs: currentTime,
        });
        currentTime += gapMs;
    }

    engine.handleKey({
        key: 'Enter',
        timeStampMs: currentTime,
    });

    return currentTime;
}

test('emits normalized UID for fast keystroke bursts ending with Enter across reader-sample.md formats', () => {
    const scannedEvents: NfcScanEvent[] = [];

    const engine = createNfcScannerEngine({
        maxKeyGapMs: 50,
        onScan: (event) => {
            scannedEvents.push(event);
        },
    });

    let clock = 1000;
    clock = feedBurst(engine, '0077705923', clock, 10) + 500;
    clock = feedBurst(engine, '3283263748', clock, 12) + 500;
    clock = feedBurst(engine, '0004512345', clock, 8) + 500;
    clock = feedBurst(engine, '04:a1:b2:c3', clock, 14) + 500;
    clock = feedBurst(engine, '04:A1:B2:C3:D4:E5:F6', clock, 10) + 500;

    assert.equal(scannedEvents.length, 5);
    assert.deepEqual(
        scannedEvents.map((item) => item.uid),
        ['04A1B2C3', 'C3B2A104', '0044DA59', '04A1B2C3', '04A1B2C3D4E5F6'],
    );
});

test('ignores slow manual typing on the keyboard even when ending with Enter', () => {
    const scannedEvents: NfcScanEvent[] = [];
    const invalidEvents: NfcInvalidScanEvent[] = [];

    const engine = createNfcScannerEngine({
        maxKeyGapMs: 50,
        onScan: (event) => scannedEvents.push(event),
        onInvalidScan: (event) => invalidEvents.push(event),
    });

    feedBurst(engine, '0077705923', 1000, 120);
    feedBurst(engine, '04A1B2C3', 5000, 85);

    assert.equal(scannedEvents.length, 0);
    assert.equal(invalidEvents.length, 0);
});

test('recovers cleanly when slow manual typing is followed by a fast NFC reader tap', () => {
    const scannedEvents: NfcScanEvent[] = [];

    const engine = createNfcScannerEngine({
        maxKeyGapMs: 50,
        onScan: (event) => scannedEvents.push(event),
    });

    engine.handleKey({ key: '1', timeStampMs: 100 });
    engine.handleKey({ key: '2', timeStampMs: 250 });
    engine.handleKey({ key: '3', timeStampMs: 420 });

    feedBurst(engine, '0077705923', 600, 10);

    assert.equal(scannedEvents.length, 1);
    assert.equal(scannedEvents[0].uid, '04A1B2C3');
    assert.equal(scannedEvents[0].raw, '0077705923');
});

test('reports invalid fast bursts (such as all-zero UID) to onInvalidScan without emitting onScan', () => {
    const scannedEvents: NfcScanEvent[] = [];
    const invalidEvents: NfcInvalidScanEvent[] = [];

    const engine = createNfcScannerEngine({
        maxKeyGapMs: 50,
        onScan: (event) => scannedEvents.push(event),
        onInvalidScan: (event) => invalidEvents.push(event),
    });

    feedBurst(engine, '0000000000', 1000, 10);

    assert.equal(scannedEvents.length, 0);
    assert.equal(invalidEvents.length, 1);
    assert.equal(invalidEvents[0].raw, '0000000000');
});

