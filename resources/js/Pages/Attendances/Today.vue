<script setup lang="ts">
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import type {
    ClassroomItem,
    ClassTodayStudentItem,
    ClassTodaySummary,
    SelectOptionItem,
} from '@/types/models';

const props = defineProps<{
    classrooms: ClassroomItem[];
    selectedClassroomId: number | null;
    students: ClassTodayStudentItem[];
    summary: ClassTodaySummary;
    filters: {
        classroom_id: number | null;
        date: string;
        search: string;
        status: string;
    };
    statusOptions: SelectOptionItem[];
    excuseStatusOptions: SelectOptionItem[];
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);

const filterClassroomId = ref<number | ''>(props.filters.classroom_id ?? '');
const filterDate = ref<string>(props.filters.date);
const filterSearch = ref<string>(props.filters.search);
const filterStatus = ref<string>(props.filters.status);

function applyFilters(): void {
    router.get(
        route('attendances.today'),
        {
            classroom_id: filterClassroomId.value || undefined,
            date: filterDate.value || undefined,
            search: filterSearch.value || undefined,
            status: filterStatus.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}

function resetFilters(): void {
    filterSearch.value = '';
    filterStatus.value = '';
    applyFilters();
}

const excuseModalStudent = ref<ClassTodayStudentItem | null>(null);
const excuseForm = useForm<{
    student_id: number | '';
    status: string;
    start_date: string;
    end_date: string;
    note: string;
    attachment: File | null;
}>({
    student_id: '',
    status: 'sick',
    start_date: props.filters.date,
    end_date: props.filters.date,
    note: '',
    attachment: null,
});

function openExcuseModal(student: ClassTodayStudentItem): void {
    excuseModalStudent.value = student;
    excuseForm.clearErrors();
    excuseForm.student_id = student.id;
    excuseForm.status =
        student.attendance &&
        ['permission', 'sick', 'dispensation'].includes(student.attendance.status)
            ? student.attendance.status
            : 'sick';
    excuseForm.start_date = filterDate.value;
    excuseForm.end_date = filterDate.value;
    excuseForm.note = student.attendance?.note ?? '';
    excuseForm.attachment = null;
}

function closeExcuseModal(): void {
    excuseModalStudent.value = null;
    excuseForm.reset();
    excuseForm.clearErrors();
}

function handleExcuseFileChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    excuseForm.attachment = target.files && target.files.length > 0 ? target.files[0] : null;
}

function submitExcuse(): void {
    excuseForm.post(route('attendances.excuse'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            closeExcuseModal();
        },
    });
}

const correctionModalStudent = ref<ClassTodayStudentItem | null>(null);
const correctionForm = useForm({
    student_id: '' as number | '',
    date: props.filters.date,
    status: 'present',
    check_in_time: '',
    check_out_time: '',
    note: '',
    reason: '',
});

function openCorrectionModal(student: ClassTodayStudentItem): void {
    correctionModalStudent.value = student;
    correctionForm.clearErrors();
    correctionForm.student_id = student.id;
    correctionForm.date = filterDate.value;
    correctionForm.status = student.attendance?.status ?? 'present';
    correctionForm.check_in_time = student.attendance?.check_in_time ?? '';
    correctionForm.check_out_time = student.attendance?.check_out_time ?? '';
    correctionForm.note = student.attendance?.note ?? '';
    correctionForm.reason = '';
}

function closeCorrectionModal(): void {
    correctionModalStudent.value = null;
    correctionForm.reset();
    correctionForm.clearErrors();
}

function submitCorrection(): void {
    correctionForm.post(route('attendances.correct'), {
        preserveScroll: true,
        onSuccess: () => {
            closeCorrectionModal();
        },
    });
}

function statusBadgeClass(status: string | undefined): string {
    switch (status) {
        case 'present':
            return 'border-emerald-800 bg-emerald-50 text-emerald-950';
        case 'late':
            return 'border-amber-800 bg-amber-50 text-amber-950';
        case 'permission':
        case 'sick':
        case 'dispensation':
            return 'border-sky-800 bg-sky-50 text-sky-950';
        case 'absent':
            return 'border-red-800 bg-red-50 text-red-950';
        default:
            return 'border-slate-400 bg-slate-100 text-slate-800';
    }
}
</script>

<template>
    <Head title="Kehadiran Harian per Kelas" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-xl font-bold text-slate-900">
                        Kehadiran Harian per Kelas
                    </h1>
                    <p class="text-sm text-slate-700">
                        Pantau status kehadiran siswa, catat izin/sakit/dispensasi, atau lakukan koreksi data dengan alasan tercatat.
                    </p>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <section
                aria-label="Filter Kehadiran Kelas"
                class="rounded border border-slate-300 bg-white p-4 sm:p-5"
            >
                <form
                    @submit.prevent="applyFilters"
                    class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5"
                >
                    <div>
                        <label
                            for="filter-classroom"
                            class="block text-sm font-semibold text-slate-900"
                        >
                            Kelas
                        </label>
                        <select
                            id="filter-classroom"
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
                            for="filter-date"
                            class="block text-sm font-semibold text-slate-900"
                        >
                            Tanggal
                        </label>
                        <input
                            id="filter-date"
                            v-model="filterDate"
                            type="date"
                            @change="applyFilters"
                            class="mt-1 min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700"
                        />
                    </div>

                    <div>
                        <label
                            for="filter-status"
                            class="block text-sm font-semibold text-slate-900"
                        >
                            Status Kehadiran
                        </label>
                        <select
                            id="filter-status"
                            v-model="filterStatus"
                            @change="applyFilters"
                            class="mt-1 min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700"
                        >
                            <option value="">Semua Status</option>
                            <option
                                v-for="opt in statusOptions"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                            <option value="unrecorded">Belum Tercatat</option>
                        </select>
                    </div>

                    <div>
                        <label
                            for="filter-search"
                            class="block text-sm font-semibold text-slate-900"
                        >
                            Cari Nama / NIS
                        </label>
                        <input
                            id="filter-search"
                            v-model="filterSearch"
                            type="search"
                            placeholder="Ketik nama atau NIS..."
                            class="mt-1 min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700"
                        />
                    </div>

                    <div class="flex items-end gap-2">
                        <button
                            type="submit"
                            class="inline-flex min-h-[44px] items-center rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                        >
                            Terapkan
                        </button>
                        <button
                            type="button"
                            @click="resetFilters"
                            class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                        >
                            Reset
                        </button>
                    </div>
                </form>
            </section>

            <section
                aria-label="Ringkasan Kehadiran Kelas"
                class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6"
            >
                <div class="rounded border border-slate-300 bg-white p-3.5">
                    <p class="text-xs font-semibold uppercase text-slate-700">Total Siswa</p>
                    <p class="mt-1 text-xl font-bold text-slate-900">{{ summary.total_students }}</p>
                </div>
                <div class="rounded border border-emerald-800 bg-emerald-50 p-3.5">
                    <p class="text-xs font-semibold uppercase text-emerald-950">Hadir Tepat</p>
                    <p class="mt-1 text-xl font-bold text-emerald-950">{{ summary.present }}</p>
                </div>
                <div class="rounded border border-amber-800 bg-amber-50 p-3.5">
                    <p class="text-xs font-semibold uppercase text-amber-950">Terlambat</p>
                    <p class="mt-1 text-xl font-bold text-amber-950">{{ summary.late }}</p>
                </div>
                <div class="rounded border border-sky-800 bg-sky-50 p-3.5">
                    <p class="text-xs font-semibold uppercase text-sky-950">Izin / Sakit / Disp.</p>
                    <p class="mt-1 text-xl font-bold text-sky-950">{{ summary.excused }}</p>
                </div>
                <div class="rounded border border-red-800 bg-red-50 p-3.5">
                    <p class="text-xs font-semibold uppercase text-red-950">Alfa</p>
                    <p class="mt-1 text-xl font-bold text-red-950">{{ summary.absent }}</p>
                </div>
                <div class="rounded border border-slate-400 bg-slate-100 p-3.5">
                    <p class="text-xs font-semibold uppercase text-slate-800">Belum Tercatat</p>
                    <p class="mt-1 text-xl font-bold text-slate-900">{{ summary.unrecorded }}</p>
                </div>
            </section>

            <section class="rounded border border-slate-300 bg-white">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-300 text-left text-sm">
                        <thead class="bg-slate-100 text-xs font-bold uppercase text-slate-800">
                            <tr>
                                <th scope="col" class="px-4 py-3">NIS</th>
                                <th scope="col" class="px-4 py-3">Nama Siswa</th>
                                <th scope="col" class="px-4 py-3">Status</th>
                                <th scope="col" class="px-4 py-3">Jam Masuk</th>
                                <th scope="col" class="px-4 py-3">Jam Pulang</th>
                                <th scope="col" class="px-4 py-3">Sumber &amp; Catatan</th>
                                <th scope="col" class="px-4 py-3 text-right">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            <tr v-if="students.length === 0">
                                <td colspan="7" class="px-4 py-8 text-center text-sm font-medium text-slate-700">
                                    Tidak ada siswa yang sesuai dengan filter kelas atau pencarian.
                                </td>
                            </tr>
                            <tr
                                v-for="student in students"
                                :key="student.id"
                                class="hover:bg-slate-50"
                            >
                                <td class="whitespace-nowrap px-4 py-3 font-mono text-sm font-semibold text-slate-900">
                                    {{ student.nis }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-bold text-slate-900">{{ student.name }}</div>
                                    <div class="text-xs text-slate-700">
                                        {{ student.classroom?.name ?? '-' }} ({{ student.gender_label ?? student.gender }})
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span
                                        class="inline-flex items-center rounded border px-2.5 py-1 text-xs font-bold"
                                        :class="statusBadgeClass(student.attendance?.status)"
                                    >
                                        {{ student.attendance?.status_label ?? 'Belum Tercatat' }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 font-mono text-sm font-semibold text-slate-900">
                                    {{ student.attendance?.check_in_time ?? '-' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 font-mono text-sm font-semibold text-slate-900">
                                    {{ student.attendance?.check_out_time ?? '-' }}
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-800">
                                    <div v-if="student.attendance" class="space-y-1">
                                        <div class="font-semibold text-slate-900">
                                            {{ student.attendance.source_label }}
                                        </div>
                                        <div v-if="student.attendance.note" class="text-slate-700">
                                            Catatan: {{ student.attendance.note }}
                                        </div>
                                        <div v-if="student.attendance.attachment_url">
                                            <a
                                                :href="student.attendance.attachment_url"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="font-semibold text-emerald-800 underline hover:text-emerald-950"
                                            >
                                                Lihat Lampiran Surat
                                            </a>
                                        </div>
                                    </div>
                                    <span v-else class="text-slate-700">-</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <div class="inline-flex flex-wrap justify-end gap-2">
                                        <button
                                            v-if="user.can.excuse_attendance"
                                            type="button"
                                            @click="openExcuseModal(student)"
                                            class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-3 py-1.5 text-xs font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                                        >
                                            Izin / Sakit
                                        </button>
                                        <button
                                            v-if="user.can.correct_attendance"
                                            type="button"
                                            @click="openCorrectionModal(student)"
                                            class="inline-flex min-h-[44px] items-center rounded bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                                        >
                                            Koreksi
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div
            v-if="excuseModalStudent"
            role="dialog"
            aria-modal="true"
            aria-labelledby="excuse-modal-title"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4"
        >
            <div class="w-full max-w-lg rounded border border-slate-400 bg-white p-6">
                <h2 id="excuse-modal-title" class="text-lg font-bold text-slate-900">
                    Catat Izin / Sakit / Dispensasi
                </h2>
                <p class="mt-1 text-sm text-slate-700">
                    Siswa: <span class="font-semibold text-slate-900">{{ excuseModalStudent.name }}</span> ({{ excuseModalStudent.nis }})
                </p>

                <form @submit.prevent="submitExcuse" class="mt-4 space-y-4">
                    <div>
                        <label for="excuse-status" class="block text-sm font-semibold text-slate-900">
                            Jenis Keterangan
                        </label>
                        <select
                            id="excuse-status"
                            v-model="excuseForm.status"
                            required
                            class="mt-1 min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700"
                        >
                            <option
                                v-for="opt in excuseStatusOptions"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>
                        <p v-if="excuseForm.errors.status" class="mt-1 text-xs font-semibold text-red-700">
                            {{ excuseForm.errors.status }}
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="excuse-start-date" class="block text-sm font-semibold text-slate-900">
                                Tanggal Mulai
                            </label>
                            <input
                                id="excuse-start-date"
                                v-model="excuseForm.start_date"
                                type="date"
                                required
                                class="mt-1 min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700"
                            />
                            <p v-if="excuseForm.errors.start_date" class="mt-1 text-xs font-semibold text-red-700">
                                {{ excuseForm.errors.start_date }}
                            </p>
                        </div>

                        <div>
                            <label for="excuse-end-date" class="block text-sm font-semibold text-slate-900">
                                Tanggal Selesai
                            </label>
                            <input
                                id="excuse-end-date"
                                v-model="excuseForm.end_date"
                                type="date"
                                required
                                class="mt-1 min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700"
                            />
                            <p v-if="excuseForm.errors.end_date" class="mt-1 text-xs font-semibold text-red-700">
                                {{ excuseForm.errors.end_date }}
                            </p>
                        </div>
                    </div>

                    <div>
                        <label for="excuse-note" class="block text-sm font-semibold text-slate-900">
                            Keterangan / Catatan (Opsional)
                        </label>
                        <textarea
                            id="excuse-note"
                            v-model="excuseForm.note"
                            rows="3"
                            placeholder="Contoh: Surat keterangan dokter terlampir"
                            class="mt-1 w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700"
                        />
                        <p v-if="excuseForm.errors.note" class="mt-1 text-xs font-semibold text-red-700">
                            {{ excuseForm.errors.note }}
                        </p>
                    </div>

                    <div>
                        <label for="excuse-attachment" class="block text-sm font-semibold text-slate-900">
                            Lampiran Surat (JPG, PNG, PDF maks. 2MB - Opsional)
                        </label>
                        <input
                            id="excuse-attachment"
                            type="file"
                            accept=".jpg,.jpeg,.png,.pdf"
                            @change="handleExcuseFileChange"
                            class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900"
                        />
                        <p v-if="excuseForm.errors.attachment" class="mt-1 text-xs font-semibold text-red-700">
                            {{ excuseForm.errors.attachment }}
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-slate-300 pt-4">
                        <button
                            type="button"
                            @click="closeExcuseModal"
                            class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-100"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="excuseForm.processing"
                            class="inline-flex min-h-[44px] items-center rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 disabled:opacity-60"
                        >
                            {{ excuseForm.processing ? 'Menyimpan...' : 'Simpan Keterangan' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div
            v-if="correctionModalStudent"
            role="dialog"
            aria-modal="true"
            aria-labelledby="correction-modal-title"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4"
        >
            <div class="w-full max-w-lg rounded border border-slate-400 bg-white p-6">
                <h2 id="correction-modal-title" class="text-lg font-bold text-slate-900">
                    Koreksi Kehadiran Siswa
                </h2>
                <p class="mt-1 text-sm text-slate-700">
                    Siswa: <span class="font-semibold text-slate-900">{{ correctionModalStudent.name }}</span> ({{ correctionModalStudent.nis }}) | Tanggal: <span class="font-semibold text-slate-900">{{ correctionForm.date }}</span>
                </p>

                <form @submit.prevent="submitCorrection" class="mt-4 space-y-4">
                    <div>
                        <label for="correction-status" class="block text-sm font-semibold text-slate-900">
                            Status Kehadiran
                        </label>
                        <select
                            id="correction-status"
                            v-model="correctionForm.status"
                            required
                            class="mt-1 min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700"
                        >
                            <option
                                v-for="opt in statusOptions"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>
                        <p v-if="correctionForm.errors.status" class="mt-1 text-xs font-semibold text-red-700">
                            {{ correctionForm.errors.status }}
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="correction-in-time" class="block text-sm font-semibold text-slate-900">
                                Jam Masuk (Opsional)
                            </label>
                            <input
                                id="correction-in-time"
                                v-model="correctionForm.check_in_time"
                                type="time"
                                class="mt-1 min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700"
                            />
                            <p v-if="correctionForm.errors.check_in_time" class="mt-1 text-xs font-semibold text-red-700">
                                {{ correctionForm.errors.check_in_time }}
                            </p>
                        </div>

                        <div>
                            <label for="correction-out-time" class="block text-sm font-semibold text-slate-900">
                                Jam Pulang (Opsional)
                            </label>
                            <input
                                id="correction-out-time"
                                v-model="correctionForm.check_out_time"
                                type="time"
                                class="mt-1 min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700"
                            />
                            <p v-if="correctionForm.errors.check_out_time" class="mt-1 text-xs font-semibold text-red-700">
                                {{ correctionForm.errors.check_out_time }}
                            </p>
                        </div>
                    </div>

                    <div>
                        <label for="correction-note" class="block text-sm font-semibold text-slate-900">
                            Catatan Kehadiran (Opsional)
                        </label>
                        <input
                            id="correction-note"
                            v-model="correctionForm.note"
                            type="text"
                             class="mt-1 min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700"
                        />
                        <p v-if="correctionForm.errors.note" class="mt-1 text-xs font-semibold text-red-700">
                            {{ correctionForm.errors.note }}
                        </p>
                    </div>

                    <div>
                        <label for="correction-reason" class="block text-sm font-semibold text-slate-900">
                            Alasan Koreksi (Wajib Diisi, min. 5 karakter)
                        </label>
                        <textarea
                            id="correction-reason"
                            v-model="correctionForm.reason"
                            rows="3"
                            required
                            placeholder="Jelaskan alasan perubahan data untuk pencatatan di Log Audit..."
                            class="mt-1 w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700"
                        />
                        <p class="mt-1 text-xs text-slate-700">
                            Setiap koreksi akan merekam data sebelum dan sesudah perubahan beserta nama Anda di Log Audit.
                        </p>
                        <p v-if="correctionForm.errors.reason" class="mt-1 text-xs font-semibold text-red-700">
                            {{ correctionForm.errors.reason }}
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-slate-300 pt-4">
                        <button
                            type="button"
                            @click="closeCorrectionModal"
                            class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-100"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="correctionForm.processing"
                            class="inline-flex min-h-[44px] items-center rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 disabled:opacity-60"
                        >
                            {{ correctionForm.processing ? 'Menyimpan...' : 'Simpan Koreksi' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

