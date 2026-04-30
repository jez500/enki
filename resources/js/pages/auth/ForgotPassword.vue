<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Forgot password" />

    <div class="flex min-h-svh flex-col items-center justify-center p-6">
        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="space-y-2 text-center">
                    <h1 class="text-xl font-medium">Forgot password</h1>
                    <p class="text-sm text-muted-foreground">
                        Enter your email to receive a password reset link
                    </p>
                </div>

                <div
                    v-if="status"
                    class="text-center text-sm font-medium text-green-600"
                >
                    {{ status }}
                </div>

                <div class="space-y-6">
                    <Form v-bind="email.form()" v-slot="{ errors, processing }">
                        <div class="grid gap-2">
                            <Label for="email">Email address</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                autocomplete="off"
                                autofocus
                                placeholder="email@example.com"
                            />
                            <InputError :message="errors.email" />
                        </div>

                        <div class="my-6 flex items-center justify-start">
                            <Button
                                class="w-full"
                                :disabled="processing"
                                data-test="email-password-reset-link-button"
                            >
                                <Spinner v-if="processing" />
                                Email password reset link
                            </Button>
                        </div>
                    </Form>

                    <div
                        class="space-x-1 text-center text-sm text-muted-foreground"
                    >
                        <span>Or, return to</span>
                        <TextLink :href="login()">log in</TextLink>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
