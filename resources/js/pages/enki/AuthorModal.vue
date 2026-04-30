<script setup lang="ts">
import { computed, onMounted, onUnmounted } from 'vue';
import type { EnkiSkillSummary, EnkiAuthor, EnkiTint } from '@/types/enki';
import Monogram from './Monogram.vue';

const props = defineProps<{
    authorId: string;
    authors: Record<string, EnkiAuthor>;
    skills: EnkiSkillSummary[];
    tints: EnkiTint[];
}>();

const emit = defineEmits<{
    close: [];
    selectSkill: [slug: string];
}>();

const author = computed(() => props.authors[props.authorId]);
const authorSkills = computed(() => props.skills.filter((s) => s.author === props.authorId));
const initials = computed(() =>
    author.value.name
        .split(' ')
        .map((w) => w[0])
        .slice(0, 2)
        .join(''),
);

function onKey(e: KeyboardEvent) {
    if (e.key === 'Escape') {
emit('close');
}
}

onMounted(() => window.addEventListener('keydown', onKey));
onUnmounted(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <div class="enki-modal-bg">
        <div class="enki-modal" @click.stop>
            <header class="enki-modal-head">
                <h2>Author</h2>
                <button type="button" class="enki-modal-x" @click="emit('close')" aria-label="Close">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
                        <path d="M3 3l10 10M13 3L3 13" />
                    </svg>
                </button>
            </header>
            <div class="enki-modal-body">
                <div class="enki-author">
                    <div class="enki-author-head">
                        <div class="enki-author-mark">{{ initials }}</div>
                        <div>
                            <h3>{{ author.name }}</h3>
                            <div class="enki-author-meta">{{ author.members }} members · {{ author.skills }} skills published</div>
                        </div>
                    </div>
                    <div class="enki-author-list">
                        <div class="enki-author-listhead">Skills by this team</div>
                        <ul>
                            <li v-for="s in authorSkills" :key="s.slug">
                                <button
                                    type="button"
                                    class="enki-author-item"
                                    @click="emit('selectSkill', s.slug); emit('close')"
                                >
                                    <Monogram :name="s.name" :tint="s.monogramTint" :tints="tints" :size="28" />
                                    <div>
                                        <div class="enki-author-item-name">{{ s.name }}</div>
                                        <div class="enki-author-item-sum">{{ s.summary }}</div>
                                    </div>
                                    <span class="enki-mono-sm">v{{ s.version }}</span>
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
