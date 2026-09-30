<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    token: { type: String, required: true },
    authenticated: { type: Boolean, required: true },
    valid: { type: Boolean, required: true },
});

const form = useForm({
    password: '',
    password_confirmation: '',
});
const signOutForm = useForm({});

const submit = () => {
    form.put(`/team-invitations/${props.token}`, {
        onFinish: () => form.reset(),
    });
};
const signOut = () => {
    signOutForm.post('/logout');
};
</script>

<template>
    <Head :title="$t('auth.invitation.title')" />

    <div
        class="flex min-h-screen items-center justify-center bg-page px-4 py-6 text-charcoal antialiased"
    >
        <div class="w-full max-w-[400px]">
            <div class="mb-7 text-center">
                <div
                    class="mb-3 inline-flex h-11 w-11 items-center justify-center rounded-[10px] bg-brand text-[15px] font-bold text-white"
                    aria-hidden="true"
                >
                    {{ $t('app.mark') }}
                </div>
                <h1 class="m-0 text-xl font-bold tracking-tight">
                    {{ $t('app.name') }}
                </h1>
            </div>

            <div class="rounded-xl border border-line bg-white px-7 pt-8 pb-7">
                <template v-if="authenticated">
                    <h2 class="mb-3 text-[22px] font-bold tracking-tight">
                        {{ $t('auth.invitation.signed_in_title') }}
                    </h2>
                    <p class="mb-6 text-sm leading-relaxed text-muted">
                        {{ $t('auth.invitation.signed_in_body') }}
                    </p>
                    <button
                        type="button"
                        :disabled="signOutForm.processing"
                        class="h-[42px] w-full rounded-lg bg-brand text-sm font-bold text-white disabled:opacity-70"
                        @click="signOut"
                    >
                        {{ $t('auth.invitation.sign_out') }}
                    </button>
                </template>
                <template v-else-if="valid">
                    <h2 class="mb-6 text-[22px] font-bold tracking-tight">
                        {{ $t('auth.invitation.title') }}
                    </h2>
                    <form @submit.prevent="submit">
                        <div class="mb-4">
                            <label
                                class="mb-1.5 block text-[13px] font-bold"
                                for="password"
                            >
                                {{ $t('auth.password.new') }}
                            </label>
                            <input
                                id="password"
                                v-model="form.password"
                                type="password"
                                autocomplete="new-password"
                                required
                                autofocus
                                class="box-border h-[42px] w-full rounded-lg border border-line px-3 text-sm outline-none focus:border-brand"
                            />
                            <p class="mt-1.5 mb-0 text-xs text-muted">
                                {{ $t('auth.password.rule') }}
                            </p>
                            <p
                                v-if="form.errors.password"
                                class="mt-1.5 text-[13px] text-danger"
                                role="alert"
                            >
                                {{ form.errors.password }}
                            </p>
                        </div>
                        <div class="mb-6">
                            <label
                                class="mb-1.5 block text-[13px] font-bold"
                                for="password_confirmation"
                            >
                                {{ $t('auth.password.confirm') }}
                            </label>
                            <input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                required
                                class="box-border h-[42px] w-full rounded-lg border border-line px-3 text-sm outline-none focus:border-brand"
                            />
                        </div>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="h-[42px] w-full rounded-lg bg-brand text-sm font-bold text-white disabled:opacity-70"
                        >
                            {{ $t('auth.invitation.submit') }}
                        </button>
                    </form>
                </template>
                <template v-else>
                    <h2 class="mb-3 text-[22px] font-bold tracking-tight">
                        {{ $t('auth.invitation.invalid_title') }}
                    </h2>
                    <p class="m-0 text-sm leading-relaxed text-muted">
                        {{ $t('auth.invitation.invalid_body') }}
                    </p>
                </template>
            </div>
        </div>
    </div>
</template>
