<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import EnkiAdminLayout from '@/layouts/EnkiAdminLayout.vue';

defineOptions({ layout: EnkiAdminLayout });

type AdminUser = {
    id: number;
    name: string;
    email: string;
    role: string;
    createdAt: string;
};

defineProps<{
    users: AdminUser[];
    roles: string[];
}>();

function updateRole(userId: number, role: string) {
    router.patch(
        `/enki/admin/users/${userId}/role`,
        { role },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}

function deleteUser(user: AdminUser) {
    if (!confirm(`Delete ${user.name}? This cannot be undone.`)) {
        return;
    }

    router.delete(`/enki/admin/users/${user.id}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <div class="enki-admin">
        <div class="enki-admin-header">
            <h1 class="enki-admin-title">Users</h1>
            <span class="enki-admin-count"
                >{{ users.length }}
                {{ users.length === 1 ? 'user' : 'users' }}</span
            >
        </div>

        <div class="enki-admin-table-wrap">
            <table class="enki-admin-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Joined</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="user in users" :key="user.id">
                        <td class="enki-admin-name">{{ user.name }}</td>
                        <td class="enki-admin-email">{{ user.email }}</td>
                        <td>
                            <select
                                class="enki-admin-select"
                                :value="user.role"
                                @change="
                                    updateRole(
                                        user.id,
                                        ($event.target as HTMLSelectElement)
                                            .value,
                                    )
                                "
                            >
                                <option v-for="r in roles" :key="r" :value="r">
                                    {{ r.charAt(0).toUpperCase() + r.slice(1) }}
                                </option>
                            </select>
                        </td>
                        <td class="enki-admin-date">{{ user.createdAt }}</td>
                        <td class="enki-admin-actions">
                            <button
                                type="button"
                                class="enki-admin-delete"
                                title="Delete user"
                                @click="deleteUser(user)"
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
                </tbody>
            </table>
        </div>
    </div>
</template>
