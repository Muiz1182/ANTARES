<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

const page = usePage();
const showingNavigationDropdown = ref(false);
const isNavigating = ref(false);

const user = computed(() => page.props.auth.user);
const ui = computed(() => page.props.ui);
const flash = computed(() => page.props.flash);
const schoolSetting = computed(() => page.props.schoolSetting);

interface NavItem {
    label: string;
    href: string;
    active: boolean;
    visible: boolean;
}

const navItems = computed<NavItem[]>(() => [
    {
        label: ui.value?.nav?.dashboard ?? 'Ringkasan',
        href: route('dashboard'),
        active: route().current('dashboard'),
        visible: true,
    },
    {
        label: ui.value?.nav?.scan_station ?? 'Pos Absensi',
        href: route('scan-station.index'),
        active: route().current('scan-station.*'),
        visible: Boolean(user.value?.can?.operate_scan),
    },
    {
        label: ui.value?.nav?.class_today ?? 'Kehadiran Kelas',
        href: route('attendances.today'),
        active: route().current('attendances.*'),
        visible: Boolean(user.value?.can?.view_attendances),
    },
    {
        label: ui.value?.nav?.reports ?? 'Laporan & Rekap',
        href: route('reports.index'),
        active: route().current('reports.*'),
        visible: Boolean(user.value?.can?.view_reports),
    },
    {
        label: ui.value?.nav?.cards ?? 'Kartu NFC',
        href: route('cards.index'),
        active: route().current('cards.*'),
        visible: Boolean(user.value?.can?.manage_cards),
    },
    {
        label: ui.value?.nav?.students ?? 'Data Siswa',
        href: route('students.index'),
        active: route().current('students.*'),
        visible: Boolean(user.value?.can?.view_students),
    },
    {
        label: ui.value?.nav?.classrooms ?? 'Kelas',
        href: route('classrooms.index'),
        active: route().current('classrooms.*'),
        visible: Boolean(user.value?.can?.view_classrooms),
    },
    {
        label: ui.value?.nav?.attendance_rules ?? 'Aturan Jam',
        href: route('attendance-rules.index'),
        active: route().current('attendance-rules.*'),
        visible: Boolean(user.value?.can?.manage_rules),
    },
    {
        label: ui.value?.nav?.academic_years ?? 'Tahun Ajaran',
        href: route('academic-years.index'),
        active: route().current('academic-years.*'),
        visible: Boolean(user.value?.can?.view_academic_years),
    },
    {
        label: ui.value?.nav?.holidays ?? 'Hari Libur',
        href: route('holidays.index'),
        active: route().current('holidays.*'),
        visible: Boolean(user.value?.can?.view_holidays),
    },
    {
        label: ui.value?.nav?.devices ?? 'Perangkat Pos',
        href: route('devices.index'),
        active: route().current('devices.*'),
        visible: Boolean(user.value?.can?.manage_devices),
    },
    {
        label: ui.value?.nav?.audit_logs ?? 'Log Audit',
        href: route('audit-logs.index'),
        active: route().current('audit-logs.*'),
        visible: Boolean(user.value?.can?.view_audit_logs),
    },
    {
        label: ui.value?.nav?.users ?? 'Pengguna & Guru',
        href: route('users.index'),
        active: route().current('users.*'),
        visible: Boolean(user.value?.can?.manage_users),
    },
    {
        label: ui.value?.nav?.settings ?? 'Pengaturan Sekolah',
        href: route('settings.edit'),
        active: route().current('settings.*'),
        visible: Boolean(user.value?.can?.manage_settings),
    },
]);

const visibleNavItems = computed(() => navItems.value.filter((item) => item.visible));

let removeStartListener: (() => void) | null = null;
let removeFinishListener: (() => void) | null = null;

function handleKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape' && showingNavigationDropdown.value) {
        showingNavigationDropdown.value = false;
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
    removeStartListener = router.on('start', () => {
        isNavigating.value = true;
    });
    removeFinishListener = router.on('finish', () => {
        isNavigating.value = false;
        showingNavigationDropdown.value = false;
    });
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
    if (removeStartListener) removeStartListener();
    if (removeFinishListener) removeFinishListener();
});
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <header class="border-b border-slate-300 bg-white">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex min-h-[64px] items-center justify-between gap-4 py-2">
                    <div class="flex items-center gap-6">
                        <Link
                            :href="route('dashboard')"
                            class="inline-flex min-h-[44px] items-center gap-2 rounded px-2 text-base font-bold tracking-tight text-slate-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                        >
                            <img
                                v-if="schoolSetting?.logo_url"
                                :src="schoolSetting.logo_url"
                                :alt="schoolSetting.school_name"
                                class="h-8 w-8 rounded object-contain"
                            />
                            <span>{{ schoolSetting?.school_name ?? ui?.app_name ?? '[LOGO] Sistem Absensi NFC SMP' }}</span>
                        </Link>

                        <nav
                            aria-label="Navigasi Utama"
                            class="hidden items-center gap-2 lg:flex"
                        >
                            <Link
                                v-for="item in visibleNavItems"
                                :key="item.href"
                                :href="item.href"
                                class="inline-flex min-h-[44px] items-center rounded px-3 py-2 text-sm font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                                :class="
                                    item.active
                                        ? 'bg-emerald-700 text-white'
                                        : 'text-slate-800 hover:bg-slate-100'
                                "
                            >
                                {{ item.label }}
                            </Link>
                        </nav>
                    </div>

                    <div class="hidden items-center gap-3 lg:flex">
                        <div class="text-right">
                            <p class="text-sm font-semibold text-slate-900">
                                {{ user?.name }}
                            </p>
                            <p class="text-xs font-medium text-slate-700">
                                {{ user?.role_labels?.join(', ') || user?.email }}
                            </p>
                        </div>

                        <Link
                            :href="route('profile.edit')"
                            class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-3 py-2 text-sm font-medium text-slate-800 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                        >
                            {{ ui?.nav?.profile ?? 'Profil Akun' }}
                        </Link>

                        <Link
                            :href="route('logout')"
                            method="post"
                            as="button"
                            class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-3 py-2 text-sm font-medium text-slate-800 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                        >
                            {{ ui?.nav?.logout ?? 'Keluar' }}
                        </Link>
                    </div>

                    <div class="flex items-center lg:hidden">
                        <button
                            type="button"
                            :aria-expanded="showingNavigationDropdown"
                            @click="showingNavigationDropdown = !showingNavigationDropdown"
                            class="inline-flex min-h-[44px] items-center gap-2 rounded border border-slate-500 bg-white px-3 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                        >
                            <span>{{ ui?.nav?.menu ?? 'Menu Navigasi' }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <div
                v-if="showingNavigationDropdown"
                class="border-t border-slate-300 bg-white px-4 py-3 lg:hidden"
            >
                <nav aria-label="Navigasi Seluler" class="flex flex-col gap-2">
                    <Link
                        v-for="item in visibleNavItems"
                        :key="item.href"
                        :href="item.href"
                        class="inline-flex min-h-[44px] items-center rounded px-3 py-2 text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                        :class="
                            item.active
                                ? 'bg-emerald-700 text-white'
                                : 'text-slate-800 hover:bg-slate-100'
                        "
                    >
                        {{ item.label }}
                    </Link>
                </nav>

                <div class="mt-4 border-t border-slate-300 pt-3">
                    <p class="text-sm font-semibold text-slate-900">{{ user?.name }}</p>
                    <p class="text-xs text-slate-700">
                        {{ user?.role_labels?.join(', ') }} ({{ user?.email }})
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <Link
                            :href="route('profile.edit')"
                            class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-3 py-2 text-sm font-medium text-slate-800 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                        >
                            {{ ui?.nav?.profile ?? 'Profil Akun' }}
                        </Link>
                        <Link
                            :href="route('logout')"
                            method="post"
                            as="button"
                            class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-3 py-2 text-sm font-medium text-slate-800 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                        >
                            {{ ui?.nav?.logout ?? 'Keluar' }}
                        </Link>
                    </div>
                </div>
            </div>
        </header>

        <div
            v-if="$slots.header"
            class="border-b border-slate-300 bg-white"
        >
            <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">
                <slot name="header" />
            </div>
        </div>

        <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <div
                v-if="isNavigating"
                role="status"
                aria-live="polite"
                class="mb-4 rounded border border-slate-400 bg-white px-4 py-3 text-sm font-medium text-slate-800"
            >
                {{ ui?.states?.loading ?? 'Memuat data, mohon tunggu...' }}
            </div>

            <div
                v-if="flash?.success"
                role="status"
                class="mb-4 rounded border border-emerald-800 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-950"
            >
                Berhasil: {{ flash.success }}
            </div>

            <div
                v-if="flash?.error"
                role="alert"
                class="mb-4 rounded border border-red-800 bg-red-50 px-4 py-3 text-sm font-semibold text-red-950"
            >
                Kesalahan: {{ flash.error }}
            </div>

            <slot />
        </main>

        <footer class="mt-12 border-t border-slate-300 bg-white py-4">
            <div class="mx-auto flex max-w-7xl flex-col justify-between gap-2 px-4 text-xs text-slate-700 sm:flex-row sm:px-6 lg:px-8">
                <span>{{ ui?.app_name ?? '[LOGO] Sistem Absensi NFC SMP' }}</span>
                <span>{{ ui?.draft_badge ?? 'Draft without direction (ENERGY 1 / RHYTHM 1 / MOTION 1)' }}</span>
            </div>
        </footer>
    </div>
</template>
