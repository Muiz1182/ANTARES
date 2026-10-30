<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import type { DeviceItem } from '@/types/models';

const props = defineProps<{
    devices: DeviceItem[];
}>();

const page = usePage();
const flash = computed(() => page.props.flash);

const showModal = ref(false);
const editingDevice = ref<DeviceItem | null>(null);
const copiedToken = ref(false);
const regeneratingId = ref<number | null>(null);

const form = useForm({
    name: '',
    location: '',
    is_active: true,
});

function openCreateModal(): void {
    editingDevice.value = null;
    form.reset();
    form.clearErrors();
    form.is_active = true;
    showModal.value = true;
}

function openEditModal(device: DeviceItem): void {
    editingDevice.value = device;
    form.clearErrors();
    form.name = device.name;
    form.location = device.location ?? '';
    form.is_active = device.is_active;
    showModal.value = true;
}

function closeModal(): void {
    showModal.value = false;
    editingDevice.value = null;
}

function submitForm(): void {
    if (editingDevice.value) {
        form.put(route('devices.update', editingDevice.value.id), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
        return;
    }

    form.post(route('devices.store'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    });
}

function regenerateToken(device: DeviceItem): void {
    regeneratingId.value = device.id;
    copiedToken.value = false;
    router.post(
        route('devices.regenerate-token', device.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                regeneratingId.value = null;
            },
        },
    );
}

function toggleDeviceActive(device: DeviceItem): void {
    router.put(
        route('devices.update', device.id),
        {
            name: device.name,
            location: device.location,
            is_active: !device.is_active,
        },
        { preserveScroll: true },
    );
}

async function copyDeviceToken(token: string): Promise<void> {
    if (typeof navigator !== 'undefined' && navigator.clipboard) {
        await navigator.clipboard.writeText(token);
        copiedToken.value = true;
    }
}

function saveTokenToLocalStation(token: string): void {
    if (typeof window !== 'undefined') {
        window.localStorage.setItem('antares_scan_device_token', token);
        copiedToken.value = true;
    }
}

function handleEscape(event: KeyboardEvent): void {
    if (event.key === 'Escape' && showModal.value) {
        closeModal();
    }
}

onMounted(() => window.addEventListener('keydown', handleEscape));
onUnmounted(() => window.removeEventListener('keydown', handleEscape));
</script>

<template>
    <Head title="Perangkat Pos Absensi" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-xl font-bold text-slate-900">
                        Perangkat Pos Absensi (Scan Station)
                    </h1>
                    <p class="text-sm text-slate-700">
                        Kelola daftar komputer pos pembaca kartu NFC dan terbitkan token autentikasi API pos.
                    </p>
                </div>

                <button
                    type="button"
                    @click="openCreateModal"
                    class="inline-flex min-h-[44px] items-center justify-center rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                >
                    Tambah Perangkat Pos
                </button>
            </div>
        </template>

        <section class="space-y-6">
            <div
                v-if="flash?.plain_device_token"
                role="region"
                aria-label="Token Perangkat Baru"
                class="rounded border-2 border-amber-800 bg-amber-50 p-5 text-slate-900"
            >
                <h2 class="text-base font-bold text-amber-950">
                    Token Perangkat untuk "{{ flash.plain_device_name }}" (Ditampilkan Satu Kali)
                </h2>
                <p class="mt-1 text-sm text-amber-950">
                    Salin dan simpan token ini sekarang untuk dimasukkan pada halaman Pos Absensi. Demi keamanan, sistem hanya menyimpan hash SHA-256 sehingga token asli tidak dapat dilihat kembali setelah halaman ini ditutup.
                </p>

                <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <code
                        class="block flex-1 overflow-x-auto rounded border border-amber-900 bg-white px-3 py-2.5 font-mono text-sm font-bold text-slate-900"
                    >
                        {{ flash.plain_device_token }}
                    </code>
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            @click="copyDeviceToken(flash.plain_device_token)"
                            class="inline-flex min-h-[44px] items-center rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                        >
                            {{ copiedToken ? 'Token Tersalin' : 'Salin Token' }}
                        </button>
                        <button
                            type="button"
                            @click="saveTokenToLocalStation(flash.plain_device_token)"
                            class="inline-flex min-h-[44px] items-center rounded border border-slate-600 bg-white px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                        >
                            Pasang di Browser Ini
                        </button>
                    </div>
                </div>
            </div>

            <div
                v-if="props.devices.length === 0"
                class="rounded border border-slate-300 bg-white p-8 text-center"
            >
                <h2 class="text-base font-bold text-slate-900">
                    Belum Ada Perangkat Pos Terdaftar
                </h2>
                <p class="mt-1 text-sm text-slate-700">
                    Tambahkan perangkat pos pertama untuk mendapatkan token autentikasi yang diperlukan oleh layar Pos Absensi.
                </p>
                <div class="mt-4">
                    <button
                        type="button"
                        @click="openCreateModal"
                        class="inline-flex min-h-[44px] items-center rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                    >
                        Tambah Perangkat Pos Pertama
                    </button>
                </div>
            </div>

            <div
                v-else
                class="overflow-x-auto rounded border border-slate-300 bg-white"
            >
                <table class="min-w-full divide-y divide-slate-300 text-left text-sm">
                    <thead class="bg-slate-100 text-xs font-bold uppercase text-slate-800">
                        <tr>
                            <th scope="col" class="px-4 py-3">Nama Perangkat Pos</th>
                            <th scope="col" class="px-4 py-3">Lokasi</th>
                            <th scope="col" class="px-4 py-3">Status Akses</th>
                            <th scope="col" class="px-4 py-3">Terakhir Terhubung</th>
                            <th scope="col" class="px-4 py-3 text-right">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white text-slate-900">
                        <tr v-for="device in props.devices" :key="device.id">
                            <td class="px-4 py-3 font-semibold">{{ device.name }}</td>
                            <td class="px-4 py-3 text-slate-700">
                                {{ device.location || 'Lokasi belum diisi' }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded border px-2.5 py-1 text-xs font-bold"
                                    :class="
                                        device.is_active
                                            ? 'border-emerald-800 bg-emerald-50 text-emerald-950'
                                            : 'border-red-800 bg-red-50 text-red-950'
                                    "
                                >
                                    {{ device.is_active ? 'Aktif' : 'Dinonaktifkan' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                {{ device.last_seen_at_formatted || 'Belum pernah terhubung' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <button
                                        type="button"
                                        @click="openEditModal(device)"
                                        class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-3 py-2 text-xs font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                                    >
                                        Ubah
                                    </button>
                                    <button
                                        type="button"
                                        :disabled="regeneratingId === device.id"
                                        @click="regenerateToken(device)"
                                        class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-3 py-2 text-xs font-semibold text-slate-900 hover:bg-slate-100 disabled:opacity-60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                                    >
                                        {{ regeneratingId === device.id ? 'Memproses...' : 'Buat Ulang Token' }}
                                    </button>
                                    <button
                                        type="button"
                                        @click="toggleDeviceActive(device)"
                                        class="inline-flex min-h-[44px] items-center rounded border px-3 py-2 text-xs font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                                        :class="
                                            device.is_active
                                                ? 'border-red-800 bg-red-50 text-red-950 hover:bg-red-100'
                                                : 'border-emerald-800 bg-emerald-50 text-emerald-950 hover:bg-emerald-100'
                                        "
                                    >
                                        {{ device.is_active ? 'Cabut Akses' : 'Aktifkan Kembali' }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div
            v-if="showModal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="device-modal-title"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4"
        >
            <div class="w-full max-w-lg rounded border border-slate-400 bg-white p-6">
                <h2 id="device-modal-title" class="text-lg font-bold text-slate-900">
                    {{ editingDevice ? 'Ubah Perangkat Pos' : 'Tambah Perangkat Pos Baru' }}
                </h2>

                <form @submit.prevent="submitForm" class="mt-4 space-y-4">
                    <div>
                        <label for="device-name" class="block text-sm font-semibold text-slate-900">
                            Nama Perangkat Pos
                        </label>
                        <input
                            id="device-name"
                            v-model="form.name"
                            type="text"
                            required
                            placeholder="Contoh: Pos Gerbang Utama"
                            class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:ring-emerald-700"
                        />
                        <p v-if="form.errors.name" class="mt-1 text-xs font-semibold text-red-800">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div>
                        <label for="device-location" class="block text-sm font-semibold text-slate-900">
                            Lokasi Penempatan (Opsional)
                        </label>
                        <input
                            id="device-location"
                            v-model="form.location"
                            type="text"
                            placeholder="Contoh: Lobby Ruang Piket"
                            class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:ring-emerald-700"
                        />
                        <p v-if="form.errors.location" class="mt-1 text-xs font-semibold text-red-800">
                            {{ form.errors.location }}
                        </p>
                    </div>

                    <div v-if="editingDevice" class="flex items-center gap-2 pt-1">
                        <input
                            id="device-is-active"
                            v-model="form.is_active"
                            type="checkbox"
                            class="h-5 w-5 rounded border-slate-500 text-emerald-700 focus:ring-emerald-700"
                        />
                        <label for="device-is-active" class="text-sm font-semibold text-slate-900">
                            Perangkat diizinkan mengirim data scan
                        </label>
                    </div>

                    <div class="flex justify-end gap-2 pt-3">
                        <button
                            type="button"
                            @click="closeModal"
                            class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex min-h-[44px] items-center rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 disabled:opacity-60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                        >
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Perangkat' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

