<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { formatTitle } from '@/lib/utils';
import type { EnkiSkill, EnkiAuthor, EnkiTint } from '@/types/enki';
import Monogram from './Monogram.vue';

const props = defineProps<{
    skill: EnkiSkill;
    authors: Record<string, EnkiAuthor>;
    tints: EnkiTint[];
}>();

const emit = defineEmits<{
    toggleStar: [slug: string];
    authorClick: [authorId: string];
    edit: [];
    back: [];
}>();

const page = usePage();

const tab = ref<'readme' | 'files' | 'usage' | 'changelog'>('readme');
const copied = ref(false);
const openFilePath = ref(props.skill.files[0]?.path ?? '');
const syncing = ref(false);
const syncError = ref<string | null>(null);

async function syncSkill() {
    syncing.value = true;
    syncError.value = null;

    try {
        const res = await fetch(
            `/enki/skills/${encodeURIComponent(props.skill.slug)}/sync`,
            {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as HTMLMetaElement
                        )?.content ?? '',
                    Accept: 'application/json',
                },
            },
        );

        if (!res.ok) {
            const body = await res.json().catch(() => ({}));
            syncError.value = body.message ?? 'Sync failed.';
        } else {
            window.location.reload();
        }
    } catch {
        syncError.value = 'Network error during sync.';
    } finally {
        syncing.value = false;
    }
}

watch(
    () => props.skill.slug,
    () => {
        tab.value = 'readme';
        summaryExpanded.value = false;
        openFilePath.value = props.skill.files[0]?.path ?? '';
        expandedDirs.value = new Set();
    },
);

const author = computed(
    () =>
        props.authors[props.skill.author] ?? {
            name: props.skill.author,
            members: 0,
            skills: 0,
        },
);
const installCmd = computed(
    () => `${page.props.machineName} add ${props.skill.slug}`,
);

const summaryExpanded = ref(false);
const summaryTruncated = computed(() => props.skill.summary.length > 150);
const summaryDisplay = computed(() => {
    if (summaryExpanded.value || !summaryTruncated.value) {
        return props.skill.summary;
    }

    const cut = props.skill.summary.slice(0, 150);
    const lastSpace = cut.lastIndexOf(' ');

    return lastSpace > 0 ? cut.slice(0, lastSpace) : cut;
});

function copyInstall() {
    navigator.clipboard?.writeText(installCmd.value).catch(() => {});
    copied.value = true;
    setTimeout(() => (copied.value = false), 1400);
}

const tabs = computed(() => [
    { id: 'readme', label: 'README' },
    { id: 'files', label: 'Files', count: props.skill.files.length },
    { id: 'usage', label: 'Usage' },
    { id: 'changelog', label: 'Changelog' },
]);

// ── File tree ────────────────────────────────────────────────────────────────

interface TreeNode {
    name: string;
    path: string;
    isDir: boolean;
    size?: string;
    kind?: string;
    children?: TreeNode[];
}

interface FlatItem {
    name: string;
    path: string;
    isDir: boolean;
    depth: number;
    size?: string;
    kind?: string;
    hasChildren: boolean;
    isExpanded: boolean;
}

function buildTree(files: typeof props.skill.files): TreeNode[] {
    const root: TreeNode[] = [];

    for (const file of files) {
        const parts = file.path.split('/');
        let nodes = root;

        for (let i = 0; i < parts.length - 1; i++) {
            const dirPath = parts.slice(0, i + 1).join('/');
            let dir = nodes.find((n) => n.isDir && n.name === parts[i]);

            if (!dir) {
                dir = {
                    name: parts[i],
                    path: dirPath,
                    isDir: true,
                    children: [],
                };
                nodes.push(dir);
            }

            nodes = dir.children!;
        }

        nodes.push({
            name: parts[parts.length - 1],
            path: file.path,
            isDir: false,
            size: file.size,
            kind: file.kind,
        });
    }

    return root;
}

const expandedDirs = ref<Set<string>>(new Set());
const fileTree = computed(() => buildTree(props.skill.files));

function flattenTree(nodes: TreeNode[], depth = 0): FlatItem[] {
    const result: FlatItem[] = [];

    for (const node of nodes) {
        if (node.isDir) {
            const isExpanded = expandedDirs.value.has(node.path);
            result.push({
                name: node.name,
                path: node.path,
                isDir: true,
                depth,
                hasChildren: !!node.children?.length,
                isExpanded,
            });

            if (isExpanded) {
                result.push(...flattenTree(node.children ?? [], depth + 1));
            }
        } else {
            result.push({
                name: node.name,
                path: node.path,
                isDir: false,
                depth,
                size: node.size,
                kind: node.kind,
                hasChildren: false,
                isExpanded: false,
            });
        }
    }

    return result;
}

const fileTreeItems = computed(() => flattenTree(fileTree.value));

function toggleDir(path: string) {
    const next = new Set(expandedDirs.value);

    if (next.has(path)) {
        next.delete(path);
    } else {
        next.add(path);
    }

    expandedDirs.value = next;
}

// ── File viewer ──────────────────────────────────────────────────────────────

const openFile = computed(() =>
    props.skill.files.find((f) => f.path === openFilePath.value),
);

const filePreview = computed(() => {
    const file = openFile.value;

    if (!file) {
        return '';
    }

    if (file.content != null) {
        return file.content;
    }

    // Mock previews for internal skills (no stored content)
    const basename = file.path.split('/').pop() ?? file.path;

    if (basename === 'skill.yaml') {
        return `name: ${props.skill.name}\nslug: ${props.skill.slug}\nversion: ${props.skill.version}\nowner: ${props.skill.author}\n\ninputs:\n  goal: string\n  context: string[]\n  schema: object?\n\noutputs:\n  result: object\n  trace: step[]\n\nruntime:\n  model: claude-sonnet-4.5\n  temperature: 0\n  max_steps: 12\n`;
    }

    if (file.kind === 'json') {
        return `{\n  "skill": "${props.skill.slug}",\n  "version": "${props.skill.version}",\n  "example": {\n    "goal": "Summarize the attached thread",\n    "context": ["thread:Q3-launch"]\n  },\n  "expected": {\n    "summary": "…",\n    "decisions": [],\n    "owners": []\n  }\n}`;
    }

    if (file.kind === 'jsonl') {
        return Array.from({ length: 6 })
            .map(
                (_, i) =>
                    `{"id":"case-${i + 1}","input":{"goal":"…"},"expected":{"score":${(0.8 + i * 0.02).toFixed(2)}}}`,
            )
            .join('\n');
    }

    return `Error loading: ${file.path}`;
});

// ── Activity log ─────────────────────────────────────────────────────────────

const eventLabels: Record<string, string> = {
    created: 'Created',
    updated: 'Updated',
    deleted: 'Deleted',
    restored: 'Restored',
};

function eventLabel(event: string): string {
    return eventLabels[event] ?? event.charAt(0).toUpperCase() + event.slice(1);
}

function eventIcon(event: string): string {
    const icons: Record<string, string> = {
        created: 'M8 2v12M2 8h12',
        updated: 'M3 8h10M9 4l4 4-4 4',
        deleted: 'M4 8h8M3 4l10 8M3 12l10-8',
        restored: 'M3 8c0-2.8 2.2-5 5-5s5 2.2 5 5-2.2 5-5 5M8 6v2l1.5 1.5',
    };

    return icons[event] ?? 'M8 8h.01';
}

// ── File icon ────────────────────────────────────────────────────────────────

function fileIconFill(kind: string) {
    const fills: Record<string, string> = {
        md: '#d4cfbf',
        json: '#cfc7af',
        yaml: '#c8c0a4',
        jsonl: '#cfc7af',
    };

    return fills[kind] ?? '#dcd6c5';
}
</script>

<template>
    <section class="enki-detail">
        <button type="button" class="enki-detail-back" @click="emit('back')">
            <svg
                width="14"
                height="14"
                viewBox="0 0 16 16"
                fill="none"
                stroke="currentColor"
                stroke-width="1.5"
                stroke-linecap="round"
            >
                <path d="M10 3L4 8l6 5" />
            </svg>
            Back to list
        </button>
        <div class="enki-detail-scroll">
            <!-- Header -->
            <header class="enki-detail-head">
                <div class="enki-detail-head-inner">
                    <Monogram
                        :key="skill.slug"
                        :name="formatTitle(skill.name)"
                        :tint="skill.monogramTint"
                        :tints="tints"
                        :size="60"
                        :icon="skill.categoryIcon"
                        :color="skill.categoryColor"
                    />
                    <div style="flex: 1">
                        <div class="enki-detail-head-top">
                            <div class="enki-detail-headtext">
                                <div class="enki-detail-eyebrow">
                                    <button
                                        type="button"
                                        class="enki-link"
                                        @click="
                                            emit('authorClick', skill.author)
                                        "
                                    >
                                        {{ author.name }}
                                    </button>
                                    <span class="enki-item-dot">·</span>
                                    <span class="enki-mono-sm">{{
                                        skill.slug
                                    }}</span>
                                    <template v-if="skill.isPrivate">
                                        <span class="enki-item-dot">·</span>
                                        <span class="enki-private-badge">
                                            <svg
                                                width="10"
                                                height="10"
                                                viewBox="0 0 16 16"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.6"
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
                                            Private
                                        </span>
                                    </template>
                                    <template v-if="skill.isExternal">
                                        <span class="enki-item-dot">·</span>
                                        <a
                                            :href="skill.githubUrl ?? undefined"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="enki-external-badge"
                                        >
                                            <svg
                                                width="11"
                                                height="11"
                                                viewBox="0 0 16 16"
                                                fill="currentColor"
                                                aria-hidden="true"
                                            >
                                                <path
                                                    d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"
                                                />
                                            </svg>
                                            External
                                        </a>
                                    </template>
                                </div>
                                <h1
                                    class="enki-detail-name enki-detail-name--editable"
                                >
                                    <span>{{ formatTitle(skill.name) }}</span>
                                    <button
                                        v-if="skill.canEdit"
                                        type="button"
                                        class="enki-edit-btn"
                                        aria-label="Edit skill"
                                        title="Edit skill"
                                        @click="emit('edit')"
                                    >
                                        <svg
                                            width="14"
                                            height="14"
                                            viewBox="0 0 16 16"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.4"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            aria-hidden="true"
                                        >
                                            <path
                                                d="M11.5 1.5l3 3-9 9H2.5v-3l9-9z"
                                            />
                                            <path d="M9.5 3.5l3 3" />
                                        </svg>
                                    </button>
                                </h1>
                            </div>
                            <div class="enki-detail-actions">
                                <button
                                    v-if="skill.isExternal"
                                    type="button"
                                    class="enki-btn enki-btn--ghost"
                                    :disabled="syncing"
                                    @click="syncSkill"
                                    title="Re-pull from GitHub"
                                >
                                    <svg
                                        width="13"
                                        height="13"
                                        viewBox="0 0 16 16"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.5"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        :class="{ 'enki-spin': syncing }"
                                    >
                                        <path
                                            d="M13.5 2.5A6.5 6.5 0 1 1 7 1.5"
                                        />
                                        <path d="M10 1l3 2-2 3" />
                                    </svg>
                                    {{ syncing ? 'Syncing…' : 'Sync' }}
                                </button>
                                <button
                                    v-if="skill.canStar"
                                    type="button"
                                    :class="[
                                        'enki-btn',
                                        'enki-btn--ghost',
                                        { 'is-on': skill.starred },
                                    ]"
                                    @click="emit('toggleStar', skill.slug)"
                                >
                                    <svg
                                        width="13"
                                        height="13"
                                        viewBox="0 0 16 16"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M8 1.5l1.95 4.16 4.55.55-3.37 3.16.86 4.5L8 11.7l-4 2.17.86-4.5L1.5 6.21l4.55-.55L8 1.5z"
                                            :fill="
                                                skill.starred
                                                    ? 'currentColor'
                                                    : 'none'
                                            "
                                            stroke="currentColor"
                                            stroke-width="1.4"
                                            stroke-linejoin="round"
                                        />
                                    </svg>
                                    <span>{{
                                        skill.starred ? 'Starred' : 'Star'
                                    }}</span>
                                    <span class="enki-btn-count">{{
                                        skill.ratings
                                    }}</span>
                                </button>
                                <a
                                    :href="`/enki/skills/${encodeURIComponent(skill.slug)}/download`"
                                    class="enki-btn enki-btn--primary"
                                    download
                                >
                                    <svg
                                        width="13"
                                        height="13"
                                        viewBox="0 0 16 16"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.6"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        aria-hidden="true"
                                    >
                                        <path d="M8 2v8.5" />
                                        <path d="M4.5 7L8 10.5 11.5 7" />
                                        <path d="M3 13h10" />
                                    </svg>
                                    Download
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <p class="enki-detail-summary">
                    {{ summaryDisplay
                    }}<button
                        v-if="summaryTruncated && !summaryExpanded"
                        type="button"
                        class="enki-summary-more"
                        @click="summaryExpanded = true"
                    >
                        …more
                    </button>
                </p>
                <div
                    v-if="skill.tags && skill.tags.length"
                    class="enki-detail-tags"
                >
                    <span v-for="t in skill.tags" :key="t" class="enki-tagchip"
                        >#{{ t }}</span
                    >
                </div>
            </header>

            <!-- Install command -->
            <div class="enki-install">
                <div class="enki-install-head">
                    <span class="enki-mono-sm">install</span>
                    <a href="/help/agent-install" class="enki-link enki-mono-sm"
                        >Need help?</a
                    >
                </div>
                <div class="enki-install-cmd">
                    <span class="enki-install-prompt">/</span>
                    <span class="enki-install-text">{{ installCmd }}</span>
                    <button
                        type="button"
                        class="enki-install-copy"
                        @click="copyInstall"
                    >
                        <template v-if="copied">
                            <svg
                                width="12"
                                height="12"
                                viewBox="0 0 16 16"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.6"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M3.5 8.5l2.8 2.8L12.8 4.8" />
                            </svg>
                            Copied
                        </template>
                        <template v-else>
                            <svg
                                width="12"
                                height="12"
                                viewBox="0 0 16 16"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.3"
                            >
                                <rect
                                    x="4.5"
                                    y="4.5"
                                    width="8"
                                    height="9"
                                    rx="1.2"
                                />
                                <path d="M3 11V3a.5.5 0 0 1 .5-.5H10" />
                            </svg>
                            Copy
                        </template>
                    </button>
                </div>
            </div>

            <!-- Tabs -->
            <nav class="enki-tabs">
                <button
                    v-for="t in tabs"
                    :key="t.id"
                    type="button"
                    :class="['enki-tab', { 'is-active': tab === t.id }]"
                    @click="tab = t.id as typeof tab"
                >
                    <span>{{ t.label }}</span>
                    <span v-if="t.count != null" class="enki-tab-count">{{
                        t.count
                    }}</span>
                </button>
            </nav>

            <!-- Tab bodies -->
            <div class="enki-tabbody">
                <!-- README -->
                <article v-if="tab === 'readme'" class="enki-md">
                    <dl
                        v-if="Object.keys(skill.readmeFrontmatter).length"
                        class="enki-frontmatter"
                    >
                        <template
                            v-for="(val, key) in skill.readmeFrontmatter"
                            :key="key"
                        >
                            <dt>{{ key }}</dt>
                            <dd>{{ val }}</dd>
                        </template>
                    </dl>
                    <div v-html="skill.readmeHtml" class="enki-md-body" />
                </article>

                <!-- Usage -->
                <template v-else-if="tab === 'usage'">
                    <div class="enki-agent-hint">
                        <span class="enki-agent-hint-title"
                            >Tell your agent how to download these skills</span
                        >
                        <span class="enki-agent-hint-body"
                            >Your AI agent can install skills directly from the
                            command line.
                            <a href="/help/agent-install" class="enki-link"
                                >Learn how to set up your agent →</a
                            ></span
                        >
                    </div>
                    <article class="enki-md">
                        <div v-html="skill.usageHtml" class="enki-md-body" />
                    </article>
                </template>

                <!-- Files -->
                <div v-else-if="tab === 'files'" class="enki-files">
                    <div class="enki-files-tree">
                        <div class="enki-files-treetop">
                            <span class="enki-mono-sm"
                                >{{ skill.slug.split('/').pop() }}/</span
                            >
                            <span class="enki-files-count"
                                >{{ skill.files.length }} files</span
                            >
                        </div>
                        <ul>
                            <li v-for="item in fileTreeItems" :key="item.path">
                                <!-- Directory row -->
                                <button
                                    v-if="item.isDir"
                                    type="button"
                                    class="enki-file enki-file--dir"
                                    :style="{
                                        paddingLeft: `${8 + item.depth * 16}px`,
                                    }"
                                    @click="toggleDir(item.path)"
                                >
                                    <svg
                                        width="10"
                                        height="10"
                                        viewBox="0 0 10 10"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.5"
                                        stroke-linecap="round"
                                        :class="[
                                            'enki-chevron',
                                            {
                                                'enki-chevron--open':
                                                    item.isExpanded,
                                            },
                                        ]"
                                    >
                                        <path d="M2 3.5l3 3 3-3" />
                                    </svg>
                                    <svg
                                        width="14"
                                        height="14"
                                        viewBox="0 0 16 16"
                                        fill="none"
                                    >
                                        <path
                                            d="M1.5 5.5h13v7.5a.5.5 0 0 1-.5.5h-12a.5.5 0 0 1-.5-.5V5.5z"
                                            fill="#dfd9c5"
                                            stroke="currentColor"
                                            stroke-opacity=".4"
                                            stroke-width=".8"
                                        />
                                        <path
                                            d="M1.5 5.5h4.8l1.2-2H14a.5.5 0 0 1 .5.5V6"
                                            fill="#dfd9c5"
                                            stroke="currentColor"
                                            stroke-opacity=".4"
                                            stroke-width=".8"
                                        />
                                    </svg>
                                    <span class="enki-file-name">{{
                                        item.name
                                    }}</span>
                                </button>
                                <!-- File row -->
                                <button
                                    v-else
                                    type="button"
                                    :class="[
                                        'enki-file',
                                        {
                                            'is-active':
                                                openFilePath === item.path,
                                        },
                                    ]"
                                    :style="{
                                        paddingLeft: `${8 + item.depth * 16}px`,
                                    }"
                                    @click="openFilePath = item.path"
                                >
                                    <svg
                                        width="14"
                                        height="14"
                                        viewBox="0 0 16 16"
                                        fill="none"
                                    >
                                        <path
                                            d="M3.5 1.5h6L12.5 4.5v9.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-12a.5.5 0 0 1 .5-.5z"
                                            :fill="
                                                fileIconFill(item.kind ?? '')
                                            "
                                            stroke="currentColor"
                                            stroke-opacity=".35"
                                            stroke-width=".8"
                                        />
                                        <path
                                            d="M9.5 1.5v3h3"
                                            stroke="currentColor"
                                            stroke-opacity=".35"
                                            stroke-width=".8"
                                            fill="none"
                                        />
                                    </svg>
                                    <span class="enki-file-name">{{
                                        item.name
                                    }}</span>
                                    <span class="enki-file-size">{{
                                        item.size
                                    }}</span>
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="enki-files-view">
                        <div class="enki-files-bar">
                            <span class="enki-mono-sm">{{
                                openFile?.path
                            }}</span>
                            <span class="enki-files-meta"
                                >{{ openFile?.size }} ·
                                {{ openFile?.kind }}</span
                            >
                        </div>
                        <pre
                            class="enki-files-pre"
                        ><code>{{ filePreview }}</code></pre>
                    </div>
                </div>

                <!-- Changelog / Activity -->
                <div v-else-if="tab === 'changelog'" class="enki-changelog">
                    <div
                        v-if="skill.activityLog.length === 0"
                        class="enki-empty"
                    >
                        No activity recorded yet.
                    </div>
                    <ul v-else class="enki-activity-list">
                        <li
                            v-for="(entry, i) in skill.activityLog"
                            :key="entry.at + i"
                            class="enki-activity-item"
                        >
                            <div class="enki-activity-line" />
                            <div class="enki-activity-dot">
                                <svg
                                    width="12"
                                    height="12"
                                    viewBox="0 0 16 16"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.6"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    aria-hidden="true"
                                >
                                    <path :d="eventIcon(entry.event)" />
                                </svg>
                            </div>
                            <div class="enki-activity-body">
                                <div class="enki-activity-head">
                                    <span class="enki-activity-event">{{
                                        eventLabel(entry.event)
                                    }}</span>
                                    <span
                                        v-if="entry.causer"
                                        class="enki-activity-by"
                                        >by {{ entry.causer }}</span
                                    >
                                </div>
                                <time
                                    :datetime="entry.at"
                                    class="enki-activity-time"
                                    :title="new Date(entry.at).toLocaleString()"
                                >
                                    {{ entry.atHuman }}
                                </time>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>
</template>
