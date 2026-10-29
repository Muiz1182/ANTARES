<script setup lang="ts">
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PaginationLinks from '@/Components/PaginationLinks.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import type { ClassroomItem, PaginatedData, StudentItem } from '@/types/models';

const props = defineProps<{
    students: PaginatedData<StudentItem>;
    classrooms: ClassroomItem[];
    filters: {
        search: string;
        classroom_id: string;
        status: string;
    };
}>();

const page = usePage();
const canManage = computed(() => page.props.auth.user.can.manage_master);

const search = ref(props.filters.search ?? '');
const classroomId = ref(props.filters.classroom_id ?? '');
const status = ref(props.filters.status ?? '');
const isFiltering = ref(false);

function applyFilter(): void {
    isFiltering.value = true;
    router.get(
        route('students.index'),
        {
            search: search.value || undefined,
            classroom_id: classroomId.value || undefined,
            status: status.value || undefined,
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
    classroomId.value = '';
    status.value = '';
    applyFilter();
}

function initials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

function deleteStudent(item: StudentItem): void {
    if (!window.confirm(`Nonaktifkan dan arsipkan data siswa ${item.name} (${item.nis})?`)) {
        return;
    }
    router.delete(route('students.destroy', item.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Data Siswa" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-xl font-bold text-slate-900">Data Siswa</h1>
                    <p class="text-sm text-slate-700">
                        Daftar siswa beserta NIS, NISN, penempatan kelas, dan kontak orang tua.
                    </p>
                </div>
                <div v-if="canManage" class="flex flex-wrap gap-2">
                    <Link
                        :href="route('students.import.show')"
                        class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                    >
                        Impor Excel / CSV
                    </Link>
                    <Link
                        :href="route('students.create')"
                        class="inline-flex min-h-[44px] items-center rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                    >
                        Tambah Siswa
                    </Link>
                </div>
            </div>
        </template>

        <section class="rounded border border-slate-300 bg-white p-4 sm:p-6">
            <form @submit.prevent="applyFilter" class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label for="student-search" class="block text-sm font-semibold text-slate-900">
                        Cari Nama / NIS / NISN
                    </label>
                    <input
                        id="student-search"
                        v-model="search"
                        type="search"
                        placeholder="Contoh: 260001 atau Ahmad"
                        class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700"
                    />
                </div>

                <div>
                    <label for="student-classroom" class="block text-sm font-semibold text-slate-900">
                        Filter Kelas
                    </label>
                    <select
                        id="student-classroom"
                        v-model="classroomId"
                        class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700"
                    >
                        <option value="">Semua Kelas</option>
                        <option v-for="c in classrooms" :key="c.id" :value="String(c.id)">
                            {{ c.name }}
                        </option>
                    </select>
                </div>

                <div>
                    <label for="student-status" class="block text-sm font-semibold text-slate-900">
                        Status Siswa
                    </label>
                    <select
                        id="student-status"
                        v-model="status"
                        class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700"
                    >
                        <option value="">Semua Status</option>
                        <option value="active">Aktif</option>
                        <option value="inactive">Nonaktif</option>
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
                        v-if="search || classroomId || status"
                        type="button"
                        @click="resetFilter"
                        class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                    >
                        Atur Ulang
                    </button>
                </div>
            </form>

            <div
                v-if="students.data.length === 0"
                class="rounded border border-slate-300 bg-slate-50 p-6 text-center"
            >
                <p class="text-base font-semibold text-slate-900">
                    Belum ada data siswa yang sesuai dengan kriteria pencarian.
                </p>
                <p class="mt-1 text-sm text-slate-700">
                    Ubah filter pencarian, tambah siswa secara manual, atau impor daftar siswa dari berkas Excel/CSV.
                </p>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-300 text-left text-sm">
                    <thead class="bg-slate-100 text-slate-900">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-bold">Siswa</th>
                            <th scope="col" class="px-4 py-3 font-bold">NIS / NISN</th>
                            <th scope="col" class="px-4 py-3 font-bold">Kelas</th>
                            <th scope="col" class="px-4 py-3 font-bold">L/P</th>
                            <th scope="col" class="px-4 py-3 font-bold">Orang Tua / Wali</th>
                            <th scope="col" class="px-4 py-3 font-bold">Status</th>
                            <th scope="col" class="px-4 py-3 text-right font-bold">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        <tr v-for="item in students.data" :key="item.id">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <img
                                        v-if="item.photo_url"
                                        :src="item.photo_url"
                                        :alt="`Foto ${item.name}`"
                                        class="h-10 w-10 shrink-0 rounded border border-slate-400 object-cover"
                                    />
                                    <span
                                        v-else
                                        aria-hidden="true"
                                        class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded border border-slate-400 bg-slate-200 text-xs font-bold text-slate-900"
                                    >
                                        {{ initials(item.name) }}
                                    </span>
                                    <span class="font-semibold text-slate-900">{{ item.name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 tabular-nums text-slate-800">
                                <div class="font-semibold text-slate-900">{{ item.nis }}</div>
                                <div class="text-xs text-slate-700">NISN: {{ item.nisn || '-' }}</div>
                            </td>
                            <td class="px-4 py-3 font-semibold text-slate-900">
                                {{ item.classroom_name || 'Belum ditempatkan' }}
                            </td>
                            <td class="px-4 py-3 text-slate-800">
                                {{ item.gender_label }}
                            </td>
                            <td class="px-4 py-3 text-slate-800">
                                <div>{{ item.parent_name || '-' }}</div>
                                <div class="tabular-nums text-xs text-slate-700">
                                    {{ item.parent_phone || '-' }}
                                </div>
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
                            <td class="px-4 py-3 text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <Link
                                        :href="route('students.show', item.id)"
                                        class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-3 py-1.5 text-xs font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                                    >
                                        Detail
                                    </Link>
                                    <Link
                                        v-if="canManage"
                                        :href="route('students.edit', item.id)"
                                        class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-3 py-1.5 text-xs font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                                    >
                                        Ubah
                                    </Link>
                                    <button
                                        v-if="canManage"
                                        type="button"
                                        @click="deleteStudent(item)"
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
                :links="students.links"
                :from="students.from"
                :to="students.to"
                :total="students.total"
            />
        </section>
    </AuthenticatedLayout>
</template>

