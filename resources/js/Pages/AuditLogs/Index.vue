<script setup lang="ts">
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PaginationLinks from '@/Components/PaginationLinks.vue';
import { Head, router } from '@inertiajs/vue3';
import type {
    AuditActorSummary,
    AuditLogItem,
    PaginatedData,
    SelectOptionItem,
} from '@/types/models';

const props = defineProps<{
    logs: PaginatedData<AuditLogItem>;
    actors: AuditActorSummary[];
    actionOptions: SelectOptionItem[];
    filters: {
        user_id: number | null;
        action: string;
        date_from: string;
        date_to: string;
    };
}>();

const filterUserId = ref<number | ''>(props.filters.user_id ?? '');
const filterAction = ref<string>(props.filters.action ?? '');
const filterDateFrom = ref<string>(props.filters.date_from ?? '');
const filterDateTo = ref<string>(props.filters.date_to ?? '');

function applyFilters(): void {
    router.get(
        route('audit-logs.index'),
        {
            user_id: filterUserId.value || undefined,
            action: filterAction.value || undefined,
            date_from: filterDateFrom.value || undefined,
            date_to: filterDateTo.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}

function resetFilters(): void {
    filterUserId.value = '';
    filterAction.value = '';
    filterDateFrom.value = '';
    filterDateTo.value = '';
    applyFilters();
}

function formatSnapshot(snapshot: Record<string, unknown> | null): string {
    if (!snapshot) {
        return '-';
    }

    const parts: string[] = [];
    if (snapshot.status) parts.push(`Status: ${String(snapshot.status)}`);
    if (snapshot.check_in_at) parts.push(`Masuk: ${String(snapshot.check_in_at)}`);
    if (snapshot.check_out_at) parts.push(`Pulang: ${String(snapshot.check_out_at)}`);
    if (snapshot.source) parts.push(`Sumber: ${String(snapshot.source)}`);
    if (snapshot.note) parts.push(`Catatan: ${String(snapshot.note)}`);

    return parts.length > 0 ? parts.join(' | ') : JSON.stringify(snapshot);
}
</script>

<template>
    <Head title="Log Audit Kehadiran" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-xl font-bold text-slate-900">
                        Log Audit Perubahan Kehadiran
                    </h1>
                    <p class="text-sm text-slate-700">
                        Jejak seluruh pencatatan izin/sakit/dispensasi, koreksi kehadiran manual, serta penimpaan alfa otomatis oleh tap NFC.
                    </p>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <section
                aria-label="Filter Log Audit"
                class="rounded border border-slate-300 bg-white p-4 sm:p-5"
            >
                <form
                    @submit.prevent="applyFilters"
                    class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5"
                >
                    <div>
                        <label
                            for="audit-filter-user"
                            class="block text-sm font-semibold text-slate-900"
                        >
                            Petugas / Pengguna
                        </label>
                        <select
                            id="audit-filter-user"
                            v-model="filterUserId"
                            class="mt-1 min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700"
                        >
                            <option value="">Semua Petugas &amp; Sistem</option>
                            <option
                                v-for="actor in actors"
                                :key="actor.id"
                                :value="actor.id"
                            >
                                {{ actor.name }} ({{ actor.email }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <label
                            for="audit-filter-action"
                            class="block text-sm font-semibold text-slate-900"
                        >
                            Jenis Tindakan
                        </label>
                        <select
                            id="audit-filter-action"
                            v-model="filterAction"
                            class="mt-1 min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700"
                        >
                            <option value="">Semua Tindakan</option>
                            <option
                                v-for="opt in actionOptions"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label
                            for="audit-filter-from"
                            class="block text-sm font-semibold text-slate-900"
                        >
                            Dari Tanggal
                        </label>
                        <input
                            id="audit-filter-from"
                            v-model="filterDateFrom"
                            type="date"
                            class="mt-1 min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700"
                        />
                    </div>

                    <div>
                        <label
                            for="audit-filter-to"
                            class="block text-sm font-semibold text-slate-900"
                        >
                            Sampai Tanggal
                        </label>
                        <input
                            id="audit-filter-to"
                            v-model="filterDateTo"
                            type="date"
                            class="mt-1 min-h-[44px] w-full rounded border border-slate-500 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700"
                        />
                    </div>

                    <div class="flex items-end gap-2">
                        <button
                            type="submit"
                            class="inline-flex min-h-[44px] items-center rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                        >
                            Terapkan
                        </button>
                        <button
                            type="button"
                            @click="resetFilters"
                            class="inline-flex min-h-[44px] items-center rounded border border-slate-500 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                        >
                            Reset
                        </button>
                    </div>
                </form>
            </section>

            <section class="rounded border border-slate-300 bg-white p-4 sm:p-5">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-300 text-left text-sm">
                        <thead class="bg-slate-100 text-xs font-bold uppercase text-slate-800">
                            <tr>
                                <th scope="col" class="px-4 py-3">Waktu (WIB)</th>
                                <th scope="col" class="px-4 py-3">Petugas</th>
                                <th scope="col" class="px-4 py-3">Tindakan</th>
                                <th scope="col" class="px-4 py-3">Subjek</th>
                                <th scope="col" class="px-4 py-3">Alasan / Keterangan</th>
                                <th scope="col" class="px-4 py-3">Data Sebelum &amp; Sesudah</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            <tr v-if="logs.data.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-sm font-medium text-slate-700">
                                    Belum ada catatan perubahan atau koreksi kehadiran di log audit.
                                </td>
                            </tr>
                            <tr
                                v-for="log in logs.data"
                                :key="log.id"
                                class="align-top hover:bg-slate-50"
                            >
                                <td class="whitespace-nowrap px-4 py-3 font-mono text-xs font-semibold text-slate-900">
                                    {{ log.created_at }}
                                </td>
                                <td class="px-4 py-3">
                                    <div v-if="log.actor" class="font-bold text-slate-900">
                                        {{ log.actor.name }}
                                    </div>
                                    <div v-if="log.actor" class="text-xs text-slate-700">
                                        {{ log.actor.email }}
                                    </div>
                                    <span
                                        v-else
                                        class="inline-flex items-center rounded border border-slate-400 bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-800"
                                    >
                                        Sistem / Pos NFC
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-bold text-slate-900">{{ log.action_label }}</div>
                                    <div class="font-mono text-xs text-slate-700">{{ log.action }}</div>
                                </td>
                                <td class="px-4 py-3 font-semibold text-slate-900">
                                    {{ log.subject_summary }}
                                </td>
                                <td class="px-4 py-3 text-slate-800">
                                    {{ log.reason ?? '-' }}
                                </td>
                                <td class="px-4 py-3 text-xs">
                                    <div class="space-y-1.5">
                                        <div class="rounded border border-slate-300 bg-slate-50 px-2.5 py-1.5 text-slate-800">
                                            <span class="font-bold text-slate-900">Sebelum:</span>
                                            {{ formatSnapshot(log.before) }}
                                        </div>
                                        <div class="rounded border border-emerald-800 bg-emerald-50 px-2.5 py-1.5 text-emerald-950">
                                            <span class="font-bold">Sesudah:</span>
                                            {{ formatSnapshot(log.after) }}
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <PaginationLinks
                    :links="logs.links"
                    :from="logs.from"
                    :to="logs.to"
                    :total="logs.total"
                />
            </section>
        </div>
    </AuthenticatedLayout>
</template>

