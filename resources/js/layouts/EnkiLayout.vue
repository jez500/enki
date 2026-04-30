<script setup lang="ts">
import { onMounted, provide, ref, useTemplateRef, watch } from 'vue';

const darkMode = ref(localStorage.getItem('enki-dark') !== 'false');
const accent = ref(localStorage.getItem('enki-accent') ?? '#4a4a4a');
const appRef = useTemplateRef<HTMLDivElement>('appRef');

function applyTheme() {
    const el = appRef.value;

    if (!el) {
return;
}

    el.setAttribute('data-theme', darkMode.value ? 'dark' : 'light');
    const baseBg = darkMode.value ? '#16140f' : '#faf8f3';
    const baseInk = darkMode.value ? '#f0ead8' : '#1a1814';
    el.style.setProperty('--accent', accent.value);
    el.style.setProperty('--accent-soft', `color-mix(in oklch, ${accent.value} ${darkMode.value ? 28 : 22}%, ${baseBg})`);
    el.style.setProperty('--accent-ink', `color-mix(in oklch, ${accent.value} 78%, ${baseInk})`);
}

watch(accent, (v) => localStorage.setItem('enki-accent', v));
watch(darkMode, (v) => localStorage.setItem('enki-dark', String(v)));

onMounted(applyTheme);
watch([darkMode, accent], applyTheme);

provide('enkiDarkMode', darkMode);
provide('enkiAccent', accent);
</script>

<template>
    <div ref="appRef" class="enki-app" :data-theme="darkMode ? 'dark' : 'light'">
        <slot />
    </div>
</template>

<style>
@import '../../css/enki.css';

html, body, #app {
    height: 100%;
    margin: 0;
    padding: 0;
    overflow: hidden;
}
</style>
