<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const navItems: NavItem[] = [
    { title: 'Profile', href: editProfile() },
    { title: 'Security', href: editSecurity() },
    { title: 'Appearance', href: editAppearance() },
];

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="enki-settings-shell">
        <aside class="enki-settings-side">
            <p class="enki-settings-side-label">Settings</p>
            <nav aria-label="Settings">
                <ul class="enki-settings-nav">
                    <li v-for="item in navItems" :key="toUrl(item.href)">
                        <Link
                            :href="item.href"
                            class="enki-navlink"
                            :class="{
                                'is-active': isCurrentOrParentUrl(item.href),
                            }"
                        >
                            {{ item.title }}
                        </Link>
                    </li>
                </ul>
            </nav>
            <p class="enki-settings-side-label" style="margin-top: 18px">
                Back
            </p>
            <Link href="/enki" class="enki-settings-back">
                <svg
                    width="10"
                    height="10"
                    viewBox="0 0 16 16"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.6"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M10 3L5 8l5 5" />
                </svg>
                Back to Library
            </Link>
        </aside>

        <main class="enki-settings-main">
            <slot />
        </main>
    </div>
</template>
