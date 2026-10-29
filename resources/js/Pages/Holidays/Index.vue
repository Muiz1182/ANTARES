<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PaginationLinks from '@/Components/PaginationLinks.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import type { HolidayItem, PaginatedData } from '@/types/models';

const props = defineProps<{
    holidays: PaginatedData<HolidayItem>;
    filters: {
        search: string;
        year: string;
    };
}>();

const page = usePage();
const canManage = computed(() => page.props.auth.user.can.manage_master);

const search = ref(props.filters.search ?? '');
const year = ref(props.filters.year ?? '');
const isFiltering = ref(false);
const showModal = ref(false);
const editingItem = ref<HolidayItem | null>(null);

const form = useForm({
    date: '',
    name: '',
    description: '',
});

function applyFilter(): void {
    isFiltering.value = true;
    router.get(
        route('holidays.index'),
        {
            search: search.value || undefined,
            year: year.value || undefined,
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
    year.value = '';
    applyFilter();
}

function openCreate(): void {
    editingItem.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
}

function openEdit(item: HolidayItem): void {
    editingItem.value = item;
    form.date = item.date;
    form.name = item.name;
    form.description = item.description ?? '';
    form.clearErrors();
    showModal.value = true;
}

function closeModal(): void {
    showModal.value = false;
    editingItem.value = null;
}

function submitForm(): void {
    if (editingItem.value) {
        form.put(route('holidays.update', editingItem.value.id), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('holidays.store'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    }
}

function deleteHoliday(item: HolidayItem): void {
    if (!window.confirm(`Hapus hari libur ${item.name} (${item.date})?`)) {
        return;
    }
    router.delete(route('holidays.destroy', item.id), {
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
    <Head title="Hari Libur" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-xl font-bold text-slate-900">Kalender Hari Libur</h1>
                    <p class="text-sm text-slate-700">
                        Tanggal yang terdaftar di sini dilewati oleh pemeriksaan hari sekolah aktif.
                    </p>
                </div>
                <button
                    v-if="canManage"
                    type="button"
                    @click="openCreate"
                    class="inline-flex min-h-[44px] items-center justify-center rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                >
                    Tambah Hari Libur
                </button>
            </div>
        </template>

        <section class="rounded border border-slate-300 bg-white p-4 sm:p-6">
            <form @submit.prevent="applyFilter" class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <label for="holiday-search" class="block text-sm font-semibold text-slate-900">
                        Cari Nama Hari Libur
                    </label>
                    <input
                        id="holiday-search"
                        v-model="search"
                        type="search"
                        placeholder="Contoh: Kemerdekaan"
                        class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700"
                    />
                </div>

                <div>
                    <label for="holiday-year" class="block text-sm font-semibold text-slate-900">
                        Tahun
                    </label>
                    <input
                        id="holiday-year"
                        v-model="year"
                        type="number"
                        min="2020"
                        max="2040"
                        placeholder="Contoh: 2026"
                        class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700"
                    />
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
                        v-if="search || year"
                        type="button"
                        @click="resetFilter"
                        class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                    >
                        Atur Ulang
                    </button>
                </div>
            </form>

            <div
                v-if="holidays.data.length === 0"
                class="rounded border border-slate-300 bg-slate-50 p-6 text-center"
            >
                <p class="text-base font-semibold text-slate-900">
                    Belum ada hari libur yang tercatat.
                </p>
                <p class="mt-1 text-sm text-slate-700">
                    Tambahkan tanggal libur nasional atau libur akademik sekolah.
                </p>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-300 text-left text-sm">
                    <thead class="bg-slate-100 text-slate-900">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-bold">Tanggal</th>
                            <th scope="col" class="px-4 py-3 font-bold">Nama Hari Libur</th>
                            <th scope="col" class="px-4 py-3 font-bold">Keterangan</th>
                            <th v-if="canManage" scope="col" class="px-4 py-3 text-right font-bold">
                                Tindakan
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        <tr v-for="item in holidays.data" :key="item.id">
                            <td class="px-4 py-3 tabular-nums font-semibold text-slate-900">
                                {{ item.date }}
                            </td>
                            <td class="px-4 py-3 font-semibold text-slate-900">
                                {{ item.name }}
                            </td>
                            <td class="px-4 py-3 text-slate-800">
                                {{ item.description || '-' }}
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
                                        @click="deleteHoliday(item)"
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
                :links="holidays.links"
                :from="holidays.from"
                :to="holidays.to"
                :total="holidays.total"
            />
        </section>

        <div
            v-if="showModal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="holiday-modal-title"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4"
        >
            <div class="w-full max-w-lg rounded border border-slate-400 bg-white p-6">
                <h2 id="holiday-modal-title" class="text-lg font-bold text-slate-900">
                    {{ editingItem ? 'Ubah Hari Libur' : 'Tambah Hari Libur' }}
                </h2>

                <div
                    v-if="form.hasErrors"
                    role="alert"
                    class="mt-3 rounded border border-red-800 bg-red-50 p-3 text-sm font-medium text-red-950"
                >
                    Periksa kembali isian formulir hari libur.
                </div>

                <form @submit.prevent="submitForm" class="mt-4 space-y-4">
                    <div>
                        <label for="holiday-date" class="block text-sm font-semibold text-slate-900">
                            Tanggal Libur *
                        </label>
                        <input
                            id="holiday-date"
                            v-model="form.date"
                            type="date"
                            required
                            class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700"
                        />
                        <p v-if="form.errors.date" class="mt-1 text-sm font-medium text-red-800">
                            {{ form.errors.date }}
                        </p>
                    </div>

                    <div>
                        <label for="holiday-name" class="block text-sm font-semibold text-slate-900">
                            Nama Hari Libur *
                        </label>
                        <input
                            id="holiday-name"
                            v-model="form.name"
                            type="text"
                            required
                            placeholder="Contoh: Libur Semester Ganjil"
                            class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700"
                        />
                        <p v-if="form.errors.name" class="mt-1 text-sm font-medium text-red-800">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div>
                        <label for="holiday-desc" class="block text-sm font-semibold text-slate-900">
                            Keterangan (Opsional)
                        </label>
                        <input
                            id="holiday-desc"
                            v-model="form.description"
                            type="text"
                            placeholder="Keterangan tambahan"
                            class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700"
                        />
                        <p v-if="form.errors.description" class="mt-1 text-sm font-medium text-red-800">
                            {{ form.errors.description }}
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
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Hari Libur' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

