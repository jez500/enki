<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useHttp } from '@inertiajs/vue3';
import { agentInstall } from '@/routes/help';
import { token as tokenRoute } from '@/routes/help/agent-install';
import { help } from '@/routes';

const props = defineProps<{
    docs: { slug: string; title: string }[];
    apiToken: string | null;
    appUrl: string;
}>();

const token = ref(props.apiToken);
const copied = ref(false);
const regenerating = ref(false);
const tokenVisible = ref(false);
const http = useHttp();

const prompt = computed(() => {
    const key = token.value ?? '<your-api-key>';
    return `\
You have been granted access to our team's Enki skills library — a curated collection of AI agent skills.

Your first task is to install the enki skill. This skill acts as your gateway to the library: once installed, it will guide you through discovering and installing additional skills in future sessions.

Library URL: ${props.appUrl}
Your API key: ${key}

## Steps

1. Download the enki skill

Run this command to download the skill archive:

\`\`\`bash
curl -fsSL \\
  -H "Authorization: Bearer ${key}" \\
  "${props.appUrl}/api/skills/enki/download" \\
  -o /tmp/enki.zip
\`\`\`

2. Install the skill

Extract the archive into your Claude skills directory:

\`\`\`bash
mkdir -p ~/.claude/skills/enki
unzip -o /tmp/enki.zip -d ~/.claude/skills/enki
rm /tmp/enki.zip
\`\`\`

3. Read and follow the skill

\`\`\`bash
cat ~/.claude/skills/enki/SKILL.md
\`\`\`

Follow the instructions in SKILL.md carefully. It will explain how to authenticate with the library at ${props.appUrl}, browse available skills, and install them as needed.

---
Keep this API key confidential — it provides access to the skills library on your behalf.`;
});

async function copyPrompt() {
    await navigator.clipboard.writeText(prompt.value);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}

async function copyToken() {
    if (!token.value) return;
    await navigator.clipboard.writeText(token.value);
}

async function regenerateToken() {
    regenerating.value = true;
    try {
        const res = await http.post(tokenRoute().url);
        token.value = (res as any).api_token ?? token.value;
        tokenVisible.value = true;
    } finally {
        regenerating.value = false;
    }
}
</script>

<template>
    <Head title="Agent Install" />

    <div class="enki-settings-shell">
        <aside class="enki-settings-side">
            <p class="enki-settings-side-label">Help</p>
            <nav aria-label="Help docs">
                <ul class="enki-settings-nav">
                    <li v-for="doc in docs" :key="doc.slug">
                        <Link
                            :href="help({ doc: doc.slug })"
                            class="enki-navlink"
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
                        <Link :href="agentInstall()" class="enki-navlink is-active">
                            Agent Install
                        </Link>
                    </li>
                </ul>
            </nav>
        </aside>

        <main class="enki-settings-main enki-help-main">
            <div class="enki-md-body">
                <h1>Agent Install</h1>
                <p>
                    Copy the prompt below and give it to any AI agent. It will download the
                    <strong>enki</strong> skill, which in turn allows the agent to discover and
                    install additional skills from this library in future sessions.
                </p>

                <h2>Your API Key</h2>
                <p>
                    This key authenticates the agent with the library on your behalf. Keep it
                    confidential.
                </p>

                <div class="enki-agent-token-row">
                    <code class="enki-agent-token-value">
                        <template v-if="token">
                            <span v-if="tokenVisible">{{ token }}</span>
                            <span v-else>{{ '•'.repeat(24) }}</span>
                        </template>
                        <span v-else class="enki-agent-token-empty">No key generated yet</span>
                    </code>
                    <div class="enki-agent-token-actions">
                        <button
                            v-if="token"
                            type="button"
                            class="enki-btn enki-btn--ghost"
                            @click="tokenVisible = !tokenVisible"
                        >
                            {{ tokenVisible ? 'Hide' : 'Show' }}
                        </button>
                        <button
                            v-if="token"
                            type="button"
                            class="enki-btn enki-btn--ghost"
                            @click="copyToken"
                        >
                            Copy
                        </button>
                        <button
                            type="button"
                            class="enki-btn enki-btn--ghost"
                            :disabled="regenerating"
                            @click="regenerateToken"
                        >
                            {{ token ? 'Regenerate' : 'Generate key' }}
                        </button>
                    </div>
                </div>

                <template v-if="token">
                    <h2>Install Prompt</h2>
                    <p>
                        Copy this prompt in full and paste it at the start of a new agent
                        conversation. The agent will handle the rest.
                    </p>

                    <div class="enki-agent-prompt-wrap">
                        <button
                            type="button"
                            class="enki-agent-copy-btn enki-btn enki-btn--ghost"
                            @click="copyPrompt"
                        >
                            {{ copied ? 'Copied!' : 'Copy prompt' }}
                        </button>
                        <pre class="enki-agent-prompt">{{ prompt }}</pre>
                    </div>
                </template>

                <div v-else class="enki-agent-no-token">
                    Generate an API key above to see the install prompt.
                </div>
            </div>
        </main>
    </div>
</template>
