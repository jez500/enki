<script setup lang="ts">
import { onMounted, onUnmounted, reactive, ref } from 'vue';
import type { EnkiCategory, EnkiSkill } from '@/types/enki';

const props = defineProps<{
    categories: EnkiCategory[];
    skill?: EnkiSkill | null;
}>();

const emit = defineEmits<{
    close: [];
    imported: [slug: string];
    deleted: [slug: string];
}>();

const isEdit = !!props.skill;

const mode = ref<'create' | 'github'>('create');

const form = reactive({
    name: props.skill?.name ?? '',
    slug: props.skill?.slug ?? '',
    category: props.skill?.categories[0] ?? 'writing',
    summary: props.skill?.summary ?? '',
    tags: props.skill?.tags?.join(', ') ?? '',
    visibility: (props.skill?.isPrivate ? 'private' : 'public') as
        | 'public'
        | 'private',
});

const archiveFile = ref<File | null>(null);
const archiveInputRef = ref<HTMLInputElement | null>(null);

const githubUrl = ref('');
const importing = ref(false);
const importError = ref<string | null>(null);
const submitting = ref(false);
const submitError = ref<string | null>(null);
const submitted = ref(false);
const submittedName = ref('');
const confirmingDelete = ref(false);
const deleting = ref(false);
const deleteError = ref<string | null>(null);

function csrfToken(): string {
    return (
        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)
            ?.content ?? ''
    );
}

function onKey(e: KeyboardEvent) {
    if (e.key === 'Escape') {
        emit('close');
    }
}

onMounted(() => window.addEventListener('keydown', onKey));
onUnmounted(() => window.removeEventListener('keydown', onKey));

function onArchivePick(e: Event) {
    const input = e.target as HTMLInputElement;
    archiveFile.value = input.files?.[0] ?? null;
}

function clearArchive() {
    archiveFile.value = null;

    if (archiveInputRef.value) {
        archiveInputRef.value.value = '';
    }
}

async function importFromGitHub() {
    importing.value = true;
    importError.value = null;

    try {
        const res = await fetch('/enki/skills/import', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ github_url: githubUrl.value }),
        });
        const body = await res.json().catch(() => ({}));

        if (!res.ok) {
            importError.value = body.message ?? 'Import failed.';
        } else {
            submittedName.value = body.name ?? githubUrl.value;
            submitted.value = true;
            emit('imported', body.slug);
        }
    } catch {
        importError.value = 'Network error. Please try again.';
    } finally {
        importing.value = false;
    }
}

async function submitSkill() {
    submitting.value = true;
    submitError.value = null;

    try {
        const fd = new FormData();
        fd.append('name', form.name);

        if (!isEdit && form.slug) {
            fd.append('slug', form.slug);
        }

        fd.append('category', form.category);
        fd.append('summary', form.summary);
        fd.append('tags', form.tags);
        fd.append('visibility', form.visibility);

        if (archiveFile.value) {
            fd.append('archive', archiveFile.value);
        }

        const url = isEdit
            ? `/enki/skills/${encodeURIComponent(props.skill!.slug)}`
            : '/enki/skills';
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: fd,
        });
        const body = await res.json().catch(() => ({}));

        if (!res.ok) {
            submitError.value = body.message ?? 'Submission failed.';
        } else {
            submittedName.value = body.name ?? form.name;
            submitted.value = true;
            emit('imported', body.slug);
        }
    } catch {
        submitError.value = 'Network error. Please try again.';
    } finally {
        submitting.value = false;
    }
}

async function deleteSkill() {
    deleting.value = true;
    deleteError.value = null;

    try {
        const res = await fetch(
            `/enki/skills/${encodeURIComponent(props.skill!.slug)}`,
            {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            },
        );

        if (!res.ok) {
            const body = await res.json().catch(() => ({}));
            deleteError.value = body.message ?? 'Delete failed.';
        } else {
            emit('deleted', props.skill!.slug);
        }
    } catch {
        deleteError.value = 'Network error. Please try again.';
    } finally {
        deleting.value = false;
    }
}
</script>

<template>
    <div class="enki-modal-bg">
        <!-- Success state -->
        <div v-if="submitted" class="enki-modal" @click.stop>
            <header class="enki-modal-head">
                <h2>
                    {{
                        isEdit
                            ? 'Skill updated'
                            : mode === 'github'
                              ? 'Skill imported'
                              : 'Skill created'
                    }}
                </h2>
                <button
                    type="button"
                    class="enki-modal-x"
                    @click="emit('close')"
                    aria-label="Close"
                >
                    <svg
                        width="14"
                        height="14"
                        viewBox="0 0 16 16"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.5"
                        stroke-linecap="round"
                    >
                        <path d="M3 3l10 10M13 3L3 13" />
                    </svg>
                </button>
            </header>
            <div class="enki-modal-body">
                <div class="enki-submit-done">
                    <div class="enki-submit-check">
                        <svg
                            width="20"
                            height="20"
                            viewBox="0 0 16 16"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.6"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="M3.5 8.5l2.8 2.8L12.8 4.8" />
                        </svg>
                    </div>
                    <h3>
                        {{
                            isEdit
                                ? 'Skill updated.'
                                : mode === 'github'
                                  ? 'Imported from GitHub.'
                                  : 'Skill created.'
                        }}
                    </h3>
                    <p>
                        <strong>{{ submittedName }}</strong>
                        {{
                            isEdit
                                ? 'has been updated.'
                                : 'has been added to the library.'
                        }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Form state -->
        <div v-else class="enki-modal" @click.stop>
            <header class="enki-modal-head">
                <h2>{{ isEdit ? 'Edit skill' : 'Add a skill' }}</h2>
                <button
                    type="button"
                    class="enki-modal-x"
                    @click="emit('close')"
                    aria-label="Close"
                >
                    <svg
                        width="14"
                        height="14"
                        viewBox="0 0 16 16"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.5"
                        stroke-linecap="round"
                    >
                        <path d="M3 3l10 10M13 3L3 13" />
                    </svg>
                </button>
            </header>
            <div class="enki-modal-body">
                <!-- Mode toggle (create only) -->
                <div v-if="!isEdit" class="enki-mode-toggle">
                    <button
                        type="button"
                        :class="[
                            'enki-mode-btn',
                            { 'is-active': mode === 'create' },
                        ]"
                        @click="mode = 'create'"
                    >
                        Create skill
                    </button>
                    <button
                        type="button"
                        :class="[
                            'enki-mode-btn',
                            { 'is-active': mode === 'github' },
                        ]"
                        @click="mode = 'github'"
                    >
                        <svg
                            width="12"
                            height="12"
                            viewBox="0 0 16 16"
                            fill="currentColor"
                            aria-hidden="true"
                        >
                            <path
                                d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"
                            />
                        </svg>
                        Import from GitHub
                    </button>
                </div>

                <!-- GitHub import form -->
                <div v-if="!isEdit && mode === 'github'" class="enki-form">
                    <label class="enki-field">
                        <span>GitHub directory URL</span>
                        <input
                            type="url"
                            v-model="githubUrl"
                            placeholder="https://github.com/owner/repo/tree/main"
                            autocomplete="off"
                        />
                    </label>
                    <p v-if="importError" class="enki-field-error">
                        {{ importError }}
                    </p>
                    <p class="enki-field-hint">
                        Repo root or subdirectory URL — must contain
                        <code>SKILL.md</code> and optionally
                        <code>skill.yaml</code>.
                    </p>
                </div>

                <!-- Manual create / edit form -->
                <div v-else class="enki-form">
                    <label class="enki-field">
                        <span>Display name</span>
                        <input
                            type="text"
                            v-model="form.name"
                            placeholder="e.g. Doc Outliner"
                        />
                    </label>
                    <label v-if="!isEdit" class="enki-field">
                        <span>Slug <em>optional, auto-generated</em></span>
                        <input
                            type="text"
                            v-model="form.slug"
                            placeholder="doc-outliner"
                        />
                    </label>
                    <label class="enki-field">
                        <span>Category</span>
                        <select v-model="form.category">
                            <option
                                v-for="c in categories.filter(
                                    (c) => c.id !== 'all',
                                )"
                                :key="c.id"
                                :value="c.id"
                            >
                                {{ c.label }}
                            </option>
                        </select>
                    </label>
                    <label class="enki-field">
                        <span>One-line summary</span>
                        <textarea
                            v-model="form.summary"
                            placeholder="What this skill does, in one sentence."
                            maxlength="500"
                            rows="2"
                        />
                    </label>
                    <label class="enki-field">
                        <span>Tags <em>comma separated</em></span>
                        <input
                            type="text"
                            v-model="form.tags"
                            placeholder="rag, support, drafting"
                        />
                    </label>
                    <div class="enki-field">
                        <span
                            >Skill file
                            <em v-if="isEdit"
                                >optional, replaces existing contents</em
                            ></span
                        >
                        <div v-if="archiveFile" class="enki-upload-chosen">
                            <svg
                                width="13"
                                height="13"
                                viewBox="0 0 16 16"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.4"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path d="M13 10v3H3v-3M8 2v8M5 5l3-3 3 3" />
                            </svg>
                            <span>{{ archiveFile.name }}</span>
                            <button
                                type="button"
                                class="enki-upload-clear"
                                @click="clearArchive"
                                aria-label="Remove file"
                            >
                                ×
                            </button>
                        </div>
                        <button
                            v-else
                            type="button"
                            class="enki-upload-btn"
                            @click="archiveInputRef?.click()"
                        >
                            <svg
                                width="13"
                                height="13"
                                viewBox="0 0 16 16"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.4"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path d="M13 10v3H3v-3M8 2v8M5 5l3-3 3 3" />
                            </svg>
                            Choose archive…
                        </button>
                        <input
                            ref="archiveInputRef"
                            type="file"
                            accept=".zip,.tar,.tar.gz,.tgz"
                            style="display: none"
                            @change="onArchivePick"
                        />
                        <p class="enki-field-hint">
                            Accepted: .zip, .tar, .tar.gz — must contain
                            <code>SKILL.md</code> at root
                        </p>
                    </div>
                    <fieldset
                        class="enki-field"
                        style="border: none; padding: 0; margin: 0"
                    >
                        <span>Visibility</span>
                        <div class="enki-radiogroup">
                            <label
                                v-for="o in [
                                    {
                                        id: 'public',
                                        label: 'Everyone',
                                        desc: 'All can see',
                                    },
                                    {
                                        id: 'private',
                                        label: 'Private',
                                        desc: 'Only you',
                                    },
                                ]"
                                :key="o.id"
                                :class="[
                                    'enki-radiochip',
                                    { 'is-on': form.visibility === o.id },
                                ]"
                            >
                                <input
                                    type="radio"
                                    name="vis"
                                    :value="o.id"
                                    v-model="form.visibility"
                                />
                                <span class="enki-radiochip-label">{{
                                    o.label
                                }}</span>
                                <span class="enki-radiochip-desc">{{
                                    o.desc
                                }}</span>
                            </label>
                        </div>
                    </fieldset>
                    <p v-if="submitError" class="enki-field-error">
                        {{ submitError }}
                    </p>
                    <p v-if="deleteError" class="enki-field-error">
                        {{ deleteError }}
                    </p>

                    <!-- Delete confirmation -->
                    <div
                        v-if="isEdit && confirmingDelete"
                        class="enki-delete-confirm"
                    >
                        <p>
                            <strong>Delete this skill?</strong> This will
                            permanently remove the skill and all of its files.
                            This cannot be undone.
                        </p>
                        <div class="enki-delete-confirm-actions">
                            <button
                                type="button"
                                class="enki-btn enki-btn--ghost"
                                :disabled="deleting"
                                @click="confirmingDelete = false"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                class="enki-btn enki-btn--danger"
                                :disabled="deleting"
                                @click="deleteSkill"
                            >
                                {{ deleting ? 'Deleting…' : 'Delete skill' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <footer class="enki-modal-foot">
                <div class="enki-modal-actions">
                    <button
                        v-if="isEdit && !confirmingDelete"
                        type="button"
                        class="enki-btn enki-btn--danger-ghost"
                        @click="confirmingDelete = true"
                    >
                        Delete
                    </button>
                    <button
                        type="button"
                        class="enki-btn enki-btn--ghost"
                        @click="emit('close')"
                    >
                        Cancel
                    </button>
                    <button
                        v-if="!isEdit && mode === 'github'"
                        type="button"
                        class="enki-btn enki-btn--primary"
                        :disabled="!githubUrl.trim() || importing"
                        @click="importFromGitHub"
                    >
                        {{ importing ? 'Importing…' : 'Import' }}
                    </button>
                    <button
                        v-else
                        type="button"
                        class="enki-btn enki-btn--primary"
                        :disabled="
                            !form.name.trim() ||
                            (!isEdit && !archiveFile) ||
                            submitting
                        "
                        @click="submitSkill"
                    >
                        {{
                            submitting
                                ? isEdit
                                    ? 'Saving…'
                                    : 'Submitting…'
                                : isEdit
                                  ? 'Save changes'
                                  : 'Submit'
                        }}
                    </button>
                </div>
            </footer>
        </div>
    </div>
</template>
