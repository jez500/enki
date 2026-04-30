<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, provide, ref, useTemplateRef, watch } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { User } from '@/types';
import { logout } from '@/routes';
import { edit as profileEdit } from '@/routes/profile';

defineOptions({ inheritAttrs: false });

const darkMode = ref(localStorage.getItem('enki-dark') !== 'false');
const accent = ref(localStorage.getItem('enki-accent') ?? '#3d6b4a');
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
    el.style.setProperty(
        '--accent-soft',
        `color-mix(in oklch, ${accent.value} ${darkMode.value ? 28 : 22}%, ${baseBg})`,
    );
    el.style.setProperty(
        '--accent-ink',
        `color-mix(in oklch, ${accent.value} 78%, ${baseInk})`,
    );
}

watch(accent, (v) => localStorage.setItem('enki-accent', v));
watch(darkMode, (v) => localStorage.setItem('enki-dark', String(v)));

onMounted(applyTheme);
watch([darkMode, accent], applyTheme);

provide('enkiDarkMode', darkMode);
provide('enkiAccent', accent);

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
</script>

<template>
    <div
        ref="appRef"
        class="enki-app"
        :data-theme="darkMode ? 'dark' : 'light'"
    >
        <header class="enki-topbar enki-topbar--minimal">
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
            </Link>
            <nav class="enki-topnav">
                <DropdownMenu v-if="userInitials">
                    <DropdownMenuTrigger as-child>
                        <button
                            type="button"
                            class="enki-avatar"
                            :title="authUser?.name ?? 'Account'"
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
                            <Link
                                href="/help"
                                class="block w-full cursor-pointer"
                            >
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

        <div class="enki-page-body">
            <slot />
        </div>
    </div>
</template>

<style>
@import '../../css/enki.css';
</style>
