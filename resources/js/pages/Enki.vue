<script setup lang="ts">
import { router, useHttp } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { star as skillStar } from '@/routes/enki/skill';
import type {
    EnkiCategory,
    EnkiTint,
    EnkiAuthor,
    EnkiSkill,
    EnkiSkillSummary,
    EnkiFilters,
} from '@/types/enki';
import AuthorModal from './enki/AuthorModal.vue';
import DetailPane from './enki/DetailPane.vue';
import FilterRail from './enki/FilterRail.vue';
import SkillList from './enki/SkillList.vue';
import SubmitModal from './enki/SubmitModal.vue';
import TopBar from './enki/TopBar.vue';

const props = defineProps<{
    categories: EnkiCategory[];
    tints: EnkiTint[];
    authors: Record<string, EnkiAuthor>;
    skills: { data: EnkiSkillSummary[]; total: number };
    selectedSkill: EnkiSkill | null;
    filters: EnkiFilters;
    skillCounts: Record<string, number>;
}>();

const http = useHttp();

// ── Mobile state ──────────────────────────────────────────────────────────────
const mq =
    typeof window !== 'undefined'
        ? window.matchMedia('(max-width: 767px)')
        : null;
const isMobile = ref(mq?.matches ?? false);
const mobilePanel = ref<'list' | 'detail'>('list');
const filterDrawerOpen = ref(false);

onMounted(() => {
    if (mq) {
        const handler = (e: MediaQueryListEvent) => {
            isMobile.value = e.matches;

            if (!e.matches) {
                mobilePanel.value = 'list';
                filterDrawerOpen.value = false;
            }
        };
        mq.addEventListener('change', handler);
        onUnmounted(() => mq.removeEventListener('change', handler));
    }
});

// ── Filters / search ─────────────────────────────────────────────────────────
const query = ref(props.filters.q);
const category = ref(props.filters.category);
const sort = ref(props.filters.sort);
const showStarred = ref(props.filters.starred);
const showMySkills = ref(props.filters.mySkills);
const source = ref(props.filters.source);

// Sync when navigating back/forward
watch(
    () => props.filters,
    (f) => {
        query.value = f.q;
        category.value = f.category;
        sort.value = f.sort;
        showStarred.value = f.starred;
        showMySkills.value = f.mySkills;
        source.value = f.source;
    },
);

// ── Modal ────────────────────────────────────────────────────────────────────
type Modal = null | 'submit' | 'edit' | { kind: 'author'; id: string };
const modal = ref<Modal>(null);
const authorModal = computed(() =>
    modal.value &&
    typeof modal.value === 'object' &&
    modal.value.kind === 'author'
        ? (modal.value as { kind: 'author'; id: string })
        : null,
);

// ── Filter application ────────────────────────────────────────────────────────
let debounceTimer: ReturnType<typeof setTimeout>;

function applyFilters(immediate = false) {
    clearTimeout(debounceTimer);
    const run = () => {
        const params: Record<string, string | number | undefined> = {};

        if (query.value) {
            params.q = query.value;
        }

        if (category.value !== 'all') {
            params.category = category.value;
        }

        if (sort.value !== 'recent') {
            params.sort = sort.value;
        }

        if (showStarred.value) {
            params.starred = 1;
        }

        if (showMySkills.value) {
            params.mySkills = 1;
        }

        if (source.value !== 'all') {
            params.source = source.value;
        }

        router.get(window.location.pathname, params, {
            only: ['skills', 'skillCounts', 'filters'],
            reset: ['skills'],
            preserveState: true,
            replace: true,
        });
    };

    if (immediate) {
        run();
    } else {
        debounceTimer = setTimeout(run, 300);
    }
}

watch(query, () => applyFilters(false));
watch([category, sort, showStarred, showMySkills, source], () =>
    applyFilters(true),
);

// ── Skill selection ───────────────────────────────────────────────────────────
const selectedSlug = computed(
    () => props.selectedSkill?.slug ?? props.skills.data[0]?.slug ?? '',
);

function selectSkill(slug: string) {
    router.visit('/enki/skills/' + slug + window.location.search, {
        only: ['selectedSkill'],
        preserveState: true,
    });

    if (isMobile.value) {
        mobilePanel.value = 'detail';
    }
}

// ── Star toggling ─────────────────────────────────────────────────────────────
function toggleStar(slug: string) {
    const idx = props.skills.data.findIndex((s) => s.slug === slug);

    if (idx !== -1) {
        router.replaceProp('skills.data.' + idx, {
            ...props.skills.data[idx],
            starred: !props.skills.data[idx].starred,
        });
    }

    if (props.selectedSkill?.slug === slug) {
        router.replaceProp('selectedSkill', {
            ...props.selectedSkill,
            starred: !props.selectedSkill.starred,
        });
    }

    http.post(skillStar.url(slug));
}
</script>

<template>
    <TopBar
        :query="query"
        @update:query="query = $event"
        @submit="modal = 'submit'"
        @filterClick="filterDrawerOpen = true"
    />

    <!-- Mobile filter drawer (inside .enki-app so CSS vars are in scope) -->
    <Transition name="enki-drawer">
        <div v-if="filterDrawerOpen" class="enki-filter-drawer">
            <FilterRail
                :categories="categories"
                :counts="skillCounts"
                :category="category"
                :sort="sort"
                :showStarred="showStarred"
                :showMySkills="showMySkills"
                :source="source"
                @update:category="category = $event"
                @update:sort="sort = $event"
                @update:showStarred="showStarred = $event"
                @update:showMySkills="showMySkills = $event"
                @update:source="source = $event"
            />
        </div>
    </Transition>
    <div
        v-if="filterDrawerOpen"
        class="enki-filter-overlay"
        @click="filterDrawerOpen = false"
    />

    <div class="enki-shell">
        <FilterRail
            v-if="!isMobile"
            :categories="categories"
            :counts="skillCounts"
            :category="category"
            :sort="sort"
            :showStarred="showStarred"
            :showMySkills="showMySkills"
            :source="source"
            @update:category="category = $event"
            @update:sort="sort = $event"
            @update:showStarred="showStarred = $event"
            @update:showMySkills="showMySkills = $event"
            @update:source="source = $event"
        />

        <SkillList
            :class="{ 'enki-panel--hidden': isMobile && mobilePanel === 'detail' }"
            :skills="skills.data"
            :total="skills.total"
            :selectedSlug="selectedSlug"
            :query="query"
            :tints="tints"
            @update:selectedSlug="selectSkill($event)"
            @toggleStar="toggleStar"
        />

        <DetailPane
            v-if="selectedSkill"
            :class="{ 'enki-panel--hidden': isMobile && mobilePanel === 'list' }"
            :skill="selectedSkill"
            :authors="authors"
            :tints="tints"
            @toggleStar="toggleStar"
            @authorClick="modal = { kind: 'author', id: $event }"
            @edit="modal = 'edit'"
            @back="mobilePanel = 'list'"
        />
        <div
            v-else
            class="enki-detail"
            :class="{ 'enki-panel--hidden': isMobile }"
        />
    </div>

    <SubmitModal
        v-if="modal === 'submit'"
        :categories="categories"
        @close="modal = null"
        @imported="
            (slug) => {
                modal = null;
                selectSkill(slug);
            }
        "
    />

    <SubmitModal
        v-if="modal === 'edit' && selectedSkill"
        :categories="categories"
        :skill="selectedSkill"
        @close="modal = null"
        @imported="
            (slug) => {
                modal = null;
                router.reload({
                    only: ['selectedSkill', 'skills', 'skillCounts'],
                    reset: ['skills'],
                });
                selectSkill(slug);
            }
        "
        @deleted="
            () => {
                modal = null;
                router.visit('/enki');
            }
        "
    />

    <AuthorModal
        v-if="authorModal"
        :authorId="authorModal.id"
        :authors="authors"
        :skills="skills.data"
        :tints="tints"
        @close="modal = null"
        @selectSkill="selectSkill($event)"
    />
</template>
