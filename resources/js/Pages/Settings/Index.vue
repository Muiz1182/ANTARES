<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import type { SchoolSettingItem } from '@/types/models';

const props = defineProps<{
    setting: SchoolSettingItem;
}>();

const form = useForm<{
    school_name: string;
    school_address: string;
    principal_name: string;
    principal_nip: string;
    logo: File | null;
    remove_logo: boolean;
}>({
    school_name: props.setting.school_name ?? '',
    school_address: props.setting.school_address ?? '',
    principal_name: props.setting.principal_name ?? '',
    principal_nip: props.setting.principal_nip ?? '',
    logo: null,
    remove_logo: false,
});

function handleLogoChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    form.logo = target.files && target.files.length > 0 ? target.files[0] : null;
    if (form.logo) {
        form.remove_logo = false;
    }
}

function submit(): void {
    form.post(route('settings.update'), {
        forceFormData: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Pengaturan Identitas Sekolah" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-xl font-bold text-slate-900">
                        Pengaturan Identitas &amp; Kop Sekolah
                    </h1>
                    <p class="text-sm text-slate-700">
                        Atur nama sekolah, alamat, identitas kepala sekolah untuk tanda tangan laporan, serta logo sekolah.
                    </p>
                </div>
            </div>
        </template>

        <div class="max-w-3xl">
            <form
                @submit.prevent="submit"
                class="space-y-5 rounded border border-slate-300 bg-white p-5 sm:p-6"
            >
                <div>
                    <label
                        for="setting-school-name"
                        class="block text-sm font-semibold text-slate-900"
                    >
                        Nama Sekolah (Wajib)
                    </label>
                    <input
                        id="setting-school-name"
                        v-model="form.school_name"
                        type="text"
                        required
                        maxlength="150"
                        class="mt-1 min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700"
                    />
                    <p v-if="form.errors.school_name" class="mt-1 text-xs font-semibold text-red-700">
                        {{ form.errors.school_name }}
                    </p>
                </div>

                <div>
                    <label
                        for="setting-school-address"
                        class="block text-sm font-semibold text-slate-900"
                    >
                        Alamat Lengkap Sekolah
                    </label>
                    <input
                        id="setting-school-address"
                        v-model="form.school_address"
                        type="text"
                        maxlength="255"
                        placeholder="Contoh: Jl. Pendidikan No. 1, Kecamatan, Kota"
                        class="mt-1 min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700"
                    />
                    <p v-if="form.errors.school_address" class="mt-1 text-xs font-semibold text-red-700">
                        {{ form.errors.school_address }}
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label
                            for="setting-principal-name"
                            class="block text-sm font-semibold text-slate-900"
                        >
                            Nama Kepala Sekolah
                        </label>
                        <input
                            id="setting-principal-name"
                            v-model="form.principal_name"
                            type="text"
                            maxlength="150"
                            class="mt-1 min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700"
                        />
                        <p v-if="form.errors.principal_name" class="mt-1 text-xs font-semibold text-red-700">
                            {{ form.errors.principal_name }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="setting-principal-nip"
                            class="block text-sm font-semibold text-slate-900"
                        >
                            NIP Kepala Sekolah
                        </label>
                        <input
                            id="setting-principal-nip"
                            v-model="form.principal_nip"
                            type="text"
                            maxlength="40"
                            class="mt-1 min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700"
                        />
                        <p v-if="form.errors.principal_nip" class="mt-1 text-xs font-semibold text-red-700">
                            {{ form.errors.principal_nip }}
                        </p>
                    </div>
                </div>

                <div>
                    <label
                        for="setting-timezone"
                        class="block text-sm font-semibold text-slate-900"
                    >
                        Zona Waktu Operasional Sistem
                    </label>
                    <input
                        id="setting-timezone"
                        :value="`${setting.timezone} (Waktu Indonesia Barat - WIB)`"
                        type="text"
                        disabled
                        class="mt-1 min-h-[44px] w-full rounded border border-slate-400 bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-800"
                    />
                </div>

                <div class="space-y-3 border-t border-slate-300 pt-4">
                    <label
                        for="setting-logo"
                        class="block text-sm font-semibold text-slate-900"
                    >
                        Logo Sekolah (JPG / PNG maks. 2MB)
                    </label>

                    <div
                        v-if="setting.logo_url"
                        class="flex items-center gap-4 rounded border border-slate-300 bg-slate-50 p-3"
                    >
                        <img
                            :src="setting.logo_url"
                            :alt="setting.school_name"
                            class="h-16 w-16 rounded border border-slate-300 bg-white object-contain p-1"
                        />
                        <div class="flex items-center gap-2">
                            <input
                                id="setting-remove-logo"
                                v-model="form.remove_logo"
                                type="checkbox"
                                class="h-5 w-5 rounded border-slate-500 text-emerald-700 focus:ring-emerald-700"
                            />
                            <label
                                for="setting-remove-logo"
                                class="text-sm font-medium text-slate-900"
                            >
                                Hapus logo saat ini
                            </label>
                        </div>
                    </div>

                    <input
                        id="setting-logo"
                        type="file"
                        accept=".jpg,.jpeg,.png"
                        @change="handleLogoChange"
                        class="block min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900"
                    />
                    <p v-if="form.errors.logo" class="mt-1 text-xs font-semibold text-red-700">
                        {{ form.errors.logo }}
                    </p>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-slate-300 pt-4">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex min-h-[44px] items-center rounded bg-emerald-700 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700 disabled:opacity-60"
                    >
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Pengaturan Sekolah' }}
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>

