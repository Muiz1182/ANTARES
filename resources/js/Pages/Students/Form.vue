<script setup lang="ts">
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import type { ClassroomItem, StudentItem } from '@/types/models';

const props = defineProps<{
    student: StudentItem | null;
    classrooms: ClassroomItem[];
}>();

const isEditing = computed(() => props.student !== null);

const form = useForm({
    _method: props.student ? 'put' : 'post',
    nis: props.student?.nis ?? '',
    nisn: props.student?.nisn ?? '',
    name: props.student?.name ?? '',
    gender: (props.student?.gender ?? 'L') as 'L' | 'P',
    classroom_id: props.student?.classroom_id ?? null,
    photo: null as File | null,
    parent_name: props.student?.parent_name ?? '',
    parent_phone: props.student?.parent_phone ?? '',
    is_active: props.student ? props.student.is_active : true,
});

function handlePhotoChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    form.photo = input.files?.[0] ?? null;
}

function submit(): void {
    if (props.student) {
        form.post(route('students.update', props.student.id), {
            forceFormData: true,
        });
    } else {
        form.post(route('students.store'), {
            forceFormData: true,
        });
    }
}
</script>

<template>
    <Head :title="isEditing ? 'Ubah Data Siswa' : 'Tambah Siswa Baru'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-xl font-bold text-slate-900">
                        {{ isEditing ? `Ubah Data Siswa: ${student?.name}` : 'Tambah Siswa Baru' }}
                    </h1>
                    <p class="text-sm text-slate-700">
                        Lengkapi identitas siswa, kelas aktif, foto, dan kontak orang tua/wali.
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

        <section class="mx-auto max-w-3xl rounded border border-slate-300 bg-white p-6">
            <div
                v-if="form.hasErrors"
                role="alert"
                class="mb-5 rounded border border-red-800 bg-red-50 p-3 text-sm font-semibold text-red-950"
            >
                Terdapat kesalahan pada isian formulir. Silakan periksa kolom yang ditandai di bawah.
            </div>

            <form @submit.prevent="submit" class="space-y-5">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="nis" class="block text-sm font-semibold text-slate-900">
                            Nomor Induk Siswa (NIS) *
                        </label>
                        <input
                            id="nis"
                            v-model="form.nis"
                            type="text"
                            required
                            placeholder="Contoh: 260001"
                            class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700"
                        />
                        <p v-if="form.errors.nis" class="mt-1 text-sm font-medium text-red-800">
                            {{ form.errors.nis }}
                        </p>
                    </div>

                    <div>
                        <label for="nisn" class="block text-sm font-semibold text-slate-900">
                            NISN (Opsional)
                        </label>
                        <input
                            id="nisn"
                            v-model="form.nisn"
                            type="text"
                            placeholder="Contoh: 0012345678"
                            class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700"
                        />
                        <p v-if="form.errors.nisn" class="mt-1 text-sm font-medium text-red-800">
                            {{ form.errors.nisn }}
                        </p>
                    </div>
                </div>

                <div>
                    <label for="student-name" class="block text-sm font-semibold text-slate-900">
                        Nama Lengkap Siswa *
                    </label>
                    <input
                        id="student-name"
                        v-model="form.name"
                        type="text"
                        required
                        placeholder="Masukkan nama lengkap sesuai ijazah/rapor"
                        class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700"
                    />
                    <p v-if="form.errors.name" class="mt-1 text-sm font-medium text-red-800">
                        {{ form.errors.name }}
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="gender" class="block text-sm font-semibold text-slate-900">
                            Jenis Kelamin *
                        </label>
                        <select
                            id="gender"
                            v-model="form.gender"
                            required
                            class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700"
                        >
                            <option value="L">Laki-laki (L)</option>
                            <option value="P">Perempuan (P)</option>
                        </select>
                        <p v-if="form.errors.gender" class="mt-1 text-sm font-medium text-red-800">
                            {{ form.errors.gender }}
                        </p>
                    </div>

                    <div>
                        <label for="classroom_id" class="block text-sm font-semibold text-slate-900">
                            Kelas
                        </label>
                        <select
                            id="classroom_id"
                            v-model="form.classroom_id"
                            class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700"
                        >
                            <option :value="null">Belum ditempatkan</option>
                            <option v-for="c in classrooms" :key="c.id" :value="c.id">
                                {{ c.name }}
                            </option>
                        </select>
                        <p v-if="form.errors.classroom_id" class="mt-1 text-sm font-medium text-red-800">
                            {{ form.errors.classroom_id }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="parent_name" class="block text-sm font-semibold text-slate-900">
                            Nama Orang Tua / Wali
                        </label>
                        <input
                            id="parent_name"
                            v-model="form.parent_name"
                            type="text"
                            placeholder="Nama orang tua atau wali siswa"
                            class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700"
                        />
                        <p v-if="form.errors.parent_name" class="mt-1 text-sm font-medium text-red-800">
                            {{ form.errors.parent_name }}
                        </p>
                    </div>

                    <div>
                        <label for="parent_phone" class="block text-sm font-semibold text-slate-900">
                            No. Telepon Orang Tua / Wali
                        </label>
                        <input
                            id="parent_phone"
                            v-model="form.parent_phone"
                            type="tel"
                            placeholder="Contoh: 081234567890"
                            class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700"
                        />
                        <p v-if="form.errors.parent_phone" class="mt-1 text-sm font-medium text-red-800">
                            {{ form.errors.parent_phone }}
                        </p>
                    </div>
                </div>

                <div>
                    <label for="photo" class="block text-sm font-semibold text-slate-900">
                        Foto Siswa (JPG, PNG, WebP maks. 2 MB)
                    </label>
                    <input
                        id="photo"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        @change="handlePhotoChange"
                        class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700"
                    />
                    <p v-if="form.errors.photo" class="mt-1 text-sm font-medium text-red-800">
                        {{ form.errors.photo }}
                    </p>
                </div>

                <div>
                    <label for="student-active" class="inline-flex min-h-[44px] items-center gap-2 text-sm font-semibold text-slate-900">
                        <input
                            id="student-active"
                            v-model="form.is_active"
                            type="checkbox"
                            class="h-5 w-5 rounded border-slate-500 text-emerald-700 focus:ring-emerald-700"
                        />
                        <span>Siswa berstatus aktif di sekolah</span>
                    </label>
                </div>

                <div class="flex flex-wrap justify-end gap-2 border-t border-slate-200 pt-4">
                    <Link
                        :href="route('students.index')"
                        class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                    >
                        Batal
                    </Link>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex min-h-[44px] items-center rounded bg-emerald-700 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700 disabled:opacity-60"
                    >
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Data Siswa' }}
                    </button>
                </div>
            </form>
        </section>
    </AuthenticatedLayout>
</template>

