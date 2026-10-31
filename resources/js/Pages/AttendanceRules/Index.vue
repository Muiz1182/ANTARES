<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import type { AttendanceRuleItem } from '@/types/models';

const props = defineProps<{
    rules: AttendanceRuleItem[];
}>();

const form = useForm({
    rules: props.rules.map((rule) => ({
        day_of_week: rule.day_of_week,
        day_label: rule.day_label,
        is_school_day: rule.is_school_day,
        check_in_start: rule.check_in_start,
        late_after: rule.late_after,
        check_out_start: rule.check_out_start,
        absent_cutoff: rule.absent_cutoff,
        debounce_seconds: rule.debounce_seconds,
    })),
});

function submit(): void {
    form.put(route('attendance-rules.update'), {
        preserveScroll: true,
    });
}

function fieldError(index: number, field: string): string | undefined {
    const errors = form.errors as Record<string, string | undefined>;
    return errors[`rules.${index}.${field}`];
}
</script>

<template>
    <Head title="Aturan Jam Absensi" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-xl font-bold text-slate-900">
                        Aturan Jam Absensi Harian (Senin - Minggu)
                    </h1>
                    <p class="text-sm text-slate-700">
                        Konfigurasikan jam mulai masuk, batas terlambat, jam mulai pulang, batas alfa otomatis, dan jeda anti-dobel per hari.
                    </p>
                </div>
            </div>
        </template>

        <form @submit.prevent="submit" class="space-y-6">
            <div class="rounded border border-slate-300 bg-white">
                <div class="border-b border-slate-300 px-4 py-3 sm:px-6">
                    <h2 class="text-base font-bold text-slate-900">
                        Jadwal Operasional Per Hari
                    </h2>
                    <p class="mt-0.5 text-xs text-slate-700">
                        Urutan waktu wajib: Jam Mulai Masuk &lt; Batas Terlambat &lt; Jam Mulai Pulang, serta Batas Terlambat &le; Batas Alfa Otomatis.
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-300 text-left text-sm">
                        <thead class="bg-slate-100 text-xs font-bold uppercase text-slate-800">
                            <tr>
                                <th scope="col" class="px-4 py-3">Hari</th>
                                <th scope="col" class="px-4 py-3">Hari Sekolah</th>
                                <th scope="col" class="px-4 py-3">Mulai Masuk</th>
                                <th scope="col" class="px-4 py-3">Batas Terlambat</th>
                                <th scope="col" class="px-4 py-3">Batas Alfa</th>
                                <th scope="col" class="px-4 py-3">Mulai Pulang</th>
                                <th scope="col" class="px-4 py-3">Jeda Tap (Detik)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            <tr
                                v-for="(item, idx) in form.rules"
                                :key="item.day_of_week"
                                :class="item.is_school_day ? 'bg-white' : 'bg-slate-50'"
                            >
                                <td class="whitespace-nowrap px-4 py-3 font-bold text-slate-900">
                                    {{ item.day_label }}
                                </td>

                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <input
                                            :id="`rule-school-day-${item.day_of_week}`"
                                            v-model="item.is_school_day"
                                            type="checkbox"
                                            class="h-5 w-5 rounded border-slate-500 text-emerald-700 focus:ring-emerald-700"
                                        />
                                        <label
                                            :for="`rule-school-day-${item.day_of_week}`"
                                            class="text-sm font-medium text-slate-800"
                                        >
                                            {{ item.is_school_day ? 'Aktif' : 'Libur' }}
                                        </label>
                                    </div>
                                </td>

                                <td class="px-4 py-3 align-top">
                                    <label :for="`rule-in-${item.day_of_week}`" class="sr-only">
                                        Mulai Masuk {{ item.day_label }}
                                    </label>
                                    <input
                                        :id="`rule-in-${item.day_of_week}`"
                                        v-model="item.check_in_start"
                                        type="time"
                                        required
                                        class="min-h-[44px] w-32 rounded border border-slate-500 bg-white px-2.5 py-1.5 text-sm font-medium text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700"
                                    />
                                    <p
                                        v-if="fieldError(idx, 'check_in_start')"
                                        class="mt-1 text-xs font-semibold text-red-700"
                                    >
                                        {{ fieldError(idx, 'check_in_start') }}
                                    </p>
                                </td>

                                <td class="px-4 py-3 align-top">
                                    <label :for="`rule-late-${item.day_of_week}`" class="sr-only">
                                        Batas Terlambat {{ item.day_label }}
                                    </label>
                                    <input
                                        :id="`rule-late-${item.day_of_week}`"
                                        v-model="item.late_after"
                                        type="time"
                                        required
                                        class="min-h-[44px] w-32 rounded border border-slate-500 bg-white px-2.5 py-1.5 text-sm font-medium text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700"
                                    />
                                    <p
                                        v-if="fieldError(idx, 'late_after')"
                                        class="mt-1 text-xs font-semibold text-red-700"
                                    >
                                        {{ fieldError(idx, 'late_after') }}
                                    </p>
                                </td>

                                <td class="px-4 py-3 align-top">
                                    <label :for="`rule-absent-${item.day_of_week}`" class="sr-only">
                                        Batas Alfa {{ item.day_label }}
                                    </label>
                                    <input
                                        :id="`rule-absent-${item.day_of_week}`"
                                        v-model="item.absent_cutoff"
                                        type="time"
                                        required
                                        class="min-h-[44px] w-32 rounded border border-slate-500 bg-white px-2.5 py-1.5 text-sm font-medium text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700"
                                    />
                                    <p
                                        v-if="fieldError(idx, 'absent_cutoff')"
                                        class="mt-1 text-xs font-semibold text-red-700"
                                    >
                                        {{ fieldError(idx, 'absent_cutoff') }}
                                    </p>
                                </td>

                                <td class="px-4 py-3 align-top">
                                    <label :for="`rule-out-${item.day_of_week}`" class="sr-only">
                                        Mulai Pulang {{ item.day_label }}
                                    </label>
                                    <input
                                        :id="`rule-out-${item.day_of_week}`"
                                        v-model="item.check_out_start"
                                        type="time"
                                        required
                                        class="min-h-[44px] w-32 rounded border border-slate-500 bg-white px-2.5 py-1.5 text-sm font-medium text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700"
                                    />
                                    <p
                                        v-if="fieldError(idx, 'check_out_start')"
                                        class="mt-1 text-xs font-semibold text-red-700"
                                    >
                                        {{ fieldError(idx, 'check_out_start') }}
                                    </p>
                                </td>

                                <td class="px-4 py-3 align-top">
                                    <label :for="`rule-debounce-${item.day_of_week}`" class="sr-only">
                                        Jeda Tap {{ item.day_label }}
                                    </label>
                                    <input
                                        :id="`rule-debounce-${item.day_of_week}`"
                                        v-model.number="item.debounce_seconds"
                                        type="number"
                                        min="1"
                                        max="300"
                                        required
                                        class="min-h-[44px] w-24 rounded border border-slate-500 bg-white px-2.5 py-1.5 text-sm font-medium text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700"
                                    />
                                    <p
                                        v-if="fieldError(idx, 'debounce_seconds')"
                                        class="mt-1 text-xs font-semibold text-red-700"
                                    >
                                        {{ fieldError(idx, 'debounce_seconds') }}
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-slate-300 bg-slate-50 px-4 py-3 sm:px-6">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex min-h-[44px] items-center rounded bg-emerald-700 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700 disabled:opacity-60"
                    >
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Aturan Jam' }}
                    </button>
                </div>
            </div>
        </form>
    </AuthenticatedLayout>
</template>

