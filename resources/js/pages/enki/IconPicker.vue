<script setup lang="ts">
import {
    MessageSquare,
    Code2,
    Database,
    Pencil,
    Settings,
    Search,
    Headphones,
    PenLine,
    BookOpen,
    Users,
    Globe,
    Zap,
    Lock,
    BarChart2,
    FileText,
    Terminal,
    Tag,
    Server,
    Calendar,
    Mail,
    Star,
    GitBranch,
    Layers,
    Cpu,
    Rocket,
    FlaskConical,
    Eye,
    Megaphone,
    Link,
    Wrench,
    Shield,
    Package,
    Workflow,
    Brain,
    Clipboard,
} from 'lucide-vue-next';
import type { LucideIcon } from 'lucide-vue-next';
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';

const ICONS: { name: string; component: LucideIcon }[] = [
    { name: 'MessageSquare', component: MessageSquare },
    { name: 'Code2', component: Code2 },
    { name: 'Database', component: Database },
    { name: 'Pencil', component: Pencil },
    { name: 'Settings', component: Settings },
    { name: 'Search', component: Search },
    { name: 'Headphones', component: Headphones },
    { name: 'PenLine', component: PenLine },
    { name: 'BookOpen', component: BookOpen },
    { name: 'Users', component: Users },
    { name: 'Globe', component: Globe },
    { name: 'Zap', component: Zap },
    { name: 'Lock', component: Lock },
    { name: 'BarChart2', component: BarChart2 },
    { name: 'FileText', component: FileText },
    { name: 'Terminal', component: Terminal },
    { name: 'Tag', component: Tag },
    { name: 'Server', component: Server },
    { name: 'Calendar', component: Calendar },
    { name: 'Mail', component: Mail },
    { name: 'Star', component: Star },
    { name: 'GitBranch', component: GitBranch },
    { name: 'Layers', component: Layers },
    { name: 'Cpu', component: Cpu },
    { name: 'Rocket', component: Rocket },
    { name: 'FlaskConical', component: FlaskConical },
    { name: 'Eye', component: Eye },
    { name: 'Megaphone', component: Megaphone },
    { name: 'Link', component: Link },
    { name: 'Wrench', component: Wrench },
    { name: 'Shield', component: Shield },
    { name: 'Package', component: Package },
    { name: 'Workflow', component: Workflow },
    { name: 'Brain', component: Brain },
    { name: 'Clipboard', component: Clipboard },
];

const props = defineProps<{ modelValue: string }>();
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

const open = ref(false);
const container = ref<HTMLElement>();

const current = computed(
    () => ICONS.find((i) => i.name === props.modelValue) ?? null,
);

function select(name: string) {
    emit('update:modelValue', name);
    open.value = false;
}

function onOutsideClick(e: MouseEvent) {
    if (container.value && !container.value.contains(e.target as Node)) {
        open.value = false;
    }
}

onMounted(() => document.addEventListener('mousedown', onOutsideClick));
onBeforeUnmount(() =>
    document.removeEventListener('mousedown', onOutsideClick),
);
</script>

<template>
    <div ref="container" class="enki-icon-picker">
        <button
            type="button"
            class="enki-icon-picker-trigger"
            :class="{ 'is-open': open }"
            :title="modelValue || 'Pick icon'"
            @click="open = !open"
        >
            <component
                :is="current?.component"
                v-if="current"
                :size="14"
                :stroke-width="1.5"
            />
            <span v-else class="enki-icon-picker-empty">—</span>
        </button>

        <div v-if="open" class="enki-icon-picker-grid">
            <button
                v-for="icon in ICONS"
                :key="icon.name"
                type="button"
                class="enki-icon-picker-option"
                :class="{ 'is-selected': icon.name === modelValue }"
                :title="icon.name"
                @click="select(icon.name)"
            >
                <component
                    :is="icon.component"
                    :size="14"
                    :stroke-width="1.5"
                />
            </button>
        </div>
    </div>
</template>
