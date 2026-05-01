<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import EnkiAdminLayout from '@/layouts/EnkiAdminLayout.vue';

defineOptions({ layout: EnkiAdminLayout });

type AdminCategory = {
    id: number;
    slug: string;
    label: string;
    icon: string | null;
    color: { bg: string; fg: string } | null;
    skillsCount: number;
};

defineProps<{
    categories: AdminCategory[];
}>();

const editingId = ref<number | null>(null);

const editForm = useForm({
    slug: '',
    label: '',
    icon: '',
    color: { bg: '', fg: '' },
});

const createForm = useForm({
    slug: '',
    label: '',
    icon: '',
    color: { bg: '', fg: '' },
});

const showCreate = ref(false);

function startEdit(category: AdminCategory) {
    editingId.value = category.id;
    editForm.slug = category.slug;
    editForm.label = category.label;
    editForm.icon = category.icon ?? '';
    editForm.color = { bg: category.color?.bg ?? '', fg: category.color?.fg ?? '' };
}

function cancelEdit() {
    editingId.value = null;
    editForm.reset();
}

function saveEdit(category: AdminCategory) {
    editForm.patch(`/enki/admin/categories/${category.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingId.value = null;
        },
    });
}

function submitCreate() {
    createForm.post('/enki/admin/categories', {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            showCreate.value = false;
        },
    });
}

function deleteCategory(category: AdminCategory) {
    if (!confirm(`Delete "${category.label}"? This cannot be undone.`)) {
        return;
    }

    router.delete(`/enki/admin/categories/${category.id}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <div class="enki-admin">
        <div class="enki-admin-header">
            <h1 class="enki-admin-title">Categories</h1>
            <span class="enki-admin-count">
                {{ categories.length }}
                {{ categories.length === 1 ? 'category' : 'categories' }}
            </span>
            <button
                type="button"
                class="enki-admin-add"
                @click="showCreate = !showCreate"
            >
                + Add
            </button>
        </div>

        <div v-if="showCreate" class="enki-admin-create-form">
            <form @submit.prevent="submitCreate">
                <div class="enki-admin-form-row">
                    <input
                        v-model="createForm.slug"
                        class="enki-admin-input"
                        placeholder="slug"
                        required
                    />
                    <input
                        v-model="createForm.label"
                        class="enki-admin-input"
                        placeholder="Label"
                        required
                    />
                    <input
                        v-model="createForm.icon"
                        class="enki-admin-input enki-admin-input--icon"
                        placeholder="Icon"
                    />
                    <input
                        v-model="createForm.color.bg"
                        class="enki-admin-input enki-admin-input--color"
                        placeholder="bg"
                    />
                    <input
                        v-model="createForm.color.fg"
                        class="enki-admin-input enki-admin-input--color"
                        placeholder="fg"
                    />
                    <button type="submit" class="enki-admin-save">Save</button>
                    <button
                        type="button"
                        class="enki-admin-cancel"
                        @click="showCreate = false; createForm.reset()"
                    >
                        Cancel
                    </button>
                </div>
                <div
                    v-if="Object.keys(createForm.errors).length"
                    class="enki-admin-errors"
                >
                    <span
                        v-for="(error, field) in createForm.errors"
                        :key="field"
                    >{{ error }}</span>
                </div>
            </form>
        </div>

        <div class="enki-admin-table-wrap">
            <table class="enki-admin-table">
                <thead>
                    <tr>
                        <th>Icon</th>
                        <th>Slug</th>
                        <th>Label</th>
                        <th>Color</th>
                        <th>Skills</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="categories.length === 0">
                        <td colspan="6" class="enki-admin-empty">
                            No categories yet.
                        </td>
                    </tr>
                    <template v-for="category in categories" :key="category.id">
                        <tr v-if="editingId !== category.id">
                            <td class="enki-admin-icon-cell">
                                {{ category.icon ?? '—' }}
                            </td>
                            <td class="enki-admin-mono">{{ category.slug }}</td>
                            <td class="enki-admin-name">
                                {{ category.label }}
                            </td>
                            <td>
                                <span
                                    v-if="category.color"
                                    class="enki-admin-color-swatch"
                                    :style="{
                                        background: category.color.bg,
                                        color: category.color.fg,
                                    }"
                                >
                                    Aa
                                </span>
                                <span v-else class="enki-admin-date">—</span>
                            </td>
                            <td class="enki-admin-date">
                                {{ category.skillsCount }}
                            </td>
                            <td class="enki-admin-actions">
                                <button
                                    type="button"
                                    class="enki-admin-edit"
                                    title="Edit category"
                                    @click="startEdit(category)"
                                >
                                    <svg
                                        width="14"
                                        height="14"
                                        viewBox="0 0 16 16"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.5"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    >
                                        <path
                                            d="M11 2l3 3-8 8H3v-3l8-8z"
                                        />
                                    </svg>
                                </button>
                                <button
                                    type="button"
                                    class="enki-admin-delete"
                                    title="Delete category"
                                    @click="deleteCategory(category)"
                                >
                                    <svg
                                        width="14"
                                        height="14"
                                        viewBox="0 0 16 16"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.5"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    >
                                        <path
                                            d="M2 4h12M5 4V3h6v1M6 7v5M10 7v5M3 4l1 9h8l1-9"
                                        />
                                    </svg>
                                </button>
                            </td>
                        </tr>
                        <tr v-else class="enki-admin-edit-row">
                            <td colspan="6">
                                <form
                                    class="enki-admin-form-row"
                                    @submit.prevent="saveEdit(category)"
                                >
                                    <input
                                        v-model="editForm.slug"
                                        class="enki-admin-input"
                                        placeholder="slug"
                                        required
                                    />
                                    <input
                                        v-model="editForm.label"
                                        class="enki-admin-input"
                                        placeholder="Label"
                                        required
                                    />
                                    <input
                                        v-model="editForm.icon"
                                        class="enki-admin-input enki-admin-input--icon"
                                        placeholder="Icon"
                                    />
                                    <input
                                        v-model="editForm.color.bg"
                                        class="enki-admin-input enki-admin-input--color"
                                        placeholder="bg"
                                    />
                                    <input
                                        v-model="editForm.color.fg"
                                        class="enki-admin-input enki-admin-input--color"
                                        placeholder="fg"
                                    />
                                    <button
                                        type="submit"
                                        class="enki-admin-save"
                                    >
                                        Save
                                    </button>
                                    <button
                                        type="button"
                                        class="enki-admin-cancel"
                                        @click="cancelEdit"
                                    >
                                        Cancel
                                    </button>
                                </form>
                                <div
                                    v-if="Object.keys(editForm.errors).length"
                                    class="enki-admin-errors"
                                >
                                    <span
                                        v-for="(error, field) in editForm.errors"
                                        :key="field"
                                    >{{ error }}</span>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</template>
