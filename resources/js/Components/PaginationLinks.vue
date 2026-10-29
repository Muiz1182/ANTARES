<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { PaginationLinkItem } from '@/types/models';

defineProps<{
    links: PaginationLinkItem[];
    from: number | null;
    to: number | null;
    total: number;
}>();

function decodeLabel(label: string): string {
    return label
        .replace(/&laquo;/g, '«')
        .replace(/&raquo;/g, '»')
        .trim();
}
</script>

<template>
    <nav
        v-if="total > 0"
        aria-label="Navigasi halaman"
        class="mt-6 flex flex-col items-start justify-between gap-4 border-t border-slate-300 pt-4 sm:flex-row sm:items-center"
    >
        <p class="text-sm text-slate-700">
            Menampilkan <span class="font-semibold text-slate-900">{{ from ?? 0 }}</span> sampai
            <span class="font-semibold text-slate-900">{{ to ?? 0 }}</span> dari
            <span class="font-semibold text-slate-900">{{ total }}</span> data
        </p>

        <div v-if="links.length > 3" class="flex flex-wrap items-center gap-2">
            <template v-for="(link, idx) in links" :key="idx">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    preserve-scroll
                    class="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded border px-3 py-2 text-sm font-medium transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                    :class="
                        link.active
                            ? 'border-emerald-800 bg-emerald-700 text-white'
                            : 'border-slate-500 bg-white text-slate-800 hover:bg-slate-100'
                    "
                >
                    {{ decodeLabel(link.label) }}
                </Link>
                <span
                    v-else
                    class="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded border border-slate-300 bg-slate-100 px-3 py-2 text-sm text-slate-600"
                >
                    {{ decodeLabel(link.label) }}
                </span>
            </template>
        </div>
    </nav>
</template>

