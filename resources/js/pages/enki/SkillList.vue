<script setup lang="ts">
import { InfiniteScroll } from '@inertiajs/vue3';
import { onMounted, onUnmounted, useTemplateRef, watch } from 'vue';
import { formatTitle } from '@/lib/utils';
import type { EnkiSkillSummary, EnkiTint } from '@/types/enki';
import Monogram from './Monogram.vue';

const props = defineProps<{
    skills: EnkiSkillSummary[];
    total: number;
    selectedSlug: string;
    query: string;
    tints: EnkiTint[];
}>();

const emit = defineEmits<{
    'update:selectedSlug': [slug: string];
    toggleStar: [slug: string];
}>();

const listRef = useTemplateRef<HTMLDivElement>('listRef');

function onKey(e: KeyboardEvent) {
    const tag = (document.activeElement as HTMLElement).tagName;

    if (tag === 'INPUT' || tag === 'TEXTAREA') {
        return;
    }

    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        const idx = props.skills.findIndex(
            (s) => s.slug === props.selectedSlug,
        );
        const next =
            e.key === 'ArrowDown'
                ? Math.min(props.skills.length - 1, idx + 1)
                : Math.max(0, idx - 1);

        if (props.skills[next]) {
            emit('update:selectedSlug', props.skills[next].slug);
        }
    }
}

onMounted(() => window.addEventListener('keydown', onKey));
onUnmounted(() => window.removeEventListener('keydown', onKey));

watch(
    () => props.selectedSlug,
    (slug) => {
        const escaped = CSS.escape(slug);
        const el = listRef.value?.querySelector<HTMLElement>(
            `[data-slug="${escaped}"]`,
        );

        if (el && listRef.value) {
            const parent = listRef.value;
            const top = el.offsetTop;

            if (
                top < parent.scrollTop ||
                top + el.offsetHeight > parent.scrollTop + parent.clientHeight
            ) {
                parent.scrollTo({ top: top - 80, behavior: 'smooth' });
            }
        }
    },
);
</script>

<template>
    <section class="enki-list-col">
        <div class="enki-list-head">
            <span class="enki-list-count">
                {{ total }} {{ total === 1 ? 'result' : 'results' }}
                <em v-if="query"> · "{{ query }}"</em>
            </span>
            <span class="enki-list-hint">
                <kbd class="enki-kbd">↑</kbd><kbd class="enki-kbd">↓</kbd> to
                navigate
            </span>
        </div>

        <div class="enki-list" ref="listRef">
            <InfiniteScroll data="skills">
                <ul>
                    <li v-for="s in skills" :key="s.slug">
                        <div
                            role="button"
                            tabindex="0"
                            :data-slug="s.slug"
                            :class="[
                                'enki-item',
                                { 'is-active': selectedSlug === s.slug },
                            ]"
                            @click="emit('update:selectedSlug', s.slug)"
                            @keydown.enter.prevent="
                                emit('update:selectedSlug', s.slug)
                            "
                            @keydown.space.prevent="
                                emit('update:selectedSlug', s.slug)
                            "
                        >
                            <Monogram
                                :name="formatTitle(s.name)"
                                :tint="s.monogramTint"
                                :tints="tints"
                                :size="36"
                                :icon="s.categoryIcon"
                                :color="s.categoryColor"
                            />
                            <div class="enki-item-body">
                                <div class="enki-item-row1">
                                    <span class="enki-item-name">
                                        {{ formatTitle(s.name) }}
                                        <svg
                                            v-if="s.isPrivate"
                                            class="enki-item-lock"
                                            width="11"
                                            height="11"
                                            viewBox="0 0 16 16"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.5"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            aria-label="Private"
                                            title="Private"
                                        >
                                            <rect
                                                x="3"
                                                y="7"
                                                width="10"
                                                height="7"
                                                rx="1.5"
                                            />
                                            <path d="M5 7V5a3 3 0 0 1 6 0v2" />
                                        </svg>
                                    </span>
                                    <button
                                        v-if="s.canStar"
                                        type="button"
                                        :class="[
                                            'enki-star',
                                            { 'is-on': s.starred },
                                        ]"
                                        @click.stop="emit('toggleStar', s.slug)"
                                        :title="s.starred ? 'Unstar' : 'Star'"
                                    >
                                        <svg
                                            width="14"
                                            height="14"
                                            viewBox="0 0 16 16"
                                            aria-hidden="true"
                                        >
                                            <path
                                                d="M8 1.5l1.95 4.16 4.55.55-3.37 3.16.86 4.5L8 11.7l-4 2.17.86-4.5L1.5 6.21l4.55-.55L8 1.5z"
                                                :fill="
                                                    s.starred
                                                        ? 'currentColor'
                                                        : 'none'
                                                "
                                                stroke="currentColor"
                                                stroke-width="1.4"
                                                stroke-linejoin="round"
                                            />
                                        </svg>
                                    </button>
                                </div>
                                <div class="enki-item-summary">
                                    {{ s.summary }}
                                </div>
                                <div class="enki-item-meta">
                                    <span class="enki-item-slug">{{
                                        s.slug
                                    }}</span>
                                    <span class="enki-item-dot">·</span>
                                    <span>{{ s.updated }}</span>
                                    <template v-if="s.isExternal">
                                        <span class="enki-item-dot">·</span>
                                        <span class="enki-item-external-label"
                                            >External</span
                                        >
                                    </template>
                                    <template v-if="s.isPrivate">
                                        <span class="enki-item-dot">·</span>
                                        <span
                                            class="enki-item-private"
                                            title="Private skill"
                                        >
                                            <svg
                                                width="11"
                                                height="11"
                                                viewBox="0 0 16 16"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.5"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                aria-hidden="true"
                                            >
                                                <rect
                                                    x="3"
                                                    y="7"
                                                    width="10"
                                                    height="7"
                                                    rx="1.5"
                                                />
                                                <path
                                                    d="M5 7V5a3 3 0 0 1 6 0v2"
                                                />
                                            </svg>
                                        </span>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </li>
                </ul>
                <template #loading>
                    <div class="enki-loading-row">Loading more…</div>
                </template>
            </InfiniteScroll>

            <ul v-if="skills.length === 0">
                <li class="enki-empty">
                    <div class="enki-empty-mark">∅</div>
                    <div>No skills match those filters.</div>
                    <div class="enki-empty-sub">
                        Try clearing the search or category.
                    </div>
                </li>
            </ul>
        </div>
    </section>
</template>
