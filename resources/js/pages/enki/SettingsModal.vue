<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue';
import type { EnkiPrefs } from '@/types/enki';

defineProps<{
    prefs: EnkiPrefs;
    darkMode: boolean;
    accent: string;
}>();

const emit = defineEmits<{
    close: [];
    'update:prefs': [prefs: EnkiPrefs];
    'update:darkMode': [value: boolean];
    'update:accent': [value: string];
}>();

const accentPresets = [
    { name: 'Terracotta', value: '#c2603a' },
    { name: 'Forest',     value: '#3d6b4a' },
    { name: 'Indigo',     value: '#3d4f88' },
    { name: 'Plum',       value: '#7a3d65' },
    { name: 'Slate',      value: '#4a4a4a' },
];

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
                <h2>Settings</h2>
                <button type="button" class="enki-modal-x" @click="emit('close')" aria-label="Close">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
                        <path d="M3 3l10 10M13 3L3 13" />
                    </svg>
                </button>
            </header>
            <div class="enki-modal-body">
                <div class="enki-form">
                    <!-- Appearance -->
                    <label class="enki-field enki-field--row">
                        <span>Dark mode</span>
                        <input
                            type="checkbox"
                            class="enki-toggle"
                            :checked="darkMode"
                            @change="emit('update:darkMode', ($event.target as HTMLInputElement).checked)"
                        />
                    </label>

                    <div class="enki-field">
                        <span>Accent color</span>
                        <div class="enki-accent-presets">
                            <button
                                v-for="p in accentPresets"
                                :key="p.value"
                                type="button"
                                :class="['enki-accent-swatch', { 'is-on': accent === p.value }]"
                                :style="{ background: p.value }"
                                :title="p.name"
                                @click="emit('update:accent', p.value)"
                            />
                        </div>
                    </div>

<!--                    &lt;!&ndash; Install scope &ndash;&gt;-->
<!--                    <fieldset class="enki-field" style="border: none; padding: 0; margin: 0;">-->
<!--                        <span>Default install scope</span>-->
<!--                        <div class="enki-radiogroup">-->
<!--                            <label-->
<!--                                v-for="o in ['workspace', 'project', 'agent']"-->
<!--                                :key="o"-->
<!--                                :class="['enki-radiochip', { 'is-on': prefs.scope === o }]"-->
<!--                            >-->
<!--                                <input-->
<!--                                    type="radio"-->
<!--                                    name="scope"-->
<!--                                    :value="o"-->
<!--                                    :checked="prefs.scope === o"-->
<!--                                    @change="emit('update:prefs', { ...prefs, scope: o as EnkiPrefs['scope'] })"-->
<!--                                />-->
<!--                                <span class="enki-radiochip-label" style="text-transform: capitalize">{{ o }}</span>-->
<!--                            </label>-->
<!--                        </div>-->
<!--                    </fieldset>-->

<!--                    <label class="enki-field enki-field&#45;&#45;row">-->
<!--                        <span>Auto-update skills</span>-->
<!--                        <input-->
<!--                            type="checkbox"-->
<!--                            class="enki-toggle"-->
<!--                            :checked="prefs.autoUpdate"-->
<!--                            @change="emit('update:prefs', { ...prefs, autoUpdate: ($event.target as HTMLInputElement).checked })"-->
<!--                        />-->
<!--                    </label>-->
<!--                    <label class="enki-field enki-field&#45;&#45;row">-->
<!--                        <span>Show experimental skills</span>-->
<!--                        <input-->
<!--                            type="checkbox"-->
<!--                            class="enki-toggle"-->
<!--                            :checked="prefs.showExperimental"-->
<!--                            @change="emit('update:prefs', { ...prefs, showExperimental: ($event.target as HTMLInputElement).checked })"-->
<!--                        />-->
<!--                    </label>-->
<!--                    <label class="enki-field enki-field&#45;&#45;row">-->
<!--                        <span>Notify me on updates I depend on</span>-->
<!--                        <input-->
<!--                            type="checkbox"-->
<!--                            class="enki-toggle"-->
<!--                            :checked="prefs.notify"-->
<!--                            @change="emit('update:prefs', { ...prefs, notify: ($event.target as HTMLInputElement).checked })"-->
<!--                        />-->
<!--                    </label>-->
                </div>
            </div>
            <footer class="enki-modal-foot">
                <div class="enki-modal-actions">
                    <button type="button" class="enki-btn enki-btn--primary" @click="emit('close')">Done</button>
                </div>
            </footer>
        </div>
    </div>
</template>
