<script setup lang="ts">
import * as LucideIcons from 'lucide-vue-next';
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
const iconSize = Math.round(size * 0.52);

const t = props.color ?? props.tints[props.tint % props.tints.length];
const letters = props.name
    .split(/\s+/)
    .map((w) => w[0])
    .slice(0, 2)
    .join('')
    .toUpperCase();

const iconComponent = props.icon
    ? ((LucideIcons as Record<string, unknown>)[props.icon] ?? null)
    : null;
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
        <component
            :is="iconComponent"
            v-if="iconComponent"
            :size="iconSize"
            :color="t.fg"
            :stroke-width="1.4"
            aria-hidden="true"
        />
        <template v-else>{{ letters }}</template>
    </div>
</template>
