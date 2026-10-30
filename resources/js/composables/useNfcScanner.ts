import { onMounted, onUnmounted, ref } from 'vue';
import { tryNormalizeNfcUid } from '../utils/nfcUid.ts';

export interface NfcScanEvent {
    uid: string;
    raw: string;
}

export interface NfcInvalidScanEvent {
    raw: string;
}

export interface NfcScannerOptions {
    maxKeyGapMs?: number;
    minBurstLength?: number;
    reverseBytes?: boolean;
    enabled?: boolean;
    ignoreEditableTargets?: boolean;
    onScan: (event: NfcScanEvent) => void;
    onInvalidScan?: (event: NfcInvalidScanEvent) => void;
}

export interface SimulatedKeyInput {
    key: string;
    timeStampMs: number;
    isEditableTarget?: boolean;
    preventDefault?: () => void;
}

const READER_CHAR_PATTERN = /^[0-9A-Fa-f:\-]$/;
const IGNORED_MODIFIER_KEYS = new Set(['Shift', 'Control', 'Alt', 'Meta', 'CapsLock']);

export function createNfcScannerEngine(options: NfcScannerOptions) {
    const maxKeyGapMs = options.maxKeyGapMs ?? 50;
    const minBurstLength = options.minBurstLength ?? 8;
    const reverseBytes = options.reverseBytes ?? false;
    const ignoreEditableTargets = options.ignoreEditableTargets ?? false;

    let buffer = '';
    let lastKeyTimeMs: number | null = null;
    let maxObservedGapMs = 0;

    function reset(): void {
        buffer = '';
        lastKeyTimeMs = null;
        maxObservedGapMs = 0;
    }

    function handleKey(input: SimulatedKeyInput): boolean {
        if (ignoreEditableTargets && input.isEditableTarget) {
            return false;
        }

        if (IGNORED_MODIFIER_KEYS.has(input.key)) {
            return false;
        }

        const now = input.timeStampMs;

        if (input.key === 'Enter') {
            const rawCandidate = buffer;
            const gapBeforeEnter = lastKeyTimeMs !== null ? now - lastKeyTimeMs : Infinity;
            const wasFastBurst =
                rawCandidate.length >= minBurstLength &&
                maxObservedGapMs <= maxKeyGapMs &&
                gapBeforeEnter <= maxKeyGapMs * 2;

            reset();

            if (!wasFastBurst) {
                return false;
            }

            input.preventDefault?.();

            const normalized = tryNormalizeNfcUid(rawCandidate, { reverseBytes });
            if (normalized !== null) {
                options.onScan({
                    uid: normalized,
                    raw: rawCandidate,
                });
                return true;
            }

            options.onInvalidScan?.({
                raw: rawCandidate,
            });
            return false;
        }

        if (READER_CHAR_PATTERN.test(input.key)) {
            if (lastKeyTimeMs !== null) {
                const gap = now - lastKeyTimeMs;
                if (gap > maxKeyGapMs) {
                    buffer = input.key;
                    maxObservedGapMs = 0;
                    lastKeyTimeMs = now;
                    return false;
                }
                if (gap > maxObservedGapMs) {
                    maxObservedGapMs = gap;
                }
            } else {
                maxObservedGapMs = 0;
            }

            buffer += input.key;
            lastKeyTimeMs = now;
            return false;
        }

        if (input.key.length === 1) {
            reset();
        }

        return false;
    }

    return {
        handleKey,
        reset,
        getBuffer: (): string => buffer,
    };
}

function isEditableElement(target: EventTarget | null): boolean {
    if (!target || typeof HTMLElement === 'undefined' || !(target instanceof HTMLElement)) {
        return false;
    }

    if (target.dataset.nfcCapture === 'true') {
        return false;
    }

    const tagName = target.tagName.toUpperCase();
    return (
        tagName === 'INPUT' ||
        tagName === 'TEXTAREA' ||
        tagName === 'SELECT' ||
        target.isContentEditable
    );
}

export function useNfcScanner(options: NfcScannerOptions) {
    const isListening = ref(options.enabled ?? true);
    const lastScannedUid = ref<string | null>(null);
    const engine = createNfcScannerEngine({
        ...options,
        onScan: (event) => {
            lastScannedUid.value = event.uid;
            options.onScan(event);
        },
    });

    function onWindowKeydown(event: KeyboardEvent): void {
        if (!isListening.value) {
            return;
        }

        const timeStampMs =
            typeof event.timeStamp === 'number' && event.timeStamp > 0
                ? event.timeStamp
                : performance.now();

        engine.handleKey({
            key: event.key,
            timeStampMs,
            isEditableTarget: isEditableElement(event.target),
            preventDefault: () => event.preventDefault(),
        });
    }

    onMounted(() => {
        if (typeof window !== 'undefined') {
            window.addEventListener('keydown', onWindowKeydown, { capture: true });
        }
    });

    onUnmounted(() => {
        if (typeof window !== 'undefined') {
            window.removeEventListener('keydown', onWindowKeydown, { capture: true });
        }
        engine.reset();
    });

    return {
        isListening,
        lastScannedUid,
        resetBuffer: engine.reset,
        simulateKey: engine.handleKey,
    };
}
