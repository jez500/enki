<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    computed,
    nextTick,
    onMounted,
    onUnmounted,
    ref,
    useTemplateRef,
} from 'vue';
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
    filterClick: [];
}>();

const searchInput = useTemplateRef<HTMLInputElement>('searchInput');
const mobileSearchInput = useTemplateRef<HTMLInputElement>('mobileSearchInput');
const searchOpen = ref(false);

async function openMobileSearch() {
    searchOpen.value = true;
    await nextTick();
    mobileSearchInput.value?.focus();
}

function closeMobileSearch() {
    searchOpen.value = false;
}

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

    if (e.key === 'Escape') {
        if (document.activeElement === searchInput.value) {
            searchInput.value?.blur();
        }

        if (searchOpen.value) {
            closeMobileSearch();
        }
    }
}

onMounted(() => window.addEventListener('keydown', onKey));
onUnmounted(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <header class="enki-topbar">
        <!-- display:contents on desktop → brand/search/nav are direct grid children -->
        <div class="enki-topbar-bar">
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
                <span class="enki-brand-name">{{ page.props.name }}</span>
                <span class="enki-brand-sub">skills</span>
            </Link>

            <div class="enki-search enki-search--topbar">
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
                    class="enki-navbtn enki-btn--ghost enki-search-btn"
                    title="Search"
                    @click="openMobileSearch"
                >
                    <svg
                        width="15"
                        height="15"
                        viewBox="0 0 16 16"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.4"
                    >
                        <circle cx="7" cy="7" r="4.5" />
                        <path d="M10.5 10.5L13 13" stroke-linecap="round" />
                    </svg>
                </button>
                <button
                    type="button"
                    class="enki-navbtn enki-btn--ghost enki-filter-btn"
                    title="Filters"
                    @click="emit('filterClick')"
                >
                    <svg
                        width="15"
                        height="15"
                        viewBox="0 0 16 16"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.5"
                        stroke-linecap="round"
                    >
                        <path d="M2 4h12M4 8h8M6 12h4" />
                    </svg>
                </button>
                <button
                    type="button"
                    class="enki-navbtn enki-btn--ghost"
                    @click="emit('submit')"
                >
                    <span class="enki-submit-label">Submit a skill</span>
                    <svg
                        class="enki-submit-icon"
                        width="15"
                        height="15"
                        viewBox="0 0 16 16"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.6"
                        stroke-linecap="round"
                    >
                        <path d="M8 3v10M3 8h10" />
                    </svg>
                </button>
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <button
                            type="button"
                            class="enki-avatar"
                            :title="
                                (page.props.auth as any)?.user?.name ??
                                'Account'
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
        </div>

        <!-- Mobile search row — lives inside header so it doesn't disturb the app grid -->
        <div v-if="searchOpen" class="enki-topbar-search">
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
                    ref="mobileSearchInput"
                    type="text"
                    :value="query"
                    @input="
                        emit(
                            'update:query',
                            ($event.target as HTMLInputElement).value,
                        )
                    "
                    placeholder="Search skills, tags, authors…"
                    spellcheck="false"
                />
                <button
                    type="button"
                    class="enki-search-close"
                    title="Close search"
                    @click="closeMobileSearch"
                >
                    <svg
                        width="13"
                        height="13"
                        viewBox="0 0 16 16"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.6"
                        stroke-linecap="round"
                    >
                        <path d="M3 3l10 10M13 3L3 13" />
                    </svg>
                </button>
            </div>
        </div>
    </header>
</template>
