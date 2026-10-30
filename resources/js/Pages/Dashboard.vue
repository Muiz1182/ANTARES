<script setup lang="ts">
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import type { AcademicYearItem } from '@/types/models';

defineProps<{
    activeAcademicYear: AcademicYearItem | null;
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head title="Ringkasan" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-xl font-bold text-slate-900">
                        Ringkasan Data Master Sekolah
                    </h1>
                    <p class="text-sm text-slate-700">
                        Masuk sebagai {{ user.name }} ({{ user.role_labels.join(', ') }})
                    </p>
                </div>
                <div class="rounded border border-slate-400 bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-900">
                    Tahun Ajaran Aktif:
                    <span class="text-emerald-800">
                        {{ activeAcademicYear ? activeAcademicYear.name : 'Belum diatur' }}
                    </span>
                </div>
            </div>
        </template>

        <section aria-labelledby="quick-access-heading" class="space-y-4">
            <h2 id="quick-access-heading" class="text-base font-bold text-slate-900">
                Menu Pengelolaan Sesuai Peran Anda
            </h2>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-if="user.can.operate_scan"
                    class="flex flex-col justify-between rounded border border-slate-300 bg-white p-5"
                >
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Pos Absensi NFC (Scan Station)</h3>
                        <p class="mt-1 text-sm text-slate-700">
                            Layar penuh pemindaian kartu NFC siswa waktu nyata dengan umpan balik suara dan daftar 10 tap terakhir.
                        </p>
                    </div>
                    <div class="mt-4">
                        <Link
                            :href="route('scan-station.index')"
                            class="inline-flex min-h-[44px] items-center rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                        >
                            Buka Pos Absensi
                        </Link>
                    </div>
                </div>

                <div
                    v-if="user.can.manage_cards"
                    class="flex flex-col justify-between rounded border border-slate-300 bg-white p-5"
                >
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Registrasi &amp; Manajemen Kartu NFC</h3>
                        <p class="mt-1 text-sm text-slate-700">
                            Pasangkan kartu NFC ke siswa, ganti kartu hilang atau rusak, serta lakukan registrasi massal beruntun per kelas.
                        </p>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <Link
                            :href="route('cards.index')"
                            class="inline-flex min-h-[44px] items-center rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                        >
                            Kelola Kartu NFC
                        </Link>
                        <Link
                            :href="route('cards.bulk')"
                            class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                        >
                            Registrasi Massal
                        </Link>
                    </div>
                </div>

                <div
                    v-if="user.can.view_students"
                    class="flex flex-col justify-between rounded border border-slate-300 bg-white p-5"
                >
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Data Siswa</h3>
                        <p class="mt-1 text-sm text-slate-700">
                            Lihat daftar siswa, cari NIS/NISN, kelola penempatan kelas, dan impor data siswa dari Excel atau CSV.
                        </p>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <Link
                            :href="route('students.index')"
                            class="inline-flex min-h-[44px] items-center rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                        >
                            Buka Data Siswa
                        </Link>
                        <Link
                            v-if="user.can.manage_master"
                            :href="route('students.import.show')"
                            class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                        >
                            Impor Excel / CSV
                        </Link>
                    </div>
                </div>

                <div
                    v-if="user.can.view_classrooms"
                    class="flex flex-col justify-between rounded border border-slate-300 bg-white p-5"
                >
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Kelas &amp; Wali Kelas</h3>
                        <p class="mt-1 text-sm text-slate-700">
                            Kelola daftar rombongan belajar tingkat 7, 8, dan 9 beserta penugasan guru wali kelas.
                        </p>
                    </div>
                    <div class="mt-4">
                        <Link
                            :href="route('classrooms.index')"
                            class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                        >
                            Buka Daftar Kelas
                        </Link>
                    </div>
                </div>

                <div
                    v-if="user.can.view_academic_years"
                    class="flex flex-col justify-between rounded border border-slate-300 bg-white p-5"
                >
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Tahun Ajaran &amp; Hari Libur</h3>
                        <p class="mt-1 text-sm text-slate-700">
                            Atur periode tahun ajaran aktif dan kalender hari libur sekolah.
                        </p>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <Link
                            :href="route('academic-years.index')"
                            class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                        >
                            Tahun Ajaran
                        </Link>
                        <Link
                            :href="route('holidays.index')"
                            class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                        >
                            Hari Libur
                        </Link>
                    </div>
                </div>

                <div
                    v-if="user.can.manage_devices"
                    class="flex flex-col justify-between rounded border border-slate-300 bg-white p-5"
                >
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Perangkat Pos Absensi</h3>
                        <p class="mt-1 text-sm text-slate-700">
                            Kelola terminal pos pemindaian, terbitkan token autentikasi perangkat, atau nonaktifkan akses pos.
                        </p>
                    </div>
                    <div class="mt-4">
                        <Link
                            :href="route('devices.index')"
                            class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                        >
                            Kelola Perangkat Pos
                        </Link>
                    </div>
                </div>

                <div
                    v-if="user.can.manage_users"
                    class="flex flex-col justify-between rounded border border-slate-300 bg-white p-5"
                >
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Pengguna &amp; Peran</h3>
                        <p class="mt-1 text-sm text-slate-700">
                            Kelola akun Super Admin, Tata Usaha, Petugas Piket, Wali Kelas, dan Kepala Sekolah.
                        </p>
                    </div>
                    <div class="mt-4">
                        <Link
                            :href="route('users.index')"
                            class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                        >
                            Kelola Pengguna
                        </Link>
                    </div>
                </div>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
