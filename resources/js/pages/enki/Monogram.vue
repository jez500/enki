<script setup lang="ts">
import type { EnkiColor, EnkiTint } from '@/types/enki';

const props = defineProps<{
    name: string;
    tint: number;
    tints: EnkiTint[];
    size?: number;
    icon?: string | null;
    color?: EnkiColor | null;
}>();

const size = props.size ?? 38;

const t = props.color ?? props.tints[props.tint % props.tints.length];
const letters = props.name
    .split(/\s+/)
    .map((w) => w[0])
    .slice(0, 2)
    .join('')
    .toUpperCase();

const iconSize = Math.round(size * 0.52);
</script>

<template>
    <div
        class="enki-monogram"
        :style="{
            width: `${size}px`,
            height: `${size}px`,
            background: t.bg,
            color: t.fg,
            fontSize: `${size * 0.36}px`,
        }"
    >
        <svg
            v-if="icon"
            :width="iconSize"
            :height="iconSize"
            viewBox="0 0 16 16"
            fill="none"
            :stroke="t.fg"
            stroke-width="1.4"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
        >
            <path :d="icon" />
        </svg>
        <template v-else>{{ letters }}</template>
    </div>
</template>
