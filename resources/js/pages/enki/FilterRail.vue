<script setup lang="ts">
import type { EnkiCategory } from '@/types/enki';

defineProps<{
    categories: EnkiCategory[];
    counts: Record<string, number>;
    category: string;
    sort: string;
    showStarred: boolean;
    showMySkills: boolean;
    source: string;
}>();

const emit = defineEmits<{
    'update:category': [value: string];
    'update:sort': [value: string];
    'update:showStarred': [value: boolean];
    'update:showMySkills': [value: boolean];
    'update:source': [value: string];
}>();

const sortOptions = [
    { id: 'recent', label: 'Recently updated' },
    { id: 'name', label: 'Name (A–Z)' },
    { id: 'stars', label: 'Most starred' },
];

const sourceOptions = [
    { id: 'all', label: 'All' },
    { id: 'internal', label: 'Internal' },
    { id: 'external', label: 'External' },
];
</script>

<template>
    <aside class="enki-rail">
        <div class="enki-rail-section">
            <div class="enki-rail-label">Categories</div>
            <ul class="enki-cats">
                <li v-for="c in categories" :key="c.id">
                    <button
                        type="button"
                        :class="[
                            'enki-cat',
                            { 'is-active': category === c.id },
                        ]"
                        @click="emit('update:category', c.id)"
                    >
                        <span>{{ c.label }}</span>
                        <span class="enki-cat-count">{{
                            counts[c.id] ?? 0
                        }}</span>
                    </button>
                </li>
            </ul>
        </div>

        <div class="enki-rail-section">
            <div class="enki-rail-label">View</div>
            <label class="enki-check">
                <input
                    type="checkbox"
                    :checked="showStarred"
                    @change="
                        emit(
                            'update:showStarred',
                            ($event.target as HTMLInputElement).checked,
                        )
                    "
                />
                <span class="enki-check-box">
                    <svg
                        width="10"
                        height="10"
                        viewBox="0 0 16 16"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.6"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M3.5 8.5l2.8 2.8L12.8 4.8" />
                    </svg>
                </span>
                <span>Starred only</span>
            </label>
            <label class="enki-check">
                <input
                    type="checkbox"
                    :checked="showMySkills"
                    @change="
                        emit(
                            'update:showMySkills',
                            ($event.target as HTMLInputElement).checked,
                        )
                    "
                />
                <span class="enki-check-box">
                    <svg
                        width="10"
                        height="10"
                        viewBox="0 0 16 16"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.6"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M3.5 8.5l2.8 2.8L12.8 4.8" />
                    </svg>
                </span>
                <span>My skills</span>
            </label>
            <div class="enki-rail-label" style="margin-top: 8px">Source</div>
            <div class="enki-sort">
                <button
                    v-for="o in sourceOptions"
                    :key="o.id"
                    type="button"
                    :class="['enki-sortbtn', { 'is-active': source === o.id }]"
                    @click="emit('update:source', o.id)"
                >
                    {{ o.label }}
                </button>
            </div>
        </div>

        <div class="enki-rail-section">
            <div class="enki-rail-label">Sort by</div>
            <div class="enki-sort">
                <button
                    v-for="o in sortOptions"
                    :key="o.id"
                    type="button"
                    :class="['enki-sortbtn', { 'is-active': sort === o.id }]"
                    @click="emit('update:sort', o.id)"
                >
                    {{ o.label }}
                </button>
            </div>
        </div>

        <div class="enki-rail-foot">
            <div class="enki-rail-meta">487 skills · 62 authors</div>
            <div class="enki-rail-meta enki-rail-meta--mono">
                v2026.04 · stable
            </div>
        </div>
    </aside>
</template>
