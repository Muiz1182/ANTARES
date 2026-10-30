<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useNfcScanner } from '@/composables/useNfcScanner';
import type {
    DeviceItem,
    ScanLogItem,
    ScanResultPayload,
    ScanSoundTone,
} from '@/types/models';

const props = defineProps<{
    recentScans: ScanLogItem[];
    devices: DeviceItem[];
    defaultDevToken: string | null;
}>();

const TOKEN_STORAGE_KEY = 'antares_scan_device_token';

const deviceToken = ref('');
const showTokenDialog = ref(false);
const tokenInput = ref('');
const soundEnabled = ref(true);
const isOnline = ref(true);
const isProcessingScan = ref(false);
const currentTimeText = ref('');
const currentDateText = ref('');
const activeFeedback = ref<ScanResultPayload | null>(null);
const recentLogs = ref<ScanLogItem[]>([...props.recentScans]);
const errorBanner = ref<string | null>(null);
const hiddenCaptureInput = ref<HTMLInputElement | null>(null);

let clockInterval: ReturnType<typeof setInterval> | null = null;
let resetTimer: ReturnType<typeof setTimeout> | null = null;

function updateJakartaClock(): void {
    const now = new Date();
    currentTimeText.value = new Intl.DateTimeFormat('id-ID', {
        timeZone: 'Asia/Jakarta',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
    }).format(now);

    currentDateText.value = new Intl.DateTimeFormat('id-ID', {
        timeZone: 'Asia/Jakarta',
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(now);
}

function playFeedbackTone(tone: ScanSoundTone): void {
    if (!soundEnabled.value || typeof window === 'undefined') {
        return;
    }

    const AudioCtx = window.AudioContext || (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext;
    if (!AudioCtx) {
        return;
    }

    try {
        const ctx = new AudioCtx();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);

        const now = ctx.currentTime;
        gain.gain.setValueAtTime(0.15, now);

        if (tone === 'success') {
            osc.type = 'sine';
            osc.frequency.setValueAtTime(880, now);
            osc.frequency.setValueAtTime(1174.66, now + 0.09);
            gain.gain.exponentialRampToValueAtTime(0.001, now + 0.24);
            osc.start(now);
            osc.stop(now + 0.24);
        } else if (tone === 'duplicate') {
            osc.type = 'triangle';
            osc.frequency.setValueAtTime(520, now);
            gain.gain.exponentialRampToValueAtTime(0.001, now + 0.18);
            osc.start(now);
            osc.stop(now + 0.18);
        } else if (tone === 'warning') {
            osc.type = 'sawtooth';
            osc.frequency.setValueAtTime(620, now);
            osc.frequency.setValueAtTime(440, now + 0.15);
            gain.gain.exponentialRampToValueAtTime(0.001, now + 0.35);
            osc.start(now);
            osc.stop(now + 0.35);
        } else {
            osc.type = 'square';
            osc.frequency.setValueAtTime(260, now);
            gain.gain.exponentialRampToValueAtTime(0.001, now + 0.32);
            osc.start(now);
            osc.stop(now + 0.32);
        }
    } catch {
        // Ignore audio context autoplay restrictions until first user interaction.
    }
}

function scheduleAutoReset(): void {
    if (resetTimer) {
        clearTimeout(resetTimer);
    }

    resetTimer = setTimeout(() => {
        activeFeedback.value = null;
        errorBanner.value = null;
        refocusHiddenInput();
    }, 3000);
}

function refocusHiddenInput(): void {
    if (showTokenDialog.value) {
        return;
    }
    hiddenCaptureInput.value?.focus({ preventScroll: true });
}

function studentInitials(name?: string | null): string {
    if (!name) return 'NFC';
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

function formatScanTime(isoString?: string | null): string {
    if (!isoString) return currentTimeText.value;
    try {
        return new Intl.DateTimeFormat('id-ID', {
            timeZone: 'Asia/Jakarta',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false,
        }).format(new Date(isoString));
    } catch {
        return currentTimeText.value;
    }
}

async function submitScan(rawUid: string): Promise<void> {
    if (!deviceToken.value) {
        errorBanner.value = 'Token perangkat pos belum diatur. Klik tombol Pengaturan Token Pos di kanan atas.';
        showTokenDialog.value = true;
        return;
    }

    isProcessingScan.value = true;
    errorBanner.value = null;

    try {
        const response = await window.fetch('/api/scan', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                Authorization: `Bearer ${deviceToken.value}`,
            },
            body: JSON.stringify({
                uid: rawUid,
                idempotency_key:
                    typeof crypto !== 'undefined' && 'randomUUID' in crypto
                        ? crypto.randomUUID()
                        : `${Date.now()}-${rawUid}`,
            }),
        });

        isOnline.value = true;

        if (response.status === 401) {
            errorBanner.value = 'Token perangkat ditolak (401). Periksa atau perbarui token perangkat pos Anda.';
            playFeedbackTone('error');
            return;
        }

        if (response.status === 429) {
            errorBanner.value = 'Terlalu banyak permintaan pemindaian dalam waktu singkat (429). Mohon tunggu beberapa detik.';
            playFeedbackTone('warning');
            return;
        }

        if (!response.ok) {
            errorBanner.value = `Gagal memproses pemindaian (HTTP ${response.status}).`;
            playFeedbackTone('error');
            return;
        }

        const payload = (await response.json()) as ScanResultPayload;
        activeFeedback.value = payload;
        playFeedbackTone(payload.sound_tone);

        const newLogEntry: ScanLogItem = {
            id: Date.now(),
            uid_raw: rawUid,
            uid_normalized: payload.uid_normalized,
            result: payload.result,
            message: payload.message,
            sound_tone: payload.sound_tone,
            ok: payload.ok,
            scanned_at: payload.scanned_at,
            scanned_time_formatted: formatScanTime(payload.scanned_at),
            student: payload.student,
            attendance: payload.attendance,
        };

        recentLogs.value = [newLogEntry, ...recentLogs.value].slice(0, 10);
        scheduleAutoReset();
    } catch {
        isOnline.value = false;
        errorBanner.value = 'Tidak dapat terhubung ke server absensi. Periksa koneksi jaringan LAN pos ini.';
        playFeedbackTone('error');
    } finally {
        isProcessingScan.value = false;
        refocusHiddenInput();
    }
}

useNfcScanner({
    maxKeyGapMs: 50,
    ignoreEditableTargets: true,
    onScan: ({ raw }) => {
        void submitScan(raw);
    },
    onInvalidScan: ({ raw }) => {
        errorBanner.value = `Kartu tidak terbaca dengan benar (output mentah: ${raw}). Silakan tempel ulang kartu.`;
        playFeedbackTone('error');
        scheduleAutoReset();
    },
});

function openTokenSettings(): void {
    tokenInput.value = deviceToken.value;
    showTokenDialog.value = true;
}

function saveTokenSettings(): void {
    deviceToken.value = tokenInput.value.trim();
    if (typeof window !== 'undefined') {
        window.localStorage.setItem(TOKEN_STORAGE_KEY, deviceToken.value);
    }
    showTokenDialog.value = false;
    errorBanner.value = null;
    refocusHiddenInput();
}

function useDefaultDevToken(): void {
    if (!props.defaultDevToken) return;
    tokenInput.value = props.defaultDevToken;
}

function handleOnlineStatus(): void {
    isOnline.value = typeof navigator !== 'undefined' ? navigator.onLine : true;
}

function handleEscape(event: KeyboardEvent): void {
    if (event.key === 'Escape' && showTokenDialog.value) {
        showTokenDialog.value = false;
        refocusHiddenInput();
    }
}

const feedbackBoxClasses = computed(() => {
    if (!activeFeedback.value) {
        return 'border-slate-400 bg-white text-slate-900';
    }

    switch (activeFeedback.value.sound_tone) {
        case 'success':
            return 'border-emerald-800 bg-emerald-50 text-emerald-950';
        case 'duplicate':
            return 'border-sky-800 bg-sky-50 text-sky-950';
        case 'warning':
            return 'border-amber-800 bg-amber-50 text-amber-950';
        default:
            return 'border-red-800 bg-red-50 text-red-950';
    }
});

onMounted(() => {
    updateJakartaClock();
    clockInterval = setInterval(updateJakartaClock, 1000);

    if (typeof window !== 'undefined') {
        const saved = window.localStorage.getItem(TOKEN_STORAGE_KEY);
        if (saved && saved.trim() !== '') {
            deviceToken.value = saved.trim();
        } else if (props.defaultDevToken) {
            deviceToken.value = props.defaultDevToken;
            window.localStorage.setItem(TOKEN_STORAGE_KEY, props.defaultDevToken);
        }

        isOnline.value = window.navigator.onLine;
        window.addEventListener('online', handleOnlineStatus);
        window.addEventListener('offline', handleOnlineStatus);
        window.addEventListener('keydown', handleEscape);
    }

    refocusHiddenInput();
});

onUnmounted(() => {
    if (clockInterval) clearInterval(clockInterval);
    if (resetTimer) clearTimeout(resetTimer);
    if (typeof window !== 'undefined') {
        window.removeEventListener('online', handleOnlineStatus);
        window.removeEventListener('offline', handleOnlineStatus);
        window.removeEventListener('keydown', handleEscape);
    }
});
</script>

<template>
    <Head title="Pos Absensi NFC (Scan Station)" />

    <div
        @click="refocusHiddenInput"
        class="flex min-h-screen flex-col justify-between bg-slate-100 text-slate-900"
    >
        <input
            ref="hiddenCaptureInput"
            data-nfc-capture="true"
            type="text"
            aria-label="Penangkap Input NFC Reader"
            autocomplete="off"
            class="sr-only"
            readonly
        />

        <header class="border-b border-slate-300 bg-white px-4 py-3 sm:px-6">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-4">
                    <span class="text-base font-bold text-slate-900">
                        [LOGO] Pos Absensi NFC SMP
                    </span>
                    <span
                        class="inline-flex items-center rounded border px-2.5 py-1 text-xs font-bold"
                        :class="
                            isOnline
                                ? 'border-emerald-800 bg-emerald-50 text-emerald-950'
                                : 'border-red-800 bg-red-50 text-red-950'
                        "
                    >
                        {{ isOnline ? 'Status Server: Terhubung' : 'Status Server: Terputus' }}
                    </span>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <div class="rounded border border-slate-400 bg-slate-50 px-3 py-1.5 text-right">
                        <p class="font-mono text-base font-bold text-slate-900">
                            {{ currentTimeText }} WIB
                        </p>
                        <p class="text-xs font-medium text-slate-700">
                            {{ currentDateText }}
                        </p>
                    </div>

                    <button
                        type="button"
                        @click.stop="soundEnabled = !soundEnabled"
                        class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-3 py-2 text-xs font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                    >
                        Suara: {{ soundEnabled ? 'Aktif' : 'Senyap' }}
                    </button>

                    <button
                        type="button"
                        @click.stop="openTokenSettings"
                        class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-3 py-2 text-xs font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                    >
                        Pengaturan Token Pos
                    </button>

                    <Link
                        :href="route('dashboard')"
                        class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-3 py-2 text-xs font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                    >
                        Kembali ke Ringkasan
                    </Link>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-6 sm:px-6">
            <div
                v-if="errorBanner"
                role="alert"
                class="mb-4 rounded border-2 border-red-800 bg-red-50 px-4 py-3 text-sm font-bold text-red-950"
            >
                {{ errorBanner }}
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
                <section
                    aria-live="polite"
                    class="flex flex-col justify-center rounded border-2 p-6 sm:p-8 lg:col-span-7"
                    :class="feedbackBoxClasses"
                >
                    <div v-if="isProcessingScan" class="py-12 text-center">
                        <p class="text-xl font-bold text-slate-900">
                            Memverifikasi Kartu NFC...
                        </p>
                        <p class="mt-2 text-sm text-slate-700">
                            Mohon tunggu sebentar, sedang mencatat kehadiran ke server.
                        </p>
                    </div>

                    <div v-else-if="activeFeedback" class="space-y-6">
                        <div class="flex flex-col items-center gap-6 sm:flex-row sm:items-start">
                            <div
                                class="flex h-32 w-32 shrink-0 items-center justify-center overflow-hidden rounded border-2 border-slate-700 bg-slate-200 text-3xl font-bold text-slate-900"
                            >
                                <img
                                    v-if="activeFeedback.student?.photo_url"
                                    :src="activeFeedback.student.photo_url"
                                    :alt="`Foto ${activeFeedback.student.name}`"
                                    class="h-full w-full object-cover"
                                />
                                <span v-else>
                                    {{ studentInitials(activeFeedback.student?.name) }}
                                </span>
                            </div>

                            <div class="flex-1 text-center sm:text-left">
                                <span
                                    class="inline-block rounded border border-current px-3 py-1 text-xs font-bold uppercase"
                                >
                                    Kode Hasil: {{ activeFeedback.result }}
                                </span>

                                <h1 class="mt-2 text-2xl font-bold sm:text-3xl">
                                    {{ activeFeedback.message }}
                                </h1>

                                <div v-if="activeFeedback.student" class="mt-4 space-y-1">
                                    <p class="text-xl font-bold">
                                        {{ activeFeedback.student.name }}
                                    </p>
                                    <p class="text-base font-semibold">
                                        Kelas: {{ activeFeedback.student.classroom || '-' }}
                                        <span v-if="activeFeedback.student.nis">
                                            | NIS: {{ activeFeedback.student.nis }}
                                        </span>
                                    </p>
                                </div>

                                <div
                                    v-if="activeFeedback.attendance"
                                    class="mt-4 inline-flex flex-wrap gap-3 rounded border border-current bg-white/80 px-4 py-2 text-sm font-bold text-slate-900"
                                >
                                    <span>
                                        Status Hari Ini:
                                        {{ activeFeedback.attendance.status_label || activeFeedback.attendance.status }}
                                    </span>
                                    <span v-if="activeFeedback.attendance.check_in_at">
                                        | Jam Masuk: {{ formatScanTime(activeFeedback.attendance.check_in_at) }}
                                    </span>
                                    <span v-if="activeFeedback.attendance.check_out_at">
                                        | Jam Pulang: {{ formatScanTime(activeFeedback.attendance.check_out_at) }}
                                    </span>
                                </div>

                                <p class="mt-3 font-mono text-xs font-semibold">
                                    UID Kartu: {{ activeFeedback.uid_normalized || 'Tidak Dikenal' }} |
                                    Waktu Tap: {{ formatScanTime(activeFeedback.scanned_at) }} WIB
                                </p>
                            </div>
                        </div>
                    </div>

                    <div v-else class="py-12 text-center">
                        <div class="mx-auto flex h-24 w-24 items-center justify-center rounded border-2 border-emerald-800 bg-emerald-50 font-mono text-xl font-bold text-emerald-950">
                            NFC
                        </div>
                        <h1 class="mt-4 text-2xl font-bold text-slate-900 sm:text-3xl">
                            Siap Memindai Kartu NFC Siswa
                        </h1>
                        <p class="mx-auto mt-2 max-w-md text-base text-slate-700">
                            Tempelkan kartu NFC pada pembaca USB. Identitas siswa dan status kehadiran akan langsung tampil di layar ini.
                        </p>
                        <p class="mt-4 text-xs font-semibold text-slate-700">
                            Layar otomatis kembali siap 3 detik setelah setiap pemindaian.
                        </p>
                    </div>
                </section>

                <aside
                    aria-labelledby="recent-scans-heading"
                    class="flex flex-col rounded border border-slate-300 bg-white p-5 lg:col-span-5"
                >
                    <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                        <h2 id="recent-scans-heading" class="text-base font-bold text-slate-900">
                            10 Pemindaian Terakhir
                        </h2>
                        <span class="text-xs font-semibold text-slate-700">
                            {{ recentLogs.length }} catatan
                        </span>
                    </div>

                    <div
                        v-if="recentLogs.length === 0"
                        class="flex flex-1 items-center justify-center py-12 text-center text-sm text-slate-700"
                    >
                        Belum ada pemindaian kartu pada pos ini. Tempelkan kartu NFC untuk memulai pencatatan.
                    </div>

                    <ul v-else class="mt-3 divide-y divide-slate-200">
                        <li
                            v-for="item in recentLogs"
                            :key="item.id"
                            class="flex items-center justify-between gap-3 py-2.5 text-sm"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold text-slate-900">
                                    {{ item.student?.name || `Kartu ${item.uid_normalized || item.uid_raw}` }}
                                    <span
                                        v-if="item.student?.classroom"
                                        class="text-xs font-normal text-slate-700"
                                    >
                                        ({{ item.student.classroom }})
                                    </span>
                                </p>
                                <p class="truncate text-xs text-slate-700">
                                    {{ item.message }}
                                </p>
                            </div>

                            <div class="shrink-0 text-right">
                                <span
                                    class="inline-block rounded border px-2 py-0.5 text-xs font-bold"
                                    :class="{
                                        'border-emerald-800 bg-emerald-50 text-emerald-950': item.sound_tone === 'success',
                                        'border-sky-800 bg-sky-50 text-sky-950': item.sound_tone === 'duplicate',
                                        'border-amber-800 bg-amber-50 text-amber-950': item.sound_tone === 'warning',
                                        'border-red-800 bg-red-50 text-red-950': item.sound_tone === 'error',
                                    }"
                                >
                                    {{ item.result }}
                                </span>
                                <p class="mt-0.5 font-mono text-xs text-slate-700">
                                    {{ item.scanned_time_formatted || formatScanTime(item.scanned_at) }}
                                </p>
                            </div>
                        </li>
                    </ul>
                </aside>
            </div>
        </main>

        <footer class="border-t border-slate-300 bg-white px-4 py-3 text-xs text-slate-700 sm:px-6">
            <div class="mx-auto flex max-w-7xl flex-col justify-between gap-2 sm:flex-row">
                <span>Pos Absensi Aktif: Ketikan manual lambat pada papan ketik diabaikan secara otomatis.</span>
                <span>Draft without direction (ENERGY 1 / RHYTHM 1 / MOTION 1)</span>
            </div>
        </footer>

        <div
            v-if="showTokenDialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="station-token-title"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4"
            @click.stop
        >
            <div class="w-full max-w-lg rounded border border-slate-400 bg-white p-6">
                <h2 id="station-token-title" class="text-lg font-bold text-slate-900">
                    Pengaturan Token Perangkat Pos Absensi
                </h2>
                <p class="mt-1 text-sm text-slate-700">
                    Masukkan token perangkat yang diterbitkan oleh Super Admin pada menu Perangkat Pos. Token disimpan di peramban komputer pos ini.
                </p>

                <form @submit.prevent="saveTokenSettings" class="mt-4 space-y-4">
                    <div>
                        <label for="station-token-input" class="block text-sm font-semibold text-slate-900">
                            Token Bearer Perangkat
                        </label>
                        <input
                            id="station-token-input"
                            v-model="tokenInput"
                            type="text"
                            required
                            placeholder="Tempelkan token perangkat pos di sini"
                            class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 px-3 py-2 font-mono text-sm text-slate-900 focus:border-emerald-700 focus:ring-emerald-700"
                        />
                    </div>

                    <div v-if="props.defaultDevToken" class="rounded border border-slate-300 bg-slate-50 p-3 text-xs text-slate-800">
                        <p class="font-semibold">Mode Pengembangan Lokal:</p>
                        <p class="mt-0.5">
                            Tersedia token bawaan seeder untuk Pos Gerbang Utama.
                        </p>
                        <button
                            type="button"
                            @click="useDefaultDevToken"
                            class="mt-2 inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-3 py-1.5 text-xs font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                        >
                            Gunakan Token Demo Seeder
                        </button>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            @click="showTokenDialog = false"
                            class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            class="inline-flex min-h-[44px] items-center rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                        >
                            Simpan Token Pos
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

