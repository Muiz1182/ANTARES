<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import type { StudentImportPreview } from '@/types/models';

const props = defineProps<{
    preview: StudentImportPreview | null;
}>();

const uploadForm = useForm({
    file: null as File | null,
});

const commitForm = useForm({
    import_token: props.preview?.import_token ?? '',
});

function handleFileChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    uploadForm.file = input.files?.[0] ?? null;
}

function submitPreview(): void {
    uploadForm.post(route('students.import.preview'), {
        forceFormData: true,
        onSuccess: (page) => {
            const nextPreview = page.props.preview as StudentImportPreview | null;
            commitForm.import_token = nextPreview?.import_token ?? '';
        },
    });
}

function submitCommit(): void {
    if (!props.preview) return;
    commitForm.import_token = props.preview.import_token;
    commitForm.post(route('students.import.commit'));
}
</script>

<template>
    <Head title="Impor Data Siswa" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-xl font-bold text-slate-900">Impor Data Siswa (Excel / CSV)</h1>
                    <p class="text-sm text-slate-700">
                        Unggah berkas Excel (.xlsx) atau CSV untuk memvalidasi baris sebelum disimpan ke database.
                    </p>
                </div>
                <Link
                    :href="route('students.index')"
                    class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                >
                    Kembali ke Daftar Siswa
                </Link>
            </div>
        </template>

        <div class="space-y-6">
            <section class="rounded border border-slate-300 bg-white p-6">
                <h2 class="text-base font-bold text-slate-900">1. Unduh Template &amp; Unggah Berkas</h2>
                <p class="mt-1 text-sm text-slate-700">
                    Pastikan kolom mengikuti urutan template: <code>nis</code>, <code>nisn</code>, <code>name</code>,
                    <code>gender</code> (L/P), <code>classroom</code> (nama kelas pada tahun ajaran aktif),
                    <code>parent_name</code>, <code>parent_phone</code>.
                </p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <a
                        :href="route('students.import.template', { format: 'xlsx' })"
                        class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                    >
                        Unduh Template Excel (.xlsx)
                    </a>
                    <a
                        :href="route('students.import.template', { format: 'csv' })"
                        class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                    >
                        Unduh Template CSV (.csv)
                    </a>
                </div>

                <form @submit.prevent="submitPreview" class="mt-6 space-y-4 border-t border-slate-200 pt-4">
                    <div>
                        <label for="import-file" class="block text-sm font-semibold text-slate-900">
                            Pilih Berkas Excel (.xlsx) atau CSV (.csv)
                        </label>
                        <input
                            id="import-file"
                            type="file"
                            required
                            accept=".xlsx,.csv,.txt"
                            @change="handleFileChange"
                            class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700"
                        />
                        <p v-if="uploadForm.errors.file" class="mt-1 text-sm font-medium text-red-800">
                            {{ uploadForm.errors.file }}
                        </p>
                    </div>

                    <button
                        type="submit"
                        :disabled="uploadForm.processing || !uploadForm.file"
                        class="inline-flex min-h-[44px] items-center rounded bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700 disabled:opacity-60"
                    >
                        {{ uploadForm.processing ? 'Memeriksa Berkas...' : 'Periksa & Pratinjau Berkas' }}
                    </button>
                </form>
            </section>

            <section v-if="preview" class="space-y-6 rounded border border-slate-300 bg-white p-6">
                <div class="flex flex-col justify-between gap-4 border-b border-slate-200 pb-4 sm:flex-row sm:items-center">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">2. Hasil Pemeriksaan Baris</h2>
                        <p class="mt-1 text-sm text-slate-700">
                            Total baris dibaca: <strong class="tabular-nums text-slate-900">{{ preview.total_rows }}</strong> |
                            Baris valid: <strong class="tabular-nums text-emerald-800">{{ preview.valid_count }}</strong> |
                            Baris bermasalah: <strong class="tabular-nums text-red-800">{{ preview.error_count }}</strong>
                        </p>
                    </div>

                    <form v-if="preview.valid_count > 0" @submit.prevent="submitCommit">
                        <button
                            type="submit"
                            :disabled="commitForm.processing"
                            class="inline-flex min-h-[44px] items-center rounded bg-emerald-700 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700 disabled:opacity-60"
                        >
                            {{
                                commitForm.processing
                                    ? 'Menyimpan Data Siswa...'
                                    : `Simpan ${preview.valid_count} Baris Valid`
                            }}
                        </button>
                    </form>
                </div>

                <div v-if="preview.errors.length > 0" class="space-y-3">
                    <h3 class="text-sm font-bold text-red-900">
                        Daftar Baris Bermasalah (Tidak Akan Diimpor)
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-300 text-left text-sm">
                            <thead class="bg-red-50 text-red-950">
                                <tr>
                                    <th scope="col" class="px-3 py-2 font-bold">Baris</th>
                                    <th scope="col" class="px-3 py-2 font-bold">NIS</th>
                                    <th scope="col" class="px-3 py-2 font-bold">Nama</th>
                                    <th scope="col" class="px-3 py-2 font-bold">Keterangan Kesalahan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                <tr v-for="err in preview.errors" :key="err.row">
                                    <td class="px-3 py-2 tabular-nums font-semibold text-slate-900">
                                        #{{ err.row }}
                                    </td>
                                    <td class="px-3 py-2 tabular-nums text-slate-800">
                                        {{ err.nis || '-' }}
                                    </td>
                                    <td class="px-3 py-2 text-slate-800">
                                        {{ err.name || '-' }}
                                    </td>
                                    <td class="px-3 py-2 font-medium text-red-900">
                                        {{ err.messages.join(' ') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="preview.valid_rows.length > 0" class="space-y-3">
                    <h3 class="text-sm font-bold text-slate-900">
                        Pratinjau Baris Valid (Menampilkan hingga 100 baris pertama)
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-300 text-left text-sm">
                            <thead class="bg-slate-100 text-slate-900">
                                <tr>
                                    <th scope="col" class="px-3 py-2 font-bold">Baris</th>
                                    <th scope="col" class="px-3 py-2 font-bold">NIS</th>
                                    <th scope="col" class="px-3 py-2 font-bold">NISN</th>
                                    <th scope="col" class="px-3 py-2 font-bold">Nama</th>
                                    <th scope="col" class="px-3 py-2 font-bold">L/P</th>
                                    <th scope="col" class="px-3 py-2 font-bold">Kelas</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                <tr v-for="row in preview.valid_rows" :key="row.row">
                                    <td class="px-3 py-2 tabular-nums text-slate-700">#{{ row.row }}</td>
                                    <td class="px-3 py-2 tabular-nums font-semibold text-slate-900">{{ row.nis }}</td>
                                    <td class="px-3 py-2 tabular-nums text-slate-800">{{ row.nisn || '-' }}</td>
                                    <td class="px-3 py-2 font-medium text-slate-900">{{ row.name }}</td>
                                    <td class="px-3 py-2 text-slate-800">{{ row.gender }}</td>
                                    <td class="px-3 py-2 font-semibold text-slate-900">{{ row.classroom_name }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>

