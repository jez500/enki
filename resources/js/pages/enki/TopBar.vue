<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, useTemplateRef } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { logout } from '@/routes';
import { edit as profileEdit } from '@/routes/profile';
import type { User } from '@/types';

defineProps<{
    query: string;
}>();

const emit = defineEmits<{
    'update:query': [value: string];
    submit: [];
}>();

const searchInput = useTemplateRef<HTMLInputElement>('searchInput');

const page = usePage();
const authUser = computed(() => (page.props.auth as { user: User })?.user);
const userInitials = computed(() => {
    const name = authUser.value?.name ?? '';

    return name
        .split(' ')
        .map((n: string) => n[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
});
const isAdmin = computed(() => authUser.value?.role === 'admin');

function onKey(e: KeyboardEvent) {
    const tag = (document.activeElement as HTMLElement).tagName;

    if (e.key === '/' && tag !== 'INPUT' && tag !== 'TEXTAREA') {
        e.preventDefault();
        searchInput.value?.focus();
    }

    if (e.key === 'Escape' && document.activeElement === searchInput.value) {
        searchInput.value?.blur();
    }
}

onMounted(() => window.addEventListener('keydown', onKey));
onUnmounted(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <header class="enki-topbar">
        <Link href="/enki" class="enki-brand">
            <div class="enki-brand-mark">
                <svg
                    viewBox="0 0 24 24"
                    width="20"
                    height="20"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.6"
                    stroke-linecap="round"
                >
                    <path
                        d="M4 7l8-4 8 4M4 7v10l8 4 8-4V7M4 7l8 4M20 7l-8 4M12 11v10"
                    />
                </svg>
            </div>
            <span class="enki-brand-name">enki</span>
            <span class="enki-brand-sub">skills</span>
        </Link>

        <div class="enki-search">
            <svg
                width="14"
                height="14"
                viewBox="0 0 16 16"
                fill="none"
                stroke="currentColor"
                stroke-width="1.4"
                style="opacity: 0.45"
            >
                <circle cx="7" cy="7" r="4.5" />
                <path d="M10.5 10.5L13 13" stroke-linecap="round" />
            </svg>
            <input
                ref="searchInput"
                type="text"
                :value="query"
                @input="
                    emit(
                        'update:query',
                        ($event.target as HTMLInputElement).value,
                    )
                "
                placeholder="Search 487 skills, tags, authors…"
                spellcheck="false"
            />
            <kbd class="enki-kbd">/</kbd>
        </div>

        <nav class="enki-topnav">
            <button
                type="button"
                class="enki-navbtn enki-btn--ghost"
                @click="emit('submit')"
            >
                Submit a skill
            </button>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <button
                        type="button"
                        class="enki-avatar"
                        :title="
                            (page.props.auth as any)?.user?.name ?? 'Account'
                        "
                    >
                        {{ userInitials }}
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-44">
                    <DropdownMenuItem v-if="isAdmin" as-child>
                        <Link
                            href="/enki/admin/users"
                            class="block w-full cursor-pointer"
                        >
                            Admin
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem as-child>
                        <Link
                            :href="profileEdit().url"
                            class="block w-full cursor-pointer"
                        >
                            Settings
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem as-child>
                        <Link href="/help" class="block w-full cursor-pointer">
                            Help
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem as-child>
                        <Link
                            :href="logout().url"
                            method="post"
                            as="button"
                            class="block w-full cursor-pointer"
                            @click="router.flushAll()"
                        >
                            Logout
                        </Link>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </nav>
    </header>
</template>
