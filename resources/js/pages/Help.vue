<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { help } from '@/routes';
import { agentInstall } from '@/routes/help';

defineProps<{
    docs: { slug: string; title: string }[];
    currentDoc: string;
    content: string;
}>();
</script>

<template>
    <Head title="Help" />

    <div class="enki-settings-shell">
        <aside class="enki-settings-side">
            <p class="enki-settings-side-label">Help</p>
            <nav aria-label="Help docs">
                <ul class="enki-settings-nav">
                    <li v-for="doc in docs" :key="doc.slug">
                        <Link
                            :href="help({ doc: doc.slug })"
                            class="enki-navlink"
                            :class="{ 'is-active': currentDoc === doc.slug }"
                        >
                            {{ doc.title }}
                        </Link>
                    </li>
                </ul>
            </nav>
            <p class="enki-settings-side-label" style="margin-top: 18px">Setup</p>
            <nav aria-label="Setup">
                <ul class="enki-settings-nav">
                    <li>
                        <Link :href="agentInstall()" class="enki-navlink">
                            Agent Install
                        </Link>
                    </li>
                </ul>
            </nav>
        </aside>

        <main class="enki-settings-main enki-help-main">
            <div class="enki-md-body" v-html="content" />
        </main>
    </div>
</template>
