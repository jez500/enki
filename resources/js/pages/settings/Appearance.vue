<script setup lang="ts">
import { inject } from 'vue';
import type { Ref } from 'vue';
import { Head } from '@inertiajs/vue3';

const darkMode = inject<Ref<boolean>>('enkiDarkMode')!;
const accent = inject<Ref<string>>('enkiAccent')!;

const accentPresets = [
    { name: 'Terracotta', value: '#c2603a' },
    { name: 'Forest',     value: '#3d6b4a' },
    { name: 'Indigo',     value: '#3d4f88' },
    { name: 'Plum',       value: '#7a3d65' },
    { name: 'Slate',      value: '#4a4a4a' },
];
</script>

<template>
    <Head title="Appearance" />

    <div class="enki-settings-section">
        <h2 class="enki-settings-heading">Appearance</h2>
        <div class="enki-form">
            <label class="enki-field enki-field--row">
                <span>Dark mode</span>
                <input
                    type="checkbox"
                    class="enki-toggle"
                    :checked="darkMode"
                    @change="darkMode = ($event.target as HTMLInputElement).checked"
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
                        @click="accent = p.value"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
