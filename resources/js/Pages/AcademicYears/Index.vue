<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PaginationLinks from '@/Components/PaginationLinks.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import type { AcademicYearItem, PaginatedData } from '@/types/models';

const props = defineProps<{
    academicYears: PaginatedData<AcademicYearItem>;
    filters: {
        search: string;
    };
}>();

const page = usePage();
const canManage = computed(() => page.props.auth.user.can.manage_master);

const search = ref(props.filters.search ?? '');
const isFiltering = ref(false);
const showModal = ref(false);
const editingItem = ref<AcademicYearItem | null>(null);

const form = useForm({
    name: '',
    start_date: '',
    end_date: '',
    is_active: false,
});

function applySearch(): void {
    isFiltering.value = true;
    router.get(
        route('academic-years.index'),
        { search: search.value || undefined },
        {
            preserveState: true,
            replace: true,
            onFinish: () => {
                isFiltering.value = false;
            },
        },
    );
}

function resetSearch(): void {
    search.value = '';
    applySearch();
}

function openCreate(): void {
    editingItem.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
}

function openEdit(item: AcademicYearItem): void {
    editingItem.value = item;
    form.name = item.name;
    form.start_date = item.start_date;
    form.end_date = item.end_date;
    form.is_active = item.is_active;
    form.clearErrors();
    showModal.value = true;
}

function closeModal(): void {
    showModal.value = false;
    editingItem.value = null;
}

function submitForm(): void {
    if (editingItem.value) {
        form.put(route('academic-years.update', editingItem.value.id), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('academic-years.store'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    }
}

function activateYear(item: AcademicYearItem): void {
    router.put(
        route('academic-years.update', item.id),
        {
            name: item.name,
            start_date: item.start_date,
            end_date: item.end_date,
            is_active: true,
        },
        { preserveScroll: true },
    );
}

function deleteYear(item: AcademicYearItem): void {
    if (!window.confirm(`Hapus tahun ajaran ${item.name}?`)) {
        return;
    }
    router.delete(route('academic-years.destroy', item.id), {
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
    <Head title="Tahun Ajaran" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-xl font-bold text-slate-900">Tahun Ajaran</h1>
                    <p class="text-sm text-slate-700">
                        Hanya satu tahun ajaran yang dapat berstatus aktif pada satu waktu.
                    </p>
                </div>
                <button
                    v-if="canManage"
                    type="button"
                    @click="openCreate"
                    class="inline-flex min-h-[44px] items-center justify-center rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                >
                    Tambah Tahun Ajaran
                </button>
            </div>
        </template>

        <section class="rounded border border-slate-300 bg-white p-4 sm:p-6">
            <form @submit.prevent="applySearch" class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label for="search-year" class="block text-sm font-semibold text-slate-900">
                        Cari Tahun Ajaran
                    </label>
                    <input
                        id="search-year"
                        v-model="search"
                        type="search"
                        placeholder="Contoh: 2026/2027"
                        class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700"
                    />
                </div>
                <div class="flex flex-wrap gap-2">
                    <button
                        type="submit"
                        :disabled="isFiltering"
                        class="inline-flex min-h-[44px] items-center rounded bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                    >
                        {{ isFiltering ? 'Mencari...' : 'Cari' }}
                    </button>
                    <button
                        v-if="search"
                        type="button"
                        @click="resetSearch"
                        class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                    >
                        Atur Ulang
                    </button>
                </div>
            </form>

            <div
                v-if="academicYears.data.length === 0"
                class="rounded border border-slate-300 bg-slate-50 p-6 text-center"
            >
                <p class="text-base font-semibold text-slate-900">
                    Belum ada data tahun ajaran yang ditemukan.
                </p>
                <p class="mt-1 text-sm text-slate-700">
                    Tambahkan tahun ajaran baru untuk mulai membuat daftar kelas dan siswa.
                </p>
                <button
                    v-if="canManage"
                    type="button"
                    @click="openCreate"
                    class="mt-4 inline-flex min-h-[44px] items-center rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                >
                    Tambah Tahun Ajaran Pertama
                </button>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-300 text-left text-sm">
                    <thead class="bg-slate-100 text-slate-900">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-bold">Tahun Ajaran</th>
                            <th scope="col" class="px-4 py-3 font-bold">Periode</th>
                            <th scope="col" class="px-4 py-3 font-bold">Jumlah Kelas</th>
                            <th scope="col" class="px-4 py-3 font-bold">Status</th>
                            <th v-if="canManage" scope="col" class="px-4 py-3 text-right font-bold">
                                Tindakan
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        <tr v-for="item in academicYears.data" :key="item.id">
                            <td class="px-4 py-3 font-semibold text-slate-900">
                                {{ item.name }}
                            </td>
                            <td class="px-4 py-3 tabular-nums text-slate-800">
                                {{ item.start_date }} s/d {{ item.end_date }}
                            </td>
                            <td class="px-4 py-3 tabular-nums text-slate-800">
                                {{ item.classrooms_count ?? 0 }} kelas
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    v-if="item.is_active"
                                    class="inline-flex items-center rounded border border-emerald-800 bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-950"
                                >
                                    Aktif
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center rounded border border-slate-400 bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-800"
                                >
                                    Nonaktif
                                </span>
                            </td>
                            <td v-if="canManage" class="px-4 py-3 text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <button
                                        v-if="!item.is_active"
                                        type="button"
                                        @click="activateYear(item)"
                                        class="inline-flex min-h-[44px] items-center rounded border border-emerald-800 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-950 hover:bg-emerald-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                                    >
                                        Jadikan Aktif
                                    </button>
                                    <button
                                        type="button"
                                        @click="openEdit(item)"
                                        class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-3 py-1.5 text-xs font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                                    >
                                        Ubah
                                    </button>
                                    <button
                                        v-if="!item.is_active"
                                        type="button"
                                        @click="deleteYear(item)"
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
                :links="academicYears.links"
                :from="academicYears.from"
                :to="academicYears.to"
                :total="academicYears.total"
            />
        </section>

        <div
            v-if="showModal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="academic-year-modal-title"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4"
        >
            <div class="w-full max-w-lg rounded border border-slate-400 bg-white p-6">
                <h2 id="academic-year-modal-title" class="text-lg font-bold text-slate-900">
                    {{ editingItem ? 'Ubah Tahun Ajaran' : 'Tambah Tahun Ajaran' }}
                </h2>

                <div
                    v-if="form.hasErrors"
                    role="alert"
                    class="mt-3 rounded border border-red-800 bg-red-50 p-3 text-sm font-medium text-red-950"
                >
                    Periksa kembali isian formulir tahun ajaran.
                </div>

                <form @submit.prevent="submitForm" class="mt-4 space-y-4">
                    <div>
                        <label for="year-name" class="block text-sm font-semibold text-slate-900">
                            Nama Tahun Ajaran
                        </label>
                        <input
                            id="year-name"
                            v-model="form.name"
                            type="text"
                            required
                            placeholder="Contoh: 2026/2027"
                            class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700"
                        />
                        <p v-if="form.errors.name" class="mt-1 text-sm font-medium text-red-800">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="start-date" class="block text-sm font-semibold text-slate-900">
                                Tanggal Mulai
                            </label>
                            <input
                                id="start-date"
                                v-model="form.start_date"
                                type="date"
                                required
                                class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700"
                            />
                            <p v-if="form.errors.start_date" class="mt-1 text-sm font-medium text-red-800">
                                {{ form.errors.start_date }}
                            </p>
                        </div>

                        <div>
                            <label for="end-date" class="block text-sm font-semibold text-slate-900">
                                Tanggal Selesai
                            </label>
                            <input
                                id="end-date"
                                v-model="form.end_date"
                                type="date"
                                required
                                class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700"
                            />
                            <p v-if="form.errors.end_date" class="mt-1 text-sm font-medium text-red-800">
                                {{ form.errors.end_date }}
                            </p>
                        </div>
                    </div>

                    <div>
                        <label for="year-active" class="inline-flex min-h-[44px] items-center gap-2 text-sm font-semibold text-slate-900">
                            <input
                                id="year-active"
                                v-model="form.is_active"
                                type="checkbox"
                                class="h-5 w-5 rounded border-slate-500 text-emerald-700 focus:ring-emerald-700"
                            />
                            <span>Jadikan tahun ajaran aktif</span>
                        </label>
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
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Tahun Ajaran' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

