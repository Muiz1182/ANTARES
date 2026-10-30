<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useNfcScanner } from '@/composables/useNfcScanner';
import type { ClassroomItem, StudentItem } from '@/types/models';

const props = defineProps<{
    classrooms: ClassroomItem[];
    selectedClassroomId: number | null;
    students: StudentItem[];
}>();

const page = usePage();
const flash = computed(() => page.props.flash);

const classroomId = ref<number | string>(props.selectedClassroomId ?? '');
const skippedIds = ref<number[]>([]);
const currentSelectedId = ref<number | null>(null);
const lastAssignedCardId = ref<number | null>(
    typeof flash.value?.last_assigned_card_id === 'number'
        ? flash.value.last_assigned_card_id
        : null,
);
const manualUidInput = ref('');
const invalidBurstNotice = ref<string | null>(null);

const unassignedQueue = computed(() =>
    props.students.filter(
        (student) => !student.active_card && !skippedIds.value.includes(student.id),
    ),
);

const currentStudent = computed<StudentItem | null>(() => {
    if (currentSelectedId.value !== null) {
        const chosen = unassignedQueue.value.find((item) => item.id === currentSelectedId.value);
        if (chosen) {
            return chosen;
        }
    }

    return unassignedQueue.value[0] ?? null;
});

watch(
    () => flash.value?.last_assigned_card_id,
    (newVal) => {
        if (typeof newVal === 'number') {
            lastAssignedCardId.value = newVal;
        }
    },
);

const assignForm = useForm({
    student_id: 0,
    uid: '',
    notes: 'Registrasi massal per kelas',
});

function assignUidToCurrentStudent(uid: string): void {
    if (!currentStudent.value || assignForm.processing) {
        return;
    }

    invalidBurstNotice.value = null;
    assignForm.student_id = currentStudent.value.id;
    assignForm.uid = uid;

    assignForm.post(route('cards.store'), {
        preserveScroll: true,
        onSuccess: () => {
            manualUidInput.value = '';
            currentSelectedId.value = null;
        },
    });
}

useNfcScanner({
    maxKeyGapMs: 50,
    onScan: ({ uid }) => {
        assignUidToCurrentStudent(uid);
    },
    onInvalidScan: ({ raw }) => {
        invalidBurstNotice.value = `Format UID dari reader tidak valid: ${raw}`;
    },
});

function changeClassroom(): void {
    skippedIds.value = [];
    currentSelectedId.value = null;
    router.get(
        route('cards.bulk'),
        { classroom_id: classroomId.value || undefined },
        { preserveState: false },
    );
}

function skipCurrentStudent(): void {
    if (!currentStudent.value) return;
    skippedIds.value.push(currentStudent.value.id);
    currentSelectedId.value = null;
}

function resetSkippedStudents(): void {
    skippedIds.value = [];
    currentSelectedId.value = null;
}

function selectStudentFromList(student: StudentItem): void {
    if (student.active_card) return;
    skippedIds.value = skippedIds.value.filter((id) => id !== student.id);
    currentSelectedId.value = student.id;
}

function submitManualUid(): void {
    if (!manualUidInput.value.trim()) return;
    assignUidToCurrentStudent(manualUidInput.value.trim());
}

function undoLastAssignment(): void {
    if (!lastAssignedCardId.value) return;
    const cardId = lastAssignedCardId.value;
    router.delete(route('cards.undo', cardId), {
        preserveScroll: true,
        onSuccess: () => {
            lastAssignedCardId.value = null;
        },
    });
}
</script>

<template>
    <Head title="Registrasi Massal Kartu NFC" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-xl font-bold text-slate-900">
                        Registrasi Massal Kartu NFC per Kelas (Mode Scan Beruntun)
                    </h1>
                    <p class="text-sm text-slate-700">
                        Pilih kelas, lalu tempelkan kartu NFC satu per satu sesuai urutan siswa yang disorot di layar.
                    </p>
                </div>

                <Link
                    :href="route('cards.index')"
                    class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                >
                    Kembali ke Daftar Kartu
                </Link>
            </div>
        </template>

        <section class="space-y-6">
            <div class="flex flex-col justify-between gap-4 rounded border border-slate-300 bg-white p-4 sm:flex-row sm:items-end">
                <div class="w-full sm:max-w-xs">
                    <label for="bulk-classroom" class="block text-sm font-semibold text-slate-900">
                        Pilih Rombongan Belajar / Kelas
                    </label>
                    <select
                        id="bulk-classroom"
                        v-model="classroomId"
                        @change="changeClassroom"
                        class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:ring-emerald-700"
                    >
                        <option
                            v-for="classroom in props.classrooms"
                            :key="classroom.id"
                            :value="classroom.id"
                        >
                            Kelas {{ classroom.name }} (Tingkat {{ classroom.level }})
                        </option>
                    </select>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button
                        v-if="lastAssignedCardId"
                        type="button"
                        @click="undoLastAssignment"
                        class="inline-flex min-h-[44px] items-center rounded border border-red-800 bg-red-50 px-4 py-2 text-sm font-semibold text-red-950 hover:bg-red-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                    >
                        Batalkan Kartu Terakhir (Undo)
                    </button>
                    <button
                        v-if="skippedIds.length > 0"
                        type="button"
                        @click="resetSkippedStudents"
                        class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                    >
                        Tampilkan Kembali {{ skippedIds.length }} Siswa yang Dilewati
                    </button>
                </div>
            </div>

            <div
                v-if="invalidBurstNotice || assignForm.errors.uid || assignForm.errors.student_id"
                role="alert"
                class="rounded border-2 border-red-800 bg-red-50 p-4 text-sm font-semibold text-red-950"
            >
                <p v-if="invalidBurstNotice">{{ invalidBurstNotice }}</p>
                <p v-if="assignForm.errors.uid">{{ assignForm.errors.uid }}</p>
                <p v-if="assignForm.errors.student_id">{{ assignForm.errors.student_id }}</p>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
                <div class="lg:col-span-5">
                    <div
                        v-if="currentStudent"
                        class="rounded border-2 border-emerald-800 bg-white p-6"
                    >
                        <p class="text-xs font-bold uppercase text-emerald-900">
                            Siswa Urutan Aktif (Siap Tempel Kartu)
                        </p>
                        <h2 class="mt-2 text-2xl font-bold text-slate-900">
                            {{ currentStudent.name }}
                        </h2>
                        <p class="mt-1 text-sm font-semibold text-slate-700">
                            NIS: {{ currentStudent.nis }} | Kelas {{ currentStudent.classroom_name }}
                        </p>

                        <div class="mt-5 rounded border border-emerald-800 bg-emerald-50 p-4 text-sm font-semibold text-emerald-950">
                            Tempelkan kartu NFC baru pada reader USB sekarang. Sistem akan langsung menyimpan UID kartu dan berpindah ke siswa berikutnya.
                        </div>

                        <form @submit.prevent="submitManualUid" class="mt-4 space-y-3">
                            <div>
                                <label for="bulk-manual-uid" class="block text-xs font-bold uppercase text-slate-800">
                                    Atau Masukkan UID Kartu Secara Manual
                                </label>
                                <input
                                    id="bulk-manual-uid"
                                    v-model="manualUidInput"
                                    data-nfc-capture="true"
                                    type="text"
                                    placeholder="Contoh: 0077705923 atau 04A1B2C3"
                                    class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 px-3 py-2 font-mono text-sm text-slate-900 focus:border-emerald-700 focus:ring-emerald-700"
                                />
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="submit"
                                    :disabled="assignForm.processing || !manualUidInput.trim()"
                                    class="inline-flex min-h-[44px] flex-1 items-center justify-center rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 disabled:opacity-60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                                >
                                    {{ assignForm.processing ? 'Menyimpan...' : 'Pasangkan Kartu' }}
                                </button>
                                <button
                                    type="button"
                                    @click="skipCurrentStudent"
                                    class="inline-flex min-h-[44px] items-center justify-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                                >
                                    Lewati Siswa Ini
                                </button>
                            </div>
                        </form>
                    </div>

                    <div
                        v-else
                        class="rounded border border-slate-300 bg-white p-6 text-center"
                    >
                        <h2 class="text-lg font-bold text-slate-900">
                            Seluruh Siswa Antrian di Kelas Ini Sudah Memiliki Kartu
                        </h2>
                        <p class="mt-1 text-sm text-slate-700">
                            Pilih kelas lain pada daftar di atas atau kembalikan siswa yang dilewati jika masih ada.
                        </p>
                    </div>
                </div>

                <div class="lg:col-span-7">
                    <div class="overflow-x-auto rounded border border-slate-300 bg-white">
                        <table class="min-w-full divide-y divide-slate-300 text-left text-sm">
                            <thead class="bg-slate-100 text-xs font-bold uppercase text-slate-800">
                                <tr>
                                    <th scope="col" class="px-4 py-3">NIS</th>
                                    <th scope="col" class="px-4 py-3">Nama Siswa</th>
                                    <th scope="col" class="px-4 py-3">Status Kartu NFC</th>
                                    <th scope="col" class="px-4 py-3 text-right">Urutan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white text-slate-900">
                                <tr v-if="props.students.length === 0">
                                    <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-700">
                                        Belum ada siswa aktif di kelas ini.
                                    </td>
                                </tr>
                                <tr
                                    v-for="student in props.students"
                                    :key="student.id"
                                    :class="{
                                        'bg-emerald-50 font-semibold': currentStudent?.id === student.id,
                                    }"
                                >
                                    <td class="px-4 py-3 font-mono text-xs text-slate-800">
                                        {{ student.nis }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ student.name }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            v-if="student.active_card"
                                            class="inline-flex rounded border border-emerald-800 bg-emerald-50 px-2.5 py-1 font-mono text-xs font-bold text-emerald-950"
                                        >
                                            Terdaftar: {{ student.active_card.uid }}
                                        </span>
                                        <span
                                            v-else-if="skippedIds.includes(student.id)"
                                            class="inline-flex rounded border border-amber-800 bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-950"
                                        >
                                            Dilewati
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex rounded border border-slate-500 bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-800"
                                        >
                                            Belum Ada Kartu
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <span
                                            v-if="currentStudent?.id === student.id"
                                            class="inline-flex rounded bg-emerald-700 px-3 py-1 text-xs font-bold text-white"
                                        >
                                            Sedang Disorot
                                        </span>
                                        <button
                                            v-else-if="!student.active_card"
                                            type="button"
                                            @click="selectStudentFromList(student)"
                                            class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-3 py-1.5 text-xs font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                                        >
                                            Pilih Siswa Ini
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </AuthenticatedLayout>
</template>

