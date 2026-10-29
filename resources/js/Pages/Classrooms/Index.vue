<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PaginationLinks from '@/Components/PaginationLinks.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import type { AcademicYearItem, ClassroomItem, PaginatedData, UserItem } from '@/types/models';

const props = defineProps<{
    classrooms: PaginatedData<ClassroomItem>;
    academicYears: AcademicYearItem[];
    teachers: UserItem[];
    filters: {
        search: string;
        level: string;
        academic_year_id: string;
    };
}>();

const page = usePage();
const canManage = computed(() => page.props.auth.user.can.manage_master);

const search = ref(props.filters.search ?? '');
const level = ref(props.filters.level ?? '');
const academicYearId = ref(props.filters.academic_year_id ?? '');
const isFiltering = ref(false);
const showModal = ref(false);
const editingItem = ref<ClassroomItem | null>(null);

const defaultYearId = computed(() => {
    const active = props.academicYears.find((y) => y.is_active);
    return active ? active.id : (props.academicYears[0]?.id ?? 0);
});

const form = useForm({
    academic_year_id: defaultYearId.value,
    name: '',
    level: 7,
    homeroom_teacher_id: null as number | null,
});

function applyFilter(): void {
    isFiltering.value = true;
    router.get(
        route('classrooms.index'),
        {
            search: search.value || undefined,
            level: level.value || undefined,
            academic_year_id: academicYearId.value || undefined,
        },
        {
            preserveState: true,
            replace: true,
            onFinish: () => {
                isFiltering.value = false;
            },
        },
    );
}

function resetFilter(): void {
    search.value = '';
    level.value = '';
    academicYearId.value = '';
    applyFilter();
}

function openCreate(): void {
    editingItem.value = null;
    form.academic_year_id = defaultYearId.value;
    form.name = '';
    form.level = 7;
    form.homeroom_teacher_id = null;
    form.clearErrors();
    showModal.value = true;
}

function openEdit(item: ClassroomItem): void {
    editingItem.value = item;
    form.academic_year_id = item.academic_year_id;
    form.name = item.name;
    form.level = item.level;
    form.homeroom_teacher_id = item.homeroom_teacher_id;
    form.clearErrors();
    showModal.value = true;
}

function closeModal(): void {
    showModal.value = false;
    editingItem.value = null;
}

function submitForm(): void {
    if (editingItem.value) {
        form.put(route('classrooms.update', editingItem.value.id), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('classrooms.store'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    }
}

function deleteClassroom(item: ClassroomItem): void {
    if (!window.confirm(`Hapus kelas ${item.name}?`)) {
        return;
    }
    router.delete(route('classrooms.destroy', item.id), {
        preserveScroll: true,
    });
}

function handleKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape' && showModal.value) {
        closeModal();
    }
}

onMounted(() => window.addEventListener('keydown', handleKeydown));
onUnmounted(() => window.removeEventListener('keydown', handleKeydown));
</script>

<template>
    <Head title="Daftar Kelas" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-xl font-bold text-slate-900">Daftar Kelas &amp; Rombel</h1>
                    <p class="text-sm text-slate-700">
                        Kelola kelas tingkat 7, 8, dan 9 beserta penugasan guru wali kelas.
                    </p>
                </div>
                <button
                    v-if="canManage"
                    type="button"
                    @click="openCreate"
                    class="inline-flex min-h-[44px] items-center justify-center rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                >
                    Tambah Kelas
                </button>
            </div>
        </template>

        <section class="rounded border border-slate-300 bg-white p-4 sm:p-6">
            <form @submit.prevent="applyFilter" class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label for="filter-search" class="block text-sm font-semibold text-slate-900">
                        Cari Nama Kelas
                    </label>
                    <input
                        id="filter-search"
                        v-model="search"
                        type="search"
                        placeholder="Contoh: 7A"
                        class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700"
                    />
                </div>

                <div>
                    <label for="filter-level" class="block text-sm font-semibold text-slate-900">
                        Tingkat Kelas
                    </label>
                    <select
                        id="filter-level"
                        v-model="level"
                        class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700"
                    >
                        <option value="">Semua Tingkat</option>
                        <option value="7">Kelas 7</option>
                        <option value="8">Kelas 8</option>
                        <option value="9">Kelas 9</option>
                    </select>
                </div>

                <div>
                    <label for="filter-year" class="block text-sm font-semibold text-slate-900">
                        Tahun Ajaran
                    </label>
                    <select
                        id="filter-year"
                        v-model="academicYearId"
                        class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700"
                    >
                        <option value="">Semua Tahun Ajaran</option>
                        <option v-for="y in academicYears" :key="y.id" :value="String(y.id)">
                            {{ y.name }} {{ y.is_active ? '(Aktif)' : '' }}
                        </option>
                    </select>
                </div>

                <div class="flex items-end gap-2">
                    <button
                        type="submit"
                        :disabled="isFiltering"
                        class="inline-flex min-h-[44px] items-center rounded bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                    >
                        {{ isFiltering ? 'Memfilter...' : 'Terapkan' }}
                    </button>
                    <button
                        v-if="search || level || academicYearId"
                        type="button"
                        @click="resetFilter"
                        class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                    >
                        Atur Ulang
                    </button>
                </div>
            </form>

            <div
                v-if="classrooms.data.length === 0"
                class="rounded border border-slate-300 bg-slate-50 p-6 text-center"
            >
                <p class="text-base font-semibold text-slate-900">
                    Tidak ada kelas yang sesuai dengan filter.
                </p>
                <p class="mt-1 text-sm text-slate-700">
                    Ubah filter pencarian atau tambahkan kelas baru pada tahun ajaran aktif.
                </p>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-300 text-left text-sm">
                    <thead class="bg-slate-100 text-slate-900">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-bold">Nama Kelas</th>
                            <th scope="col" class="px-4 py-3 font-bold">Tingkat</th>
                            <th scope="col" class="px-4 py-3 font-bold">Tahun Ajaran</th>
                            <th scope="col" class="px-4 py-3 font-bold">Wali Kelas</th>
                            <th scope="col" class="px-4 py-3 font-bold">Jumlah Siswa</th>
                            <th v-if="canManage" scope="col" class="px-4 py-3 text-right font-bold">
                                Tindakan
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        <tr v-for="item in classrooms.data" :key="item.id">
                            <td class="px-4 py-3 font-bold text-slate-900">
                                {{ item.name }}
                            </td>
                            <td class="px-4 py-3 text-slate-800">
                                Kelas {{ item.level }}
                            </td>
                            <td class="px-4 py-3 text-slate-800">
                                {{ item.academic_year_name ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-slate-800">
                                {{ item.homeroom_teacher_name || 'Belum ditentukan' }}
                            </td>
                            <td class="px-4 py-3 tabular-nums text-slate-800">
                                {{ item.students_count ?? 0 }} siswa
                            </td>
                            <td v-if="canManage" class="px-4 py-3 text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <button
                                        type="button"
                                        @click="openEdit(item)"
                                        class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-3 py-1.5 text-xs font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                                    >
                                        Ubah
                                    </button>
                                    <button
                                        type="button"
                                        @click="deleteClassroom(item)"
                                        class="inline-flex min-h-[44px] items-center rounded border border-red-800 bg-red-50 px-3 py-1.5 text-xs font-bold text-red-900 hover:bg-red-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700"
                                    >
                                        Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <PaginationLinks
                :links="classrooms.links"
                :from="classrooms.from"
                :to="classrooms.to"
                :total="classrooms.total"
            />
        </section>

        <div
            v-if="showModal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="classroom-modal-title"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4"
        >
            <div class="w-full max-w-lg rounded border border-slate-400 bg-white p-6">
                <h2 id="classroom-modal-title" class="text-lg font-bold text-slate-900">
                    {{ editingItem ? 'Ubah Kelas' : 'Tambah Kelas Baru' }}
                </h2>

                <div
                    v-if="form.hasErrors"
                    role="alert"
                    class="mt-3 rounded border border-red-800 bg-red-50 p-3 text-sm font-medium text-red-950"
                >
                    Periksa kembali isian formulir kelas.
                </div>

                <form @submit.prevent="submitForm" class="mt-4 space-y-4">
                    <div>
                        <label for="classroom-year" class="block text-sm font-semibold text-slate-900">
                            Tahun Ajaran
                        </label>
                        <select
                            id="classroom-year"
                            v-model="form.academic_year_id"
                            required
                            class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700"
                        >
                            <option v-for="y in academicYears" :key="y.id" :value="y.id">
                                {{ y.name }} {{ y.is_active ? '(Aktif)' : '' }}
                            </option>
                        </select>
                        <p v-if="form.errors.academic_year_id" class="mt-1 text-sm font-medium text-red-800">
                            {{ form.errors.academic_year_id }}
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="classroom-name" class="block text-sm font-semibold text-slate-900">
                                Nama Kelas
                            </label>
                            <input
                                id="classroom-name"
                                v-model="form.name"
                                type="text"
                                required
                                placeholder="Contoh: 7A"
                                class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700"
                            />
                            <p v-if="form.errors.name" class="mt-1 text-sm font-medium text-red-800">
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <div>
                            <label for="classroom-level" class="block text-sm font-semibold text-slate-900">
                                Tingkat
                            </label>
                            <select
                                id="classroom-level"
                                v-model="form.level"
                                required
                                class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700"
                            >
                                <option :value="7">Kelas 7</option>
                                <option :value="8">Kelas 8</option>
                                <option :value="9">Kelas 9</option>
                            </select>
                            <p v-if="form.errors.level" class="mt-1 text-sm font-medium text-red-800">
                                {{ form.errors.level }}
                            </p>
                        </div>
                    </div>

                    <div>
                        <label for="classroom-teacher" class="block text-sm font-semibold text-slate-900">
                            Guru Wali Kelas
                        </label>
                        <select
                            id="classroom-teacher"
                            v-model="form.homeroom_teacher_id"
                            class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700"
                        >
                            <option :value="null">Belum ditentukan</option>
                            <option v-for="t in teachers" :key="t.id" :value="t.id">
                                {{ t.name }} {{ t.nip ? `(${t.nip})` : '' }}
                            </option>
                        </select>
                        <p v-if="form.errors.homeroom_teacher_id" class="mt-1 text-sm font-medium text-red-800">
                            {{ form.errors.homeroom_teacher_id }}
                        </p>
                    </div>

                    <div class="mt-6 flex flex-wrap justify-end gap-2 border-t border-slate-200 pt-4">
                        <button
                            type="button"
                            @click="closeModal"
                            class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex min-h-[44px] items-center rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700 disabled:opacity-60"
                        >
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Kelas' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

