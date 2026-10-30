<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PaginationLinks from '@/Components/PaginationLinks.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useNfcScanner } from '@/composables/useNfcScanner';
import type { ClassroomItem, NfcCardItem, PaginatedData, StudentItem } from '@/types/models';

const props = defineProps<{
    cards: PaginatedData<NfcCardItem>;
    students: StudentItem[];
    classrooms: ClassroomItem[];
    filters: {
        search: string;
        status: string;
        classroom_id: number | null;
    };
}>();

const search = ref(props.filters.search ?? '');
const statusFilter = ref(props.filters.status ?? '');
const classroomFilter = ref<number | string>(props.filters.classroom_id ?? '');

const showRegisterModal = ref(false);
const showReplaceModal = ref(false);
const showStatusModal = ref(false);
const selectedCardForStatus = ref<NfcCardItem | null>(null);
const scannerFeedback = ref<string | null>(null);

const registerForm = useForm({
    student_id: '' as number | string,
    uid: '',
    notes: '',
});

const replaceForm = useForm({
    student_id: '' as number | string,
    new_uid: '',
    previous_status: 'lost' as 'inactive' | 'lost',
    notes: '',
});

const statusForm = useForm({
    status: 'active' as 'active' | 'inactive' | 'lost',
    notes: '',
});

const unassignedStudents = computed(() =>
    props.students.filter((student) => !student.active_card),
);

const assignedStudents = computed(() =>
    props.students.filter((student) => Boolean(student.active_card)),
);

useNfcScanner({
    maxKeyGapMs: 50,
    onScan: ({ uid }) => {
        scannerFeedback.value = `UID terbaca dari reader: ${uid}`;
        if (showReplaceModal.value) {
            replaceForm.new_uid = uid;
            return;
        }

        if (!showRegisterModal.value) {
            showRegisterModal.value = true;
        }
        registerForm.uid = uid;
    },
    onInvalidScan: ({ raw }) => {
        scannerFeedback.value = `Reader mengirim format UID tidak valid: ${raw}`;
    },
});

function applyFilters(): void {
    router.get(
        route('cards.index'),
        {
            search: search.value || undefined,
            status: statusFilter.value || undefined,
            classroom_id: classroomFilter.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

function resetFilters(): void {
    search.value = '';
    statusFilter.value = '';
    classroomFilter.value = '';
    router.get(route('cards.index'));
}

function openRegisterModal(studentId?: number): void {
    registerForm.reset();
    registerForm.clearErrors();
    if (studentId) {
        registerForm.student_id = studentId;
    }
    scannerFeedback.value = null;
    showRegisterModal.value = true;
}

function openReplaceModal(studentId?: number): void {
    replaceForm.reset();
    replaceForm.clearErrors();
    replaceForm.previous_status = 'lost';
    if (studentId) {
        replaceForm.student_id = studentId;
    }
    scannerFeedback.value = null;
    showReplaceModal.value = true;
}

function openStatusModal(card: NfcCardItem): void {
    selectedCardForStatus.value = card;
    statusForm.clearErrors();
    statusForm.status = card.status;
    statusForm.notes = card.notes ?? '';
    showStatusModal.value = true;
}

function closeModals(): void {
    showRegisterModal.value = false;
    showReplaceModal.value = false;
    showStatusModal.value = false;
    selectedCardForStatus.value = null;
}

function submitRegister(): void {
    registerForm.post(route('cards.store'), {
        preserveScroll: true,
        onSuccess: () => closeModals(),
    });
}

function submitReplace(): void {
    replaceForm.post(route('cards.replace'), {
        preserveScroll: true,
        onSuccess: () => closeModals(),
    });
}

function submitStatusUpdate(): void {
    if (!selectedCardForStatus.value) return;
    statusForm.patch(route('cards.update-status', selectedCardForStatus.value.id), {
        preserveScroll: true,
        onSuccess: () => closeModals(),
    });
}

function handleEscape(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        closeModals();
    }
}

onMounted(() => window.addEventListener('keydown', handleEscape));
onUnmounted(() => window.removeEventListener('keydown', handleEscape));
</script>

<template>
    <Head title="Manajemen Kartu NFC" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-xl font-bold text-slate-900">
                        Registrasi &amp; Manajemen Kartu NFC
                    </h1>
                    <p class="text-sm text-slate-700">
                        Pasangkan kartu NFC ke siswa, ganti kartu hilang atau rusak dengan menjaga riwayat kartu, atau gunakan mode registrasi massal.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        @click="openRegisterModal()"
                        class="inline-flex min-h-[44px] items-center rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                    >
                        Daftarkan Kartu
                    </button>
                    <button
                        type="button"
                        @click="openReplaceModal()"
                        class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                    >
                        Ganti Kartu Hilang / Rusak
                    </button>
                    <Link
                        :href="route('cards.bulk')"
                        class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                    >
                        Registrasi Massal per Kelas
                    </Link>
                </div>
            </div>
        </template>

        <section class="space-y-6">
            <form
                @submit.prevent="applyFilters"
                class="grid grid-cols-1 gap-3 rounded border border-slate-300 bg-white p-4 sm:grid-cols-2 lg:grid-cols-4"
            >
                <div>
                    <label for="card-search" class="block text-xs font-bold uppercase text-slate-800">
                        Cari UID / Nama / NIS
                    </label>
                    <input
                        id="card-search"
                        v-model="search"
                        type="text"
                        placeholder="Contoh: 04A1B2C3 atau nama siswa"
                        class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:ring-emerald-700"
                    />
                </div>

                <div>
                    <label for="card-classroom" class="block text-xs font-bold uppercase text-slate-800">
                        Filter Kelas
                    </label>
                    <select
                        id="card-classroom"
                        v-model="classroomFilter"
                        class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:ring-emerald-700"
                    >
                        <option value="">Semua Kelas</option>
                        <option
                            v-for="classroom in props.classrooms"
                            :key="classroom.id"
                            :value="classroom.id"
                        >
                            Kelas {{ classroom.name }}
                        </option>
                    </select>
                </div>

                <div>
                    <label for="card-status" class="block text-xs font-bold uppercase text-slate-800">
                        Status Kartu
                    </label>
                    <select
                        id="card-status"
                        v-model="statusFilter"
                        class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:ring-emerald-700"
                    >
                        <option value="">Semua Status</option>
                        <option value="active">Aktif</option>
                        <option value="inactive">Nonaktif</option>
                        <option value="lost">Hilang</option>
                    </select>
                </div>

                <div class="flex items-end gap-2">
                    <button
                        type="submit"
                        class="inline-flex min-h-[44px] flex-1 items-center justify-center rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                    >
                        Cari
                    </button>
                    <button
                        type="button"
                        @click="resetFilters"
                        class="inline-flex min-h-[44px] items-center justify-center rounded border border-slate-500 bg-white px-3 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                    >
                        Atur Ulang
                    </button>
                </div>
            </form>

            <div
                v-if="props.cards.data.length === 0"
                class="rounded border border-slate-300 bg-white p-8 text-center"
            >
                <h2 class="text-base font-bold text-slate-900">
                    Belum Ada Kartu NFC yang Sesuai Filter
                </h2>
                <p class="mt-1 text-sm text-slate-700">
                    Tempelkan kartu baru pada reader USB atau klik tombol Daftarkan Kartu untuk memasangkan kartu ke siswa.
                </p>
                <div class="mt-4 flex flex-wrap justify-center gap-2">
                    <button
                        type="button"
                        @click="openRegisterModal()"
                        class="inline-flex min-h-[44px] items-center rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                    >
                        Daftarkan Kartu Pertama
                    </button>
                    <Link
                        :href="route('cards.bulk')"
                        class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                    >
                        Buka Registrasi Massal
                    </Link>
                </div>
            </div>

            <div v-else class="overflow-x-auto rounded border border-slate-300 bg-white">
                <table class="min-w-full divide-y divide-slate-300 text-left text-sm">
                    <thead class="bg-slate-100 text-xs font-bold uppercase text-slate-800">
                        <tr>
                            <th scope="col" class="px-4 py-3">UID Kartu</th>
                            <th scope="col" class="px-4 py-3">Pemilik Siswa</th>
                            <th scope="col" class="px-4 py-3">Kelas</th>
                            <th scope="col" class="px-4 py-3">Status</th>
                            <th scope="col" class="px-4 py-3">Riwayat Waktu &amp; Catatan</th>
                            <th scope="col" class="px-4 py-3 text-right">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white text-slate-900">
                        <tr v-for="card in props.cards.data" :key="card.id">
                            <td class="px-4 py-3 font-mono font-bold text-slate-900">
                                {{ card.uid }}
                            </td>
                            <td class="px-4 py-3">
                                <div v-if="card.student">
                                    <p class="font-semibold text-slate-900">{{ card.student.name }}</p>
                                    <p class="text-xs text-slate-700">NIS: {{ card.student.nis }}</p>
                                </div>
                                <span v-else class="text-slate-700">Tidak terhubung</span>
                            </td>
                            <td class="px-4 py-3 text-slate-800">
                                {{ card.student?.classroom_name || '-' }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded border px-2.5 py-1 text-xs font-bold"
                                    :class="{
                                        'border-emerald-800 bg-emerald-50 text-emerald-950': card.status === 'active',
                                        'border-slate-600 bg-slate-100 text-slate-900': card.status === 'inactive',
                                        'border-red-800 bg-red-50 text-red-950': card.status === 'lost',
                                    }"
                                >
                                    {{ card.status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-700">
                                <p>Terbit: {{ card.issued_at_formatted || '-' }}</p>
                                <p v-if="card.deactivated_at_formatted">
                                    Nonaktif: {{ card.deactivated_at_formatted }}
                                </p>
                                <p v-if="card.replaced_by_uid" class="font-semibold text-slate-900">
                                    Digantikan oleh: {{ card.replaced_by_uid }}
                                </p>
                                <p v-if="card.notes" class="mt-0.5 text-slate-800">
                                    Catatan: {{ card.notes }}
                                </p>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <button
                                        v-if="card.status === 'active' && card.student"
                                        type="button"
                                        @click="openReplaceModal(card.student.id)"
                                        class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-3 py-2 text-xs font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                                    >
                                        Ganti Kartu
                                    </button>
                                    <button
                                        type="button"
                                        @click="openStatusModal(card)"
                                        class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-3 py-2 text-xs font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                                    >
                                        Ubah Status
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <PaginationLinks
                :links="props.cards.links"
                :from="props.cards.from"
                :to="props.cards.to"
                :total="props.cards.total"
            />
        </section>

        <div
            v-if="showRegisterModal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="register-card-title"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4"
        >
            <div class="w-full max-w-lg rounded border border-slate-400 bg-white p-6">
                <h2 id="register-card-title" class="text-lg font-bold text-slate-900">
                    Daftarkan Kartu NFC Siswa
                </h2>
                <p class="mt-1 text-sm text-slate-700">
                    Pilih siswa yang belum memiliki kartu aktif, lalu tempelkan kartu NFC pada reader USB atau ketik UID kartu.
                </p>

                <div
                    v-if="scannerFeedback"
                    role="status"
                    class="mt-3 rounded border border-emerald-800 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-950"
                >
                    {{ scannerFeedback }}
                </div>

                <form @submit.prevent="submitRegister" class="mt-4 space-y-4">
                    <div>
                        <label for="register-student" class="block text-sm font-semibold text-slate-900">
                            Pilih Siswa
                        </label>
                        <select
                            id="register-student"
                            v-model="registerForm.student_id"
                            required
                            class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:ring-emerald-700"
                        >
                            <option value="">Pilih siswa penerima kartu</option>
                            <option
                                v-for="student in unassignedStudents"
                                :key="student.id"
                                :value="student.id"
                            >
                                {{ student.name }} (NIS: {{ student.nis }} / Kelas {{ student.classroom_name || '-' }})
                            </option>
                        </select>
                        <p v-if="registerForm.errors.student_id" class="mt-1 text-xs font-semibold text-red-800">
                            {{ registerForm.errors.student_id }}
                        </p>
                    </div>

                    <div>
                        <label for="register-uid" class="block text-sm font-semibold text-slate-900">
                            UID Kartu NFC (Tempel Kartu di Reader)
                        </label>
                        <input
                            id="register-uid"
                            v-model="registerForm.uid"
                            data-nfc-capture="true"
                            type="text"
                            required
                            placeholder="Contoh: 0077705923 atau 04A1B2C3"
                            class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 px-3 py-2 font-mono text-sm text-slate-900 focus:border-emerald-700 focus:ring-emerald-700"
                        />
                        <p v-if="registerForm.errors.uid" class="mt-1 text-xs font-semibold text-red-800">
                            {{ registerForm.errors.uid }}
                        </p>
                    </div>

                    <div>
                        <label for="register-notes" class="block text-sm font-semibold text-slate-900">
                            Catatan (Opsional)
                        </label>
                        <input
                            id="register-notes"
                            v-model="registerForm.notes"
                            type="text"
                            placeholder="Contoh: Kartu tahun ajaran baru"
                            class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:ring-emerald-700"
                        />
                    </div>

                    <div class="flex justify-end gap-2 pt-3">
                        <button
                            type="button"
                            @click="closeModals"
                            class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="registerForm.processing"
                            class="inline-flex min-h-[44px] items-center rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 disabled:opacity-60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                        >
                            {{ registerForm.processing ? 'Menyimpan...' : 'Simpan Kartu' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div
            v-if="showReplaceModal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="replace-card-title"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4"
        >
            <div class="w-full max-w-lg rounded border border-slate-400 bg-white p-6">
                <h2 id="replace-card-title" class="text-lg font-bold text-slate-900">
                    Ganti Kartu NFC Siswa (Hilang / Rusak)
                </h2>
                <p class="mt-1 text-sm text-slate-700">
                    Kartu lama siswa akan dinonaktifkan dan riwayatnya tetap disimpan, kemudian digantikan oleh kartu baru.
                </p>

                <div
                    v-if="scannerFeedback"
                    role="status"
                    class="mt-3 rounded border border-emerald-800 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-950"
                >
                    {{ scannerFeedback }}
                </div>

                <form @submit.prevent="submitReplace" class="mt-4 space-y-4">
                    <div>
                        <label for="replace-student" class="block text-sm font-semibold text-slate-900">
                            Pilih Siswa
                        </label>
                        <select
                            id="replace-student"
                            v-model="replaceForm.student_id"
                            required
                            class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:ring-emerald-700"
                        >
                            <option value="">Pilih siswa yang mengganti kartu</option>
                            <option
                                v-for="student in assignedStudents"
                                :key="student.id"
                                :value="student.id"
                            >
                                {{ student.name }} (Kartu saat ini: {{ student.active_card?.uid }})
                            </option>
                        </select>
                        <p v-if="replaceForm.errors.student_id" class="mt-1 text-xs font-semibold text-red-800">
                            {{ replaceForm.errors.student_id }}
                        </p>
                    </div>

                    <div>
                        <label for="replace-previous-status" class="block text-sm font-semibold text-slate-900">
                            Status Kartu Lama
                        </label>
                        <select
                            id="replace-previous-status"
                            v-model="replaceForm.previous_status"
                            class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:ring-emerald-700"
                        >
                            <option value="lost">Hilang (Beri peringatan jika kartu lama di-tap)</option>
                            <option value="inactive">Nonaktif / Rusak Fisik</option>
                        </select>
                    </div>

                    <div>
                        <label for="replace-new-uid" class="block text-sm font-semibold text-slate-900">
                            UID Kartu NFC Baru (Tempel Kartu Baru)
                        </label>
                        <input
                            id="replace-new-uid"
                            v-model="replaceForm.new_uid"
                            data-nfc-capture="true"
                            type="text"
                            required
                            placeholder="Contoh: 0004512345 atau 0044DA59"
                            class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 px-3 py-2 font-mono text-sm text-slate-900 focus:border-emerald-700 focus:ring-emerald-700"
                        />
                        <p v-if="replaceForm.errors.new_uid" class="mt-1 text-xs font-semibold text-red-800">
                            {{ replaceForm.errors.new_uid }}
                        </p>
                    </div>

                    <div>
                        <label for="replace-notes" class="block text-sm font-semibold text-slate-900">
                            Alasan Penggantian
                        </label>
                        <input
                            id="replace-notes"
                            v-model="replaceForm.notes"
                            type="text"
                            placeholder="Contoh: Kartu hilang dilaporkan tanggal 30/10/2026"
                            class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:ring-emerald-700"
                        />
                    </div>

                    <div class="flex justify-end gap-2 pt-3">
                        <button
                            type="button"
                            @click="closeModals"
                            class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="replaceForm.processing"
                            class="inline-flex min-h-[44px] items-center rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 disabled:opacity-60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                        >
                            {{ replaceForm.processing ? 'Memproses...' : 'Simpan Kartu Pengganti' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div
            v-if="showStatusModal && selectedCardForStatus"
            role="dialog"
            aria-modal="true"
            aria-labelledby="status-card-title"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4"
        >
            <div class="w-full max-w-md rounded border border-slate-400 bg-white p-6">
                <h2 id="status-card-title" class="text-lg font-bold text-slate-900">
                    Ubah Status Kartu {{ selectedCardForStatus.uid }}
                </h2>

                <form @submit.prevent="submitStatusUpdate" class="mt-4 space-y-4">
                    <div>
                        <label for="update-card-status" class="block text-sm font-semibold text-slate-900">
                            Status Kartu
                        </label>
                        <select
                            id="update-card-status"
                            v-model="statusForm.status"
                            class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:ring-emerald-700"
                        >
                            <option value="active">Aktif</option>
                            <option value="inactive">Nonaktif</option>
                            <option value="lost">Hilang</option>
                        </select>
                        <p v-if="statusForm.errors.status" class="mt-1 text-xs font-semibold text-red-800">
                            {{ statusForm.errors.status }}
                        </p>
                    </div>

                    <div>
                        <label for="update-card-notes" class="block text-sm font-semibold text-slate-900">
                            Catatan
                        </label>
                        <input
                            id="update-card-notes"
                            v-model="statusForm.notes"
                            type="text"
                            class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:ring-emerald-700"
                        />
                    </div>

                    <div class="flex justify-end gap-2 pt-3">
                        <button
                            type="button"
                            @click="closeModals"
                            class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="statusForm.processing"
                            class="inline-flex min-h-[44px] items-center rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 disabled:opacity-60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                        >
                            Simpan Status
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
