import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import EnkiLayout from '@/layouts/EnkiLayout.vue';
import EnkiMinimalLayout from '@/layouts/EnkiMinimalLayout.vue';
import EnkiSettingsLayout from '@/layouts/settings/EnkiLayout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

// Resolve the app name at runtime from the initial Inertia page props (the
// shared `name` prop, backed by AppService) so deployments can override
// APP_NAME without rebuilding. Falls back to the build-time VITE_APP_NAME.
function resolveAppName(): string {
    try {
        const el =
            document.getElementById('app') ??
            document.querySelector('[data-page]');
        const raw = el instanceof HTMLElement ? el.dataset.page : null;
        const name = raw
            ? (JSON.parse(raw)?.props?.name as string | undefined)
            : undefined;

        return name || import.meta.env.VITE_APP_NAME || 'enki';
    } catch {
        return import.meta.env.VITE_APP_NAME || 'enki';
    }
}

const appName = resolveAppName();

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
                return null;
            case name === 'Help':
            case name.startsWith('help/'):
            case name.startsWith('auth/'):
                return EnkiMinimalLayout;
            case name.startsWith('settings/'):
                return [EnkiMinimalLayout, EnkiSettingsLayout];
            default:
                return EnkiLayout;
        }
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
