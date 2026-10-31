<script setup lang="ts">
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import type {
    ClassroomItem,
    MonthlyClassMatrixPayload,
    SchoolSettingItem,
    StudentHistoryRecordItem,
    TopAbsentStudentItem,
    TopLateStudentItem,
} from '@/types/models';

const props = defineProps<{
    classrooms: ClassroomItem[];
    selectedClassroomId: number | null;
    matrix: MonthlyClassMatrixPayload | null;
    topLate: TopLateStudentItem[];
    topAbsent: TopAbsentStudentItem[];
    classStudents: Array<{ id: number; nis: string; name: string }>;
    selectedStudentId: number | null;
    studentHistory: StudentHistoryRecordItem[];
    schoolSetting: SchoolSettingItem;
    filters: {
        classroom_id: number | null;
        month: string;
        date_from: string;
        date_to: string;
        student_id: number | null;
    };
}>();

const filterClassroomId = ref<number | ''>(props.filters.classroom_id ?? '');
const filterMonth = ref<string>(props.filters.month);
const filterDateFrom = ref<string>(props.filters.date_from);
const filterDateTo = ref<string>(props.filters.date_to);
const filterStudentId = ref<number | ''>(props.filters.student_id ?? '');

function applyFilters(): void {
    router.get(
        route('reports.index'),
        {
            classroom_id: filterClassroomId.value || undefined,
            month: filterMonth.value || undefined,
            date_from: filterDateFrom.value || undefined,
            date_to: filterDateTo.value || undefined,
            student_id: filterStudentId.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}

const excelExportUrl = computed(() =>
    route('reports.export.excel', {
        classroom_id: filterClassroomId.value || undefined,
        month: filterMonth.value || undefined,
    }),
);

const pdfExportUrl = computed(() =>
    route('reports.export.pdf', {
        classroom_id: filterClassroomId.value || undefined,
        month: filterMonth.value || undefined,
    }),
);

function printPage(): void {
    window.print();
}

function cellCodeClass(code: string, isSchoolDay: boolean): string {
    switch (code) {
        case 'H':
            return 'bg-emerald-50 text-emerald-950 font-bold';
        case 'T':
            return 'bg-amber-50 text-amber-950 font-bold';
        case 'I':
        case 'S':
        case 'D':
            return 'bg-sky-50 text-sky-950 font-bold';
        case 'A':
            return 'bg-red-50 text-red-950 font-bold';
        default:
            return isSchoolDay
                ? 'bg-white text-slate-600'
                : 'bg-slate-100 text-slate-600';
    }
}
</script>

<template>
    <Head title="Laporan & Rekap Kehadiran" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col justify-between gap-3 lg:flex-row lg:items-center">
                <div>
                    <h1 class="text-xl font-bold text-slate-900">
                        Laporan &amp; Rekapitulasi Kehadiran
                    </h1>
                    <p class="text-sm text-slate-700">
                        Matriks rekap bulanan kelas (H/T/I/S/A/D), riwayat per siswa, peringkat keterlambatan/alpa, serta ekspor Excel dan PDF.
                    </p>
                </div>

                <div v-if="matrix" class="flex flex-wrap items-center gap-2">
                    <a
                        :href="excelExportUrl"
                        class="inline-flex min-h-[44px] items-center rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                    >
                        Ekspor Excel (.xlsx)
                    </a>
                    <a
                        :href="pdfExportUrl"
                        class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                    >
                        Ekspor PDF (.pdf)
                    </a>
                    <button
                        type="button"
                        @click="printPage"
                        class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                    >
                        Cetak Halaman
                    </button>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <section
                aria-label="Filter Laporan"
                class="rounded border border-slate-300 bg-white p-4 sm:p-5 print:hidden"
            >
                <form
                    @submit.prevent="applyFilters"
                    class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5"
                >
                    <div>
                        <label
                            for="report-filter-classroom"
                            class="block text-sm font-semibold text-slate-900"
                        >
                            Kelas
                        </label>
                        <select
                            id="report-filter-classroom"
                            v-model="filterClassroomId"
                            @change="applyFilters"
                            class="mt-1 min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700"
                        >
                            <option
                                v-for="classroom in classrooms"
                                :key="classroom.id"
                                :value="classroom.id"
                            >
                                {{ classroom.name }} (Tingkat {{ classroom.level }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <label
                            for="report-filter-month"
                            class="block text-sm font-semibold text-slate-900"
                        >
                            Bulan Rekap Matriks
                        </label>
                        <input
                            id="report-filter-month"
                            v-model="filterMonth"
                            type="month"
                            @change="applyFilters"
                            class="mt-1 min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700"
                        />
                    </div>

                    <div>
                        <label
                            for="report-filter-from"
                            class="block text-sm font-semibold text-slate-900"
                        >
                            Dari Tanggal (Analitik)
                        </label>
                        <input
                            id="report-filter-from"
                            v-model="filterDateFrom"
                            type="date"
                            class="mt-1 min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700"
                        />
                    </div>

                    <div>
                        <label
                            for="report-filter-to"
                            class="block text-sm font-semibold text-slate-900"
                        >
                            Sampai Tanggal (Analitik)
                        </label>
                        <input
                            id="report-filter-to"
                            v-model="filterDateTo"
                            type="date"
                            class="mt-1 min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700"
                        />
                    </div>

                    <div class="flex items-end">
                        <button
                            type="submit"
                            class="inline-flex min-h-[44px] w-full items-center justify-center rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                        >
                            Tampilkan Laporan
                        </button>
                    </div>
                </form>
            </section>

            <section
                v-if="matrix"
                aria-labelledby="monthly-matrix-heading"
                class="rounded border border-slate-300 bg-white"
            >
                <div class="border-b border-slate-300 px-4 py-4 sm:px-6">
                    <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                        <div class="flex items-center gap-3">
                            <img
                                v-if="schoolSetting.logo_url"
                                :src="schoolSetting.logo_url"
                                :alt="schoolSetting.school_name"
                                class="h-12 w-12 rounded object-contain"
                            />
                            <div>
                                <p class="text-xs font-bold uppercase text-slate-700">
                                    {{ schoolSetting.school_name }}
                                </p>
                                <h2 id="monthly-matrix-heading" class="text-base font-bold text-slate-900">
                                    Matriks Kehadiran Bulanan Kelas {{ matrix.classroom.name }} | Periode {{ matrix.month_label }}
                                </h2>
                                <p class="text-xs text-slate-700">
                                    Wali Kelas: {{ matrix.classroom.homeroom_teacher_name ?? 'Belum ditentukan' }} | Keterangan Kode: H = Hadir, T = Terlambat, I = Izin, S = Sakit, A = Alpa, D = Dispensasi
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-300 text-left text-xs">
                        <thead class="bg-slate-100 font-bold text-slate-800">
                            <tr>
                                <th scope="col" class="px-2.5 py-2.5">No</th>
                                <th scope="col" class="px-2.5 py-2.5">NIS</th>
                                <th scope="col" class="px-3 py-2.5">Nama Siswa</th>
                                <th scope="col" class="px-2 py-2.5 text-center">L/P</th>
                                <th
                                    v-for="day in matrix.days"
                                    :key="day.date"
                                    scope="col"
                                    class="px-1.5 py-2.5 text-center font-mono"
                                    :class="day.is_school_day ? 'text-slate-900' : 'bg-slate-200 text-slate-600'"
                                    :title="day.holiday_name ? `${day.date} (${day.holiday_name})` : `${day.date} (${day.day_label})`"
                                >
                                    {{ day.day_number }}
                                </th>
                                <th scope="col" class="bg-emerald-50 px-2 py-2.5 text-center text-emerald-950">H</th>
                                <th scope="col" class="bg-amber-50 px-2 py-2.5 text-center text-amber-950">T</th>
                                <th scope="col" class="bg-sky-50 px-2 py-2.5 text-center text-sky-950">I</th>
                                <th scope="col" class="bg-sky-50 px-2 py-2.5 text-center text-sky-950">S</th>
                                <th scope="col" class="bg-red-50 px-2 py-2.5 text-center text-red-950">A</th>
                                <th scope="col" class="bg-sky-50 px-2 py-2.5 text-center text-sky-950">D</th>
                                <th scope="col" class="px-2.5 py-2.5 text-right">% Hadir</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            <tr v-if="matrix.rows.length === 0">
                                <td :colspan="matrix.days.length + 11" class="px-4 py-8 text-center text-sm text-slate-700">
                                    Tidak ada siswa aktif di kelas ini.
                                </td>
                            </tr>
                            <tr
                                v-for="(row, idx) in matrix.rows"
                                :key="row.student_id"
                                class="hover:bg-slate-50"
                            >
                                <td class="px-2.5 py-2 font-mono text-slate-700">{{ idx + 1 }}</td>
                                <td class="whitespace-nowrap px-2.5 py-2 font-mono font-semibold text-slate-900">
                                    {{ row.nis }}
                                </td>
                                <td class="whitespace-nowrap px-3 py-2 font-semibold text-slate-900">
                                    {{ row.name }}
                                </td>
                                <td class="px-2 py-2 text-center font-semibold text-slate-800">
                                    {{ row.gender }}
                                </td>
                                <td
                                    v-for="day in matrix.days"
                                    :key="day.date"
                                    class="border-x border-slate-100 px-1 py-2 text-center font-mono"
                                    :class="cellCodeClass(row.cells[day.date] ?? '-', day.is_school_day)"
                                >
                                    {{ row.cells[day.date] ?? '-' }}
                                </td>
                                <td class="bg-emerald-50/60 px-2 py-2 text-center font-mono font-bold text-emerald-950">
                                    {{ row.totals.H }}
                                </td>
                                <td class="bg-amber-50/60 px-2 py-2 text-center font-mono font-bold text-amber-950">
                                    {{ row.totals.T }}
                                </td>
                                <td class="bg-sky-50/60 px-2 py-2 text-center font-mono font-bold text-sky-950">
                                    {{ row.totals.I }}
                                </td>
                                <td class="bg-sky-50/60 px-2 py-2 text-center font-mono font-bold text-sky-950">
                                    {{ row.totals.S }}
                                </td>
                                <td class="bg-red-50/60 px-2 py-2 text-center font-mono font-bold text-red-950">
                                    {{ row.totals.A }}
                                </td>
                                <td class="bg-sky-50/60 px-2 py-2 text-center font-mono font-bold text-sky-950">
                                    {{ row.totals.D }}
                                </td>
                                <td class="whitespace-nowrap px-2.5 py-2 text-right font-mono font-bold text-slate-900">
                                    {{ row.totals.attendance_rate }}%
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="border-t-2 border-slate-300 bg-slate-100 font-bold text-slate-900">
                            <tr>
                                <td :colspan="matrix.days.length + 4" class="px-3 py-2.5 text-right">
                                    Total Rekap Kelas {{ matrix.classroom.name }}:
                                </td>
                                <td class="px-2 py-2.5 text-center font-mono text-emerald-950">
                                    {{ matrix.class_totals.H }}
                                </td>
                                <td class="px-2 py-2.5 text-center font-mono text-amber-950">
                                    {{ matrix.class_totals.T }}
                                </td>
                                <td class="px-2 py-2.5 text-center font-mono text-sky-950">
                                    {{ matrix.class_totals.I }}
                                </td>
                                <td class="px-2 py-2.5 text-center font-mono text-sky-950">
                                    {{ matrix.class_totals.S }}
                                </td>
                                <td class="px-2 py-2.5 text-center font-mono text-red-950">
                                    {{ matrix.class_totals.A }}
                                </td>
                                <td class="px-2 py-2.5 text-center font-mono text-sky-950">
                                    {{ matrix.class_totals.D }}
                                </td>
                                <td class="px-2.5 py-2.5" />
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <section
                    aria-labelledby="top-late-heading"
                    class="rounded border border-slate-300 bg-white"
                >
                    <div class="border-b border-slate-300 px-4 py-3 sm:px-6">
                        <h2 id="top-late-heading" class="text-base font-bold text-slate-900">
                            Siswa Paling Sering Terlambat ({{ filterDateFrom }} s.d. {{ filterDateTo }})
                        </h2>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-300 text-left text-sm">
                            <thead class="bg-slate-100 text-xs font-bold uppercase text-slate-800">
                                <tr>
                                    <th scope="col" class="px-4 py-2.5">NIS</th>
                                    <th scope="col" class="px-4 py-2.5">Nama Siswa</th>
                                    <th scope="col" class="px-4 py-2.5">Kelas</th>
                                    <th scope="col" class="px-4 py-2.5 text-right">Total Terlambat</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                <tr v-if="topLate.length === 0">
                                    <td colspan="4" class="px-4 py-6 text-center text-sm text-slate-700">
                                        Tidak ada catatan keterlambatan pada rentang tanggal ini.
                                    </td>
                                </tr>
                                <tr v-for="item in topLate" :key="item.student_id">
                                    <td class="whitespace-nowrap px-4 py-2.5 font-mono font-semibold text-slate-900">
                                        {{ item.nis }}
                                    </td>
                                    <td class="px-4 py-2.5 font-semibold text-slate-900">
                                        {{ item.name }}
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-800">
                                        {{ item.classroom_name }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-right font-mono font-bold text-amber-950">
                                        {{ item.late_count }} hari
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section
                    aria-labelledby="top-absent-heading"
                    class="rounded border border-slate-300 bg-white"
                >
                    <div class="border-b border-slate-300 px-4 py-3 sm:px-6">
                        <h2 id="top-absent-heading" class="text-base font-bold text-slate-900">
                            Siswa dengan Alpa Tertinggi ({{ filterDateFrom }} s.d. {{ filterDateTo }})
                        </h2>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-300 text-left text-sm">
                            <thead class="bg-slate-100 text-xs font-bold uppercase text-slate-800">
                                <tr>
                                    <th scope="col" class="px-4 py-2.5">NIS</th>
                                    <th scope="col" class="px-4 py-2.5">Nama Siswa</th>
                                    <th scope="col" class="px-4 py-2.5">Kelas</th>
                                    <th scope="col" class="px-4 py-2.5 text-right">Total Alpa</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                <tr v-if="topAbsent.length === 0">
                                    <td colspan="4" class="px-4 py-6 text-center text-sm text-slate-700">
                                        Tidak ada catatan alpa pada rentang tanggal ini.
                                    </td>
                                </tr>
                                <tr v-for="item in topAbsent" :key="item.student_id">
                                    <td class="whitespace-nowrap px-4 py-2.5 font-mono font-semibold text-slate-900">
                                        {{ item.nis }}
                                    </td>
                                    <td class="px-4 py-2.5 font-semibold text-slate-900">
                                        {{ item.name }}
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-800">
                                        {{ item.classroom_name }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-right font-mono font-bold text-red-950">
                                        {{ item.absent_count }} hari
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <section
                aria-labelledby="student-history-heading"
                class="rounded border border-slate-300 bg-white p-4 sm:p-6"
            >
                <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div>
                        <h2 id="student-history-heading" class="text-base font-bold text-slate-900">
                            Riwayat Kehadiran Individu Siswa
                        </h2>
                        <p class="text-xs text-slate-700">
                            Pilih siswa untuk meninjau rincian kehadiran per tanggal pada periode {{ filterDateFrom }} s.d. {{ filterDateTo }}.
                        </p>
                    </div>

                    <div class="w-full sm:w-80">
                        <label
                            for="report-filter-student"
                            class="block text-sm font-semibold text-slate-900"
                        >
                            Pilih Siswa
                        </label>
                        <select
                            id="report-filter-student"
                            v-model="filterStudentId"
                            @change="applyFilters"
                            class="mt-1 min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700"
                        >
                            <option
                                v-for="st in classStudents"
                                :key="st.id"
                                :value="st.id"
                            >
                                {{ st.name }} ({{ st.nis }})
                            </option>
                        </select>
                    </div>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-300 text-left text-sm">
                        <thead class="bg-slate-100 text-xs font-bold uppercase text-slate-800">
                            <tr>
                                <th scope="col" class="px-4 py-2.5">Tanggal</th>
                                <th scope="col" class="px-4 py-2.5">Status</th>
                                <th scope="col" class="px-4 py-2.5">Jam Masuk</th>
                                <th scope="col" class="px-4 py-2.5">Jam Pulang</th>
                                <th scope="col" class="px-4 py-2.5">Sumber</th>
                                <th scope="col" class="px-4 py-2.5">Catatan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            <tr v-if="studentHistory.length === 0">
                                <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-700">
                                    Belum ada catatan kehadiran untuk siswa ini pada rentang tanggal yang dipilih.
                                </td>
                            </tr>
                            <tr v-for="rec in studentHistory" :key="rec.id">
                                <td class="whitespace-nowrap px-4 py-2.5 font-mono font-semibold text-slate-900">
                                    {{ rec.date }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-2.5 font-bold text-slate-900">
                                    {{ rec.status_label }} ({{ rec.code }})
                                </td>
                                <td class="whitespace-nowrap px-4 py-2.5 font-mono text-slate-800">
                                    {{ rec.check_in_time ?? '-' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-2.5 font-mono text-slate-800">
                                    {{ rec.check_out_time ?? '-' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-slate-800">
                                    {{ rec.source_label }}
                                </td>
                                <td class="px-4 py-2.5 text-slate-700">
                                    {{ rec.note ?? '-' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>

