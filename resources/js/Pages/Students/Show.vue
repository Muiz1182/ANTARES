<script setup lang="ts">
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import type { StudentItem } from '@/types/models';

defineProps<{
    student: StudentItem;
}>();

const page = usePage();
const canManage = computed(() => page.props.auth.user.can.manage_master);

function initials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}
</script>

<template>
    <Head :title="`Detail Siswa: ${student.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-xl font-bold text-slate-900">{{ student.name }}</h1>
                    <p class="text-sm text-slate-700">
                        NIS: {{ student.nis }} | Kelas: {{ student.classroom_name || 'Belum ditempatkan' }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link
                        :href="route('students.index')"
                        class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                    >
                        Kembali ke Daftar
                    </Link>
                    <Link
                        v-if="canManage"
                        :href="route('students.edit', student.id)"
                        class="inline-flex min-h-[44px] items-center rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                    >
                        Ubah Data Siswa
                    </Link>
                </div>
            </div>
        </template>

        <section class="mx-auto max-w-3xl rounded border border-slate-300 bg-white p-6">
            <div class="flex flex-col items-start gap-6 sm:flex-row">
                <img
                    v-if="student.photo_url"
                    :src="student.photo_url"
                    :alt="`Foto ${student.name}`"
                    class="h-28 w-28 rounded border border-slate-400 object-cover"
                />
                <div
                    v-else
                    aria-hidden="true"
                    class="inline-flex h-28 w-28 items-center justify-center rounded border border-slate-400 bg-slate-200 text-2xl font-bold text-slate-900"
                >
                    {{ initials(student.name) }}
                </div>

                <dl class="grid flex-1 grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-bold uppercase text-slate-700">Nama Lengkap</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900">{{ student.name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-bold uppercase text-slate-700">Status</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900">
                            {{ student.is_active ? 'Aktif' : 'Nonaktif' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-bold uppercase text-slate-700">NIS</dt>
                        <dd class="mt-1 tabular-nums text-sm font-semibold text-slate-900">{{ student.nis }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-bold uppercase text-slate-700">NISN</dt>
                        <dd class="mt-1 tabular-nums text-sm text-slate-900">{{ student.nisn || '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-bold uppercase text-slate-700">Kelas</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900">
                            {{ student.classroom_name || 'Belum ditempatkan' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-bold uppercase text-slate-700">Jenis Kelamin</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ student.gender_label }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-bold uppercase text-slate-700">Nama Orang Tua / Wali</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ student.parent_name || '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-bold uppercase text-slate-700">Telepon Orang Tua / Wali</dt>
                        <dd class="mt-1 tabular-nums text-sm text-slate-900">{{ student.parent_phone || '-' }}</dd>
                    </div>
                </dl>
            </div>
        </section>
    </AuthenticatedLayout>
</template>

