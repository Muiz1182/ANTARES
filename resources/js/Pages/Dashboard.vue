<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import type {
    AcademicYearItem,
    ClassroomItem,
    DashboardClassBreakdownItem,
    DashboardCounts,
    DashboardDataPayload,
    DashboardRecentTapItem,
    DashboardUnarrivedStudent,
} from '@/types/models';

const props = defineProps<{
    activeAcademicYear: AcademicYearItem | null;
    classrooms: ClassroomItem[];
    dashboard: DashboardDataPayload;
    filters: {
        date: string;
        classroom_id: number | null;
    };
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);

const filterDate = ref<string>(props.filters.date);
const filterClassroomId = ref<number | ''>(props.filters.classroom_id ?? '');

const liveCounts = ref<DashboardCounts>({ ...props.dashboard.counts });
const liveClassBreakdown = ref<DashboardClassBreakdownItem[]>([
    ...props.dashboard.class_breakdown,
]);
const liveUnarrivedStudents = ref<DashboardUnarrivedStudent[]>([
    ...props.dashboard.not_yet_arrived_students,
]);
const liveRecentTaps = ref<DashboardRecentTapItem[]>([
    ...props.dashboard.recent_taps,
]);
const lastUpdatedTime = ref<string>(props.dashboard.generated_at);
const isLivePollingActive = ref<boolean>(true);
const isFetchingLiveState = ref<boolean>(false);

watch(
    () => props.dashboard,
    (next) => {
        liveCounts.value = { ...next.counts };
        liveClassBreakdown.value = [...next.class_breakdown];
        liveUnarrivedStudents.value = [...next.not_yet_arrived_students];
        liveRecentTaps.value = [...next.recent_taps];
        lastUpdatedTime.value = next.generated_at;
    },
);

function applyFilters(): void {
    router.get(
        route('dashboard'),
        {
            date: filterDate.value || undefined,
            classroom_id: filterClassroomId.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}

async function pollLiveState(): Promise<void> {
    if (!isLivePollingActive.value || isFetchingLiveState.value || document.hidden) {
        return;
    }

    isFetchingLiveState.value = true;
    try {
        const params = new URLSearchParams();
        if (filterDate.value) params.set('date', filterDate.value);
        if (filterClassroomId.value) params.set('classroom_id', String(filterClassroomId.value));

        const url = `${route('dashboard.live-state')}?${params.toString()}`;
        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (response.ok) {
            const payload = await response.json();
            liveCounts.value = payload.counts;
            liveClassBreakdown.value = payload.class_breakdown;
            liveUnarrivedStudents.value = payload.not_yet_arrived_students;
            liveRecentTaps.value = payload.recent_taps;
            lastUpdatedTime.value = payload.generated_at;
        }
    } catch {
        // Keep current dashboard state when network is briefly unavailable
    } finally {
        isFetchingLiveState.value = false;
    }
}

let pollTimer: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    pollTimer = setInterval(() => {
        void pollLiveState();
    }, 2000);
});

onUnmounted(() => {
    if (pollTimer !== null) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
});

const maxTrendDayTotal = computed(() => {
    let max = 1;
    for (const item of props.dashboard.trend_30_days) {
        const total = item.present + item.late + item.excused + item.absent;
        if (total > max) max = total;
    }
    return max;
});

function barHeightPercent(value: number): number {
    if (value <= 0) return 0;
    return Math.max(4, Math.round((value / maxTrendDayTotal.value) * 100));
}

function tapBadgeClass(tone: string): string {
    switch (tone) {
        case 'success':
            return 'border-emerald-800 bg-emerald-50 text-emerald-950';
        case 'duplicate':
            return 'border-amber-800 bg-amber-50 text-amber-950';
        case 'warning':
            return 'border-sky-800 bg-sky-50 text-sky-950';
        default:
            return 'border-red-800 bg-red-50 text-red-950';
    }
}
</script>

<template>
    <Head title="Ringkasan & Dashboard Real-Time" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col justify-between gap-3 lg:flex-row lg:items-center">
                <div>
                    <h1 class="text-xl font-bold text-slate-900">
                        Dashboard Kehadiran Real-Time
                    </h1>
                    <p class="text-sm text-slate-700">
                        Masuk sebagai {{ user.name }} ({{ user.role_labels.join(', ') }}) | Tahun Ajaran Aktif:
                        <span class="font-semibold text-emerald-800">
                            {{ activeAcademicYear ? activeAcademicYear.name : 'Belum diatur' }}
                        </span>
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <span
                        class="inline-flex min-h-[44px] items-center gap-2 rounded border border-slate-400 bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-900"
                    >
                        <span
                            class="h-2.5 w-2.5 rounded-full"
                            :class="isLivePollingActive ? 'bg-emerald-700' : 'bg-slate-500'"
                        />
                        <span>Sinkronisasi Live (2 dtk): {{ lastUpdatedTime }} WIB</span>
                    </span>

                    <button
                        type="button"
                        @click="isLivePollingActive = !isLivePollingActive"
                        class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-3 py-2 text-xs font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                    >
                        {{ isLivePollingActive ? 'Jeda Pembaruan' : 'Aktifkan Pembaruan' }}
                    </button>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <section
                aria-label="Filter Dashboard"
                class="rounded border border-slate-300 bg-white p-4"
            >
                <form
                    @submit.prevent="applyFilters"
                    class="grid grid-cols-1 gap-4 sm:grid-cols-3"
                >
                    <div>
                        <label
                            for="dashboard-filter-date"
                            class="block text-sm font-semibold text-slate-900"
                        >
                            Tanggal Pantauan
                        </label>
                        <input
                            id="dashboard-filter-date"
                            v-model="filterDate"
                            type="date"
                            @change="applyFilters"
                            class="mt-1 min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700"
                        />
                    </div>

                    <div>
                        <label
                            for="dashboard-filter-classroom"
                            class="block text-sm font-semibold text-slate-900"
                        >
                            Cakupan Kelas
                        </label>
                        <select
                            id="dashboard-filter-classroom"
                            v-model="filterClassroomId"
                            @change="applyFilters"
                            class="mt-1 min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700"
                        >
                            <option value="">Semua Kelas Tersedia</option>
                            <option
                                v-for="classroom in classrooms"
                                :key="classroom.id"
                                :value="classroom.id"
                            >
                                {{ classroom.name }} (Tingkat {{ classroom.level }})
                            </option>
                        </select>
                    </div>

                    <div class="flex items-end gap-2">
                        <button
                            type="submit"
                            class="inline-flex min-h-[44px] items-center rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                        >
                            Terapkan Filter
                        </button>
                        <Link
                            v-if="user.can.view_reports"
                            :href="route('reports.index', { classroom_id: filterClassroomId || undefined })"
                            class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                        >
                            Buka Laporan Bulanan
                        </Link>
                    </div>
                </form>
            </section>

            <section
                aria-label="Angka Kehadiran Hari Ini"
                class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7"
            >
                <div class="rounded border border-slate-300 bg-white p-3.5">
                    <p class="text-xs font-semibold uppercase text-slate-700">Siswa Aktif</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">
                        {{ liveCounts.total_active_students }}
                    </p>
                </div>
                <div class="rounded border border-emerald-800 bg-emerald-50 p-3.5">
                    <p class="text-xs font-semibold uppercase text-emerald-950">Hadir (H)</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-950">
                        {{ liveCounts.present }}
                    </p>
                </div>
                <div class="rounded border border-amber-800 bg-amber-50 p-3.5">
                    <p class="text-xs font-semibold uppercase text-amber-950">Terlambat (T)</p>
                    <p class="mt-1 text-2xl font-bold text-amber-950">
                        {{ liveCounts.late }}
                    </p>
                </div>
                <div class="rounded border border-sky-800 bg-sky-50 p-3.5">
                    <p class="text-xs font-semibold uppercase text-sky-950">Izin (I)</p>
                    <p class="mt-1 text-2xl font-bold text-sky-950">
                        {{ liveCounts.permission }}
                    </p>
                </div>
                <div class="rounded border border-sky-800 bg-sky-50 p-3.5">
                    <p class="text-xs font-semibold uppercase text-sky-950">Sakit / Disp.</p>
                    <p class="mt-1 text-2xl font-bold text-sky-950">
                        {{ liveCounts.sick + liveCounts.dispensation }}
                    </p>
                </div>
                <div class="rounded border border-red-800 bg-red-50 p-3.5">
                    <p class="text-xs font-semibold uppercase text-red-950">Alpa (A)</p>
                    <p class="mt-1 text-2xl font-bold text-red-950">
                        {{ liveCounts.absent }}
                    </p>
                </div>
                <div class="rounded border border-slate-400 bg-slate-100 p-3.5">
                    <p class="text-xs font-semibold uppercase text-slate-800">Belum Hadir</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">
                        {{ liveCounts.not_yet_arrived }}
                    </p>
                </div>
            </section>

            <section
                aria-labelledby="class-breakdown-heading"
                class="rounded border border-slate-300 bg-white"
            >
                <div class="border-b border-slate-300 px-4 py-3 sm:px-6">
                    <h2 id="class-breakdown-heading" class="text-base font-bold text-slate-900">
                        Rekap Kehadiran Hari Ini per Kelas ({{ filterDate }})
                    </h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-300 text-left text-sm">
                        <thead class="bg-slate-100 text-xs font-bold uppercase text-slate-800">
                            <tr>
                                <th scope="col" class="px-4 py-3">Kelas</th>
                                <th scope="col" class="px-4 py-3">Wali Kelas</th>
                                <th scope="col" class="px-4 py-3 text-right">Siswa</th>
                                <th scope="col" class="px-4 py-3 text-right">Hadir</th>
                                <th scope="col" class="px-4 py-3 text-right">Terlambat</th>
                                <th scope="col" class="px-4 py-3 text-right">Izin/Sakit</th>
                                <th scope="col" class="px-4 py-3 text-right">Alpa</th>
                                <th scope="col" class="px-4 py-3 text-right">Belum Hadir</th>
                                <th scope="col" class="px-4 py-3 text-right">% Hadir</th>
                                <th scope="col" class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            <tr v-if="liveClassBreakdown.length === 0">
                                <td colspan="10" class="px-4 py-6 text-center text-sm text-slate-700">
                                    Belum ada kelas yang tersedia untuk ditampilkan.
                                </td>
                            </tr>
                            <tr
                                v-for="row in liveClassBreakdown"
                                :key="row.classroom_id"
                                class="hover:bg-slate-50"
                            >
                                <td class="whitespace-nowrap px-4 py-3 font-bold text-slate-900">
                                    {{ row.classroom_name }} (Tingkat {{ row.level }})
                                </td>
                                <td class="px-4 py-3 text-slate-800">
                                    {{ row.homeroom_teacher_name ?? 'Belum ditentukan' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-mono font-semibold text-slate-900">
                                    {{ row.total_students }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-mono font-semibold text-emerald-900">
                                    {{ row.present }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-mono font-semibold text-amber-900">
                                    {{ row.late }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-mono font-semibold text-sky-900">
                                    {{ row.excused }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-mono font-semibold text-red-900">
                                    {{ row.absent }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-mono font-semibold text-slate-800">
                                    {{ row.not_yet_arrived }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-mono font-bold text-slate-900">
                                    {{ row.attendance_rate }}%
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <Link
                                        v-if="user.can.view_attendances"
                                        :href="route('attendances.today', { classroom_id: row.classroom_id, date: filterDate })"
                                        class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-3 py-1.5 text-xs font-semibold text-slate-900 hover:bg-slate-100"
                                    >
                                        Detail Kelas
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <section
                    aria-labelledby="live-feed-heading"
                    class="rounded border border-slate-300 bg-white"
                >
                    <div class="flex items-center justify-between border-b border-slate-300 px-4 py-3 sm:px-6">
                        <h2 id="live-feed-heading" class="text-base font-bold text-slate-900">
                            Umpan Langsung Tap Kartu Terkini
                        </h2>
                        <span class="text-xs font-semibold text-slate-700">
                            15 Tap Terakhir
                        </span>
                    </div>

                    <div class="divide-y divide-slate-200">
                        <div
                            v-if="liveRecentTaps.length === 0"
                            class="px-4 py-8 text-center text-sm text-slate-700"
                        >
                            Belum ada pemindaian kartu NFC pada tanggal ini.
                        </div>
                        <div
                            v-for="tap in liveRecentTaps"
                            :key="tap.id"
                            class="flex items-center justify-between gap-3 px-4 py-3 sm:px-6"
                        >
                            <div>
                                <div class="font-bold text-slate-900">
                                    {{ tap.student ? `${tap.student.name} (${tap.student.classroom ?? '-'})` : `UID: ${tap.uid_normalized ?? tap.uid_raw}` }}
                                </div>
                                <div class="text-xs text-slate-700">
                                    {{ tap.message }} | {{ tap.device_name }}
                                </div>
                            </div>
                            <div class="text-right">
                                <span
                                    class="inline-flex items-center rounded border px-2 py-0.5 text-xs font-bold"
                                    :class="tapBadgeClass(tap.sound_tone)"
                                >
                                    {{ tap.scanned_time }} WIB
                                </span>
                            </div>
                        </div>
                    </div>
                </section>

                <section
                    aria-labelledby="unarrived-heading"
                    class="rounded border border-slate-300 bg-white"
                >
                    <div class="flex items-center justify-between border-b border-slate-300 px-4 py-3 sm:px-6">
                        <h2 id="unarrived-heading" class="text-base font-bold text-slate-900">
                            Siswa Belum Hadir Hari Ini
                        </h2>
                        <span class="text-xs font-semibold text-slate-700">
                            Total: {{ liveCounts.not_yet_arrived }} siswa
                        </span>
                    </div>

                    <div class="max-h-96 overflow-y-auto divide-y divide-slate-200">
                        <div
                            v-if="liveUnarrivedStudents.length === 0"
                            class="px-4 py-8 text-center text-sm text-slate-700"
                        >
                            Seluruh siswa aktif telah memiliki catatan kehadiran pada tanggal ini.
                        </div>
                        <div
                            v-for="st in liveUnarrivedStudents"
                            :key="st.id"
                            class="flex items-center justify-between gap-3 px-4 py-2.5 sm:px-6"
                        >
                            <div>
                                <div class="text-sm font-bold text-slate-900">
                                    {{ st.name }}
                                </div>
                                <div class="font-mono text-xs text-slate-700">
                                    NIS: {{ st.nis }}
                                </div>
                            </div>
                            <span class="rounded border border-slate-400 bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-900">
                                Kelas {{ st.classroom_name }}
                            </span>
                        </div>
                    </div>
                </section>
            </div>

            <section
                aria-labelledby="trend-chart-heading"
                class="rounded border border-slate-300 bg-white p-4 sm:p-6"
            >
                <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                    <div>
                        <h2 id="trend-chart-heading" class="text-base font-bold text-slate-900">
                            Tren Kehadiran 30 Hari Terakhir
                        </h2>
                        <p class="text-xs text-slate-700">
                            Perbandingan harian status Hadir, Terlambat, Izin/Sakit, dan Alpa selama 30 hari terakhir.
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 text-xs font-semibold text-slate-800">
                        <span class="inline-flex items-center gap-1.5">
                            <span class="h-3 w-3 rounded-sm bg-emerald-700" /> Hadir
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <span class="h-3 w-3 rounded-sm bg-amber-600" /> Terlambat
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <span class="h-3 w-3 rounded-sm bg-sky-700" /> Izin/Sakit
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <span class="h-3 w-3 rounded-sm bg-red-700" /> Alpa
                        </span>
                    </div>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <div class="flex min-w-[680px] items-end gap-1.5 border-b border-slate-300 pb-2 pt-4" style="height: 180px;">
                        <div
                            v-for="day in dashboard.trend_30_days"
                            :key="day.date"
                            class="flex flex-1 flex-col items-center justify-end h-full"
                            :title="`${day.date}: Hadir ${day.present}, Terlambat ${day.late}, Izin/Sakit ${day.excused}, Alpa ${day.absent}`"
                        >
                            <div class="flex w-full flex-col justify-end h-full">
                                <div
                                    v-if="day.absent > 0"
                                    class="w-full bg-red-700"
                                    :style="{ height: `${barHeightPercent(day.absent)}%` }"
                                />
                                <div
                                    v-if="day.excused > 0"
                                    class="w-full bg-sky-700"
                                    :style="{ height: `${barHeightPercent(day.excused)}%` }"
                                />
                                <div
                                    v-if="day.late > 0"
                                    class="w-full bg-amber-600"
                                    :style="{ height: `${barHeightPercent(day.late)}%` }"
                                />
                                <div
                                    v-if="day.present > 0"
                                    class="w-full bg-emerald-700"
                                    :style="{ height: `${barHeightPercent(day.present)}%` }"
                                />
                            </div>
                            <span class="mt-1.5 text-[10px] font-mono font-semibold text-slate-700">
                                {{ day.label }}
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            <section aria-labelledby="quick-access-heading" class="space-y-4">
                <h2 id="quick-access-heading" class="text-base font-bold text-slate-900">
                    Menu Pengelolaan Sesuai Peran Anda
                </h2>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-if="user.can.operate_scan"
                        class="flex flex-col justify-between rounded border border-slate-300 bg-white p-5"
                    >
                        <div>
                            <h3 class="text-base font-bold text-slate-900">Pos Absensi NFC (Scan Station)</h3>
                            <p class="mt-1 text-sm text-slate-700">
                                Layar penuh pemindaian kartu NFC siswa waktu nyata dengan umpan balik suara dan daftar 10 tap terakhir.
                            </p>
                        </div>
                        <div class="mt-4">
                            <Link
                                :href="route('scan-station.index')"
                                class="inline-flex min-h-[44px] items-center rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                            >
                                Buka Pos Absensi
                            </Link>
                        </div>
                    </div>

                    <div
                        v-if="user.can.view_attendances"
                        class="flex flex-col justify-between rounded border border-slate-300 bg-white p-5"
                    >
                        <div>
                            <h3 class="text-base font-bold text-slate-900">Kehadiran Harian per Kelas</h3>
                            <p class="mt-1 text-sm text-slate-700">
                                Pantau kehadiran harian siswa per kelas, catat izin/sakit/dispensasi, atau lakukan koreksi data berjejak audit.
                            </p>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <Link
                                :href="route('attendances.today')"
                                class="inline-flex min-h-[44px] items-center rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                            >
                                Buka Kehadiran Kelas
                            </Link>
                            <Link
                                v-if="user.can.manage_rules"
                                :href="route('attendance-rules.index')"
                                class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                            >
                                Aturan Jam
                            </Link>
                        </div>
                    </div>

                    <div
                        v-if="user.can.view_reports"
                        class="flex flex-col justify-between rounded border border-slate-300 bg-white p-5"
                    >
                        <div>
                            <h3 class="text-base font-bold text-slate-900">Laporan &amp; Ekspor Rekap Bulanan</h3>
                            <p class="mt-1 text-sm text-slate-700">
                                Matriks kehadiran bulanan per kelas (H/T/I/S/A/D), riwayat per siswa, peringkat keterlambatan, serta ekspor Excel dan PDF.
                            </p>
                        </div>
                        <div class="mt-4">
                            <Link
                                :href="route('reports.index')"
                                class="inline-flex min-h-[44px] items-center rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                            >
                                Buka Laporan &amp; Ekspor
                            </Link>
                        </div>
                    </div>

                    <div
                        v-if="user.can.manage_cards"
                        class="flex flex-col justify-between rounded border border-slate-300 bg-white p-5"
                    >
                        <div>
                            <h3 class="text-base font-bold text-slate-900">Registrasi &amp; Manajemen Kartu NFC</h3>
                            <p class="mt-1 text-sm text-slate-700">
                                Pasangkan kartu NFC ke siswa, ganti kartu hilang atau rusak, serta lakukan registrasi massal beruntun per kelas.
                            </p>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <Link
                                :href="route('cards.index')"
                                class="inline-flex min-h-[44px] items-center rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                            >
                                Kelola Kartu NFC
                            </Link>
                            <Link
                                :href="route('cards.bulk')"
                                class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                            >
                                Registrasi Massal
                            </Link>
                        </div>
                    </div>

                    <div
                        v-if="user.can.view_students"
                        class="flex flex-col justify-between rounded border border-slate-300 bg-white p-5"
                    >
                        <div>
                            <h3 class="text-base font-bold text-slate-900">Data Siswa &amp; Kelas</h3>
                            <p class="mt-1 text-sm text-slate-700">
                                Lihat daftar siswa, kelola penempatan kelas, dan impor data siswa dari Excel atau CSV.
                            </p>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <Link
                                :href="route('students.index')"
                                class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-100"
                            >
                                Data Siswa
                            </Link>
                            <Link
                                v-if="user.can.view_classrooms"
                                :href="route('classrooms.index')"
                                class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-100"
                            >
                                Daftar Kelas
                            </Link>
                        </div>
                    </div>

                    <div
                        v-if="user.can.manage_settings || user.can.manage_users || user.can.view_audit_logs"
                        class="flex flex-col justify-between rounded border border-slate-300 bg-white p-5"
                    >
                        <div>
                            <h3 class="text-base font-bold text-slate-900">Pengaturan Sistem &amp; Audit</h3>
                            <p class="mt-1 text-sm text-slate-700">
                                Konfigurasi identitas &amp; logo sekolah, pengguna sistem, perangkat pos, serta pantau log audit perubahan data.
                            </p>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <Link
                                v-if="user.can.manage_settings"
                                :href="route('settings.edit')"
                                class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-100"
                            >
                                Pengaturan Sekolah
                            </Link>
                            <Link
                                v-if="user.can.view_audit_logs"
                                :href="route('audit-logs.index')"
                                class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-100"
                            >
                                Log Audit
                            </Link>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
