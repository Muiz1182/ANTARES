<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PaginationLinks from '@/Components/PaginationLinks.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import type { PaginatedData, RoleOption, UserItem } from '@/types/models';

const props = defineProps<{
    users: PaginatedData<UserItem>;
    roleOptions: RoleOption[];
    filters: {
        search: string;
        role: string;
    };
}>();

const page = usePage();
const currentUserId = computed(() => page.props.auth.user.id);

const search = ref(props.filters.search ?? '');
const role = ref(props.filters.role ?? '');
const isFiltering = ref(false);
const showModal = ref(false);
const editingItem = ref<UserItem | null>(null);

const form = useForm({
    name: '',
    email: '',
    nip: '',
    phone: '',
    role: props.roleOptions[0]?.value ?? 'wali_kelas',
    password: '',
    is_active: true,
});

function applyFilter(): void {
    isFiltering.value = true;
    router.get(
        route('users.index'),
        {
            search: search.value || undefined,
            role: role.value || undefined,
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
    role.value = '';
    applyFilter();
}

function openCreate(): void {
    editingItem.value = null;
    form.name = '';
    form.email = '';
    form.nip = '';
    form.phone = '';
    form.role = 'wali_kelas';
    form.password = '';
    form.is_active = true;
    form.clearErrors();
    showModal.value = true;
}

function openEdit(item: UserItem): void {
    editingItem.value = item;
    form.name = item.name;
    form.email = item.email;
    form.nip = item.nip ?? '';
    form.phone = item.phone ?? '';
    form.role = item.role ?? 'wali_kelas';
    form.password = '';
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
        form.put(route('users.update', editingItem.value.id), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('users.store'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    }
}

function deleteUser(item: UserItem): void {
    if (!window.confirm(`Hapus akun pengguna ${item.name}?`)) {
        return;
    }
    router.delete(route('users.destroy', item.id), {
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
    <Head title="Pengguna & Guru" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-xl font-bold text-slate-900">Manajemen Pengguna &amp; Guru</h1>
                    <p class="text-sm text-slate-700">
                        Kelola akun staf sekolah, guru wali kelas, dan peran akses sistem.
                    </p>
                </div>
                <button
                    type="button"
                    @click="openCreate"
                    class="inline-flex min-h-[44px] items-center justify-center rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                >
                    Tambah Pengguna
                </button>
            </div>
        </template>

        <section class="rounded border border-slate-300 bg-white p-4 sm:p-6">
            <form @submit.prevent="applyFilter" class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <label for="user-search" class="block text-sm font-semibold text-slate-900">
                        Cari Nama / Email / NIP
                    </label>
                    <input
                        id="user-search"
                        v-model="search"
                        type="search"
                        placeholder="Contoh: Budi atau 1986..."
                        class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700"
                    />
                </div>

                <div>
                    <label for="user-role" class="block text-sm font-semibold text-slate-900">
                        Filter Peran
                    </label>
                    <select
                        id="user-role"
                        v-model="role"
                        class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700"
                    >
                        <option value="">Semua Peran</option>
                        <option v-for="r in roleOptions" :key="r.value" :value="r.value">
                            {{ r.label }}
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
                        v-if="search || role"
                        type="button"
                        @click="resetFilter"
                        class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                    >
                        Atur Ulang
                    </button>
                </div>
            </form>

            <div
                v-if="users.data.length === 0"
                class="rounded border border-slate-300 bg-slate-50 p-6 text-center"
            >
                <p class="text-base font-semibold text-slate-900">
                    Tidak ada pengguna yang cocok dengan filter pencarian.
                </p>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-300 text-left text-sm">
                    <thead class="bg-slate-100 text-slate-900">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-bold">Nama &amp; NIP</th>
                            <th scope="col" class="px-4 py-3 font-bold">Email &amp; Telepon</th>
                            <th scope="col" class="px-4 py-3 font-bold">Peran</th>
                            <th scope="col" class="px-4 py-3 font-bold">Status</th>
                            <th scope="col" class="px-4 py-3 text-right font-bold">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        <tr v-for="item in users.data" :key="item.id">
                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-900">{{ item.name }}</div>
                                <div class="tabular-nums text-xs text-slate-700">
                                    NIP: {{ item.nip || '-' }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-slate-800">
                                <div>{{ item.email }}</div>
                                <div class="tabular-nums text-xs text-slate-700">{{ item.phone || '-' }}</div>
                            </td>
                            <td class="px-4 py-3 font-semibold text-slate-900">
                                {{ item.role_label || '-' }}
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
                                    <button
                                        type="button"
                                        @click="openEdit(item)"
                                        class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-3 py-1.5 text-xs font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                                    >
                                        Ubah
                                    </button>
                                    <button
                                        v-if="item.id !== currentUserId"
                                        type="button"
                                        @click="deleteUser(item)"
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
                :links="users.links"
                :from="users.from"
                :to="users.to"
                :total="users.total"
            />
        </section>

        <div
            v-if="showModal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="user-modal-title"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4"
        >
            <div class="w-full max-w-lg rounded border border-slate-400 bg-white p-6">
                <h2 id="user-modal-title" class="text-lg font-bold text-slate-900">
                    {{ editingItem ? 'Ubah Pengguna' : 'Tambah Pengguna Baru' }}
                </h2>

                <div
                    v-if="form.hasErrors"
                    role="alert"
                    class="mt-3 rounded border border-red-800 bg-red-50 p-3 text-sm font-medium text-red-950"
                >
                    Periksa kembali isian formulir pengguna.
                </div>

                <form @submit.prevent="submitForm" class="mt-4 space-y-4">
                    <div>
                        <label for="user-name" class="block text-sm font-semibold text-slate-900">
                            Nama Lengkap *
                        </label>
                        <input
                            id="user-name"
                            v-model="form.name"
                            type="text"
                            required
                            placeholder="Nama lengkap staf atau guru"
                            class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700"
                        />
                        <p v-if="form.errors.name" class="mt-1 text-sm font-medium text-red-800">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="user-email" class="block text-sm font-semibold text-slate-900">
                                Alamat Email *
                            </label>
                            <input
                                id="user-email"
                                v-model="form.email"
                                type="email"
                                required
                                placeholder="nama@sekolah.sch.id"
                                class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700"
                            />
                            <p v-if="form.errors.email" class="mt-1 text-sm font-medium text-red-800">
                                {{ form.errors.email }}
                            </p>
                        </div>

                        <div>
                            <label for="user-nip" class="block text-sm font-semibold text-slate-900">
                                NIP (Opsional)
                            </label>
                            <input
                                id="user-nip"
                                v-model="form.nip"
                                type="text"
                                placeholder="18 digit NIP"
                                class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700"
                            />
                            <p v-if="form.errors.nip" class="mt-1 text-sm font-medium text-red-800">
                                {{ form.errors.nip }}
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="user-role-select" class="block text-sm font-semibold text-slate-900">
                                Peran Akses *
                            </label>
                            <select
                                id="user-role-select"
                                v-model="form.role"
                                required
                                class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700"
                            >
                                <option v-for="r in roleOptions" :key="r.value" :value="r.value">
                                    {{ r.label }}
                                </option>
                            </select>
                            <p v-if="form.errors.role" class="mt-1 text-sm font-medium text-red-800">
                                {{ form.errors.role }}
                            </p>
                        </div>

                        <div>
                            <label for="user-phone" class="block text-sm font-semibold text-slate-900">
                                No. Telepon
                            </label>
                            <input
                                id="user-phone"
                                v-model="form.phone"
                                type="tel"
                                placeholder="Contoh: 081234567890"
                                class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700"
                            />
                            <p v-if="form.errors.phone" class="mt-1 text-sm font-medium text-red-800">
                                {{ form.errors.phone }}
                            </p>
                        </div>
                    </div>

                    <div>
                        <label for="user-password" class="block text-sm font-semibold text-slate-900">
                            {{ editingItem ? 'Kata Sandi Baru (Kosongkan jika tidak diubah)' : 'Kata Sandi *' }}
                        </label>
                        <input
                            id="user-password"
                            v-model="form.password"
                            type="password"
                            :required="!editingItem"
                            autocomplete="new-password"
                            class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700"
                        />
                        <p v-if="form.errors.password" class="mt-1 text-sm font-medium text-red-800">
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <div>
                        <label for="user-active" class="inline-flex min-h-[44px] items-center gap-2 text-sm font-semibold text-slate-900">
                            <input
                                id="user-active"
                                v-model="form.is_active"
                                type="checkbox"
                                class="h-5 w-5 rounded border-slate-500 text-emerald-700 focus:ring-emerald-700"
                            />
                            <span>Akun pengguna aktif</span>
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
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Pengguna' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

