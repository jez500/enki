import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import EnkiLayout from '@/layouts/EnkiLayout.vue';
import EnkiMinimalLayout from '@/layouts/EnkiMinimalLayout.vue';
import EnkiSettingsLayout from '@/layouts/settings/EnkiLayout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

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
            case name === 'Enki':
            case name.startsWith('enki/'):
                return EnkiLayout;
            default:
                return AppLayout;
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
