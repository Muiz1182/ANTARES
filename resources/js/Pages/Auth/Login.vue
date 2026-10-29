<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{
    canResetPassword?: boolean;
    status?: string;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit(): void {
    form.post(route('login'), {
        onFinish: () => {
            form.reset('password');
        },
    });
}
</script>

<template>
    <div class="flex min-h-screen flex-col justify-center bg-slate-50 px-4 py-8 text-slate-900 sm:px-6 lg:px-8">
        <Head title="Masuk Sistem" />

        <div class="mx-auto w-full max-w-md rounded border border-slate-300 bg-white p-6 sm:p-8">
            <div class="mb-6 border-b border-slate-200 pb-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-700">
                    [LOGO] Sistem Absensi NFC SMP
                </p>
                <h1 class="mt-1 text-xl font-bold text-slate-900">
                    Masuk ke Akun Sekolah
                </h1>
                <p class="mt-1 text-sm text-slate-700">
                    Gunakan email dan kata sandi akun staf atau guru yang terdaftar.
                </p>
            </div>

            <div
                v-if="status"
                role="status"
                class="mb-4 rounded border border-emerald-800 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-950"
            >
                {{ status }}
            </div>

            <div
                v-if="form.hasErrors"
                role="alert"
                class="mb-4 rounded border border-red-800 bg-red-50 px-3 py-2 text-sm font-medium text-red-950"
            >
                Periksa kembali email dan kata sandi Anda.
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label for="email" class="block text-sm font-semibold text-slate-900">
                        Alamat Email
                    </label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="nama@sekolah.sch.id"
                        class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700"
                    />
                    <p v-if="form.errors.email" class="mt-1 text-sm font-medium text-red-800">
                        {{ form.errors.email }}
                    </p>
                </div>

                <div>
                    <label for="password" class="block text-sm font-semibold text-slate-900">
                        Kata Sandi
                    </label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="mt-1 block min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700"
                    />
                    <p v-if="form.errors.password" class="mt-1 text-sm font-medium text-red-800">
                        {{ form.errors.password }}
                    </p>
                </div>

                <div class="flex items-center justify-between gap-2">
                    <label for="remember" class="inline-flex min-h-[44px] items-center gap-2 text-sm text-slate-800">
                        <input
                            id="remember"
                            v-model="form.remember"
                            type="checkbox"
                            class="h-5 w-5 rounded border-slate-500 text-emerald-700 focus:ring-emerald-700"
                        />
                        <span>Ingat perangkat ini</span>
                    </label>

                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="inline-flex min-h-[44px] items-center text-sm font-medium text-emerald-800 underline hover:text-emerald-950 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                    >
                        Lupa kata sandi?
                    </Link>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex min-h-[44px] w-full items-center justify-center rounded bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700 disabled:opacity-60"
                >
                    {{ form.processing ? 'Memproses...' : 'Masuk ke Sistem' }}
                </button>
            </form>

            <p class="mt-6 border-t border-slate-200 pt-3 text-xs text-slate-700">
                Draft without direction (ENERGY 1 / RHYTHM 1 / MOTION 1)
            </p>
        </div>
    </div>
</template>
