<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head :title="$t('auth.login.title')" />

    <div
        class="flex min-h-screen items-center justify-center bg-page p-6 text-charcoal antialiased"
    >
        <div class="w-full max-w-[400px]">
            <div class="mb-7 text-center">
                <div
                    class="mb-3 inline-flex h-11 w-11 items-center justify-center rounded-[10px] bg-brand text-[15px] font-bold text-white"
                    aria-hidden="true"
                >
                    {{ $t('app.mark') }}
                </div>
                <h1 class="m-0 text-xl font-bold tracking-tight">{{ $t('app.name') }}</h1>
            </div>

            <div class="rounded-xl border border-line bg-white px-7 pb-7 pt-8">
                <h2 class="mb-1.5 text-[22px] font-bold tracking-tight">{{ $t('auth.login.title') }}</h2>
                <p class="mb-6 text-sm leading-snug text-muted">{{ $t('auth.login.lead') }}</p>

                <form @submit.prevent="submit">
                    <div class="mb-4">
                        <label class="mb-1.5 block text-[13px] font-bold text-charcoal" for="email">
                            {{ $t('auth.login.email') }}
                        </label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            name="email"
                            autocomplete="username"
                            required
                            autofocus
                            class="box-border h-[42px] w-full rounded-lg border border-line bg-white px-3 text-sm text-charcoal outline-none focus:border-brand focus:shadow-[0_0_0_3px_color-mix(in_srgb,var(--color-brand)_22%,transparent)]"
                        />
                        <p v-if="form.errors.email" class="mt-1.5 text-[13px] leading-snug text-danger">
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <div class="mb-4">
                        <label class="mb-1.5 block text-[13px] font-bold text-charcoal" for="password">
                            {{ $t('auth.login.password') }}
                        </label>
                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            name="password"
                            autocomplete="current-password"
                            required
                            class="box-border h-[42px] w-full rounded-lg border border-line bg-white px-3 text-sm text-charcoal outline-none focus:border-brand focus:shadow-[0_0_0_3px_color-mix(in_srgb,var(--color-brand)_22%,transparent)]"
                        />
                        <p v-if="form.errors.password" class="mt-1.5 text-[13px] leading-snug text-danger">
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <div class="mb-[22px] mt-1 flex items-center justify-between text-[13px]">
                        <label class="flex cursor-pointer items-center gap-2 font-normal text-muted">
                            <input
                                v-model="form.remember"
                                type="checkbox"
                                name="remember"
                                class="h-[15px] w-[15px] accent-brand"
                            />
                            {{ $t('auth.login.remember') }}
                        </label>
                        <Link href="/forgot-password" class="font-bold text-accent no-underline hover:underline">
                            {{ $t('auth.login.forgot') }}
                        </Link>
                    </div>

                    <button
                        class="h-[42px] w-full cursor-pointer rounded-lg border-none bg-brand text-sm font-bold text-white hover:bg-brand-hover disabled:cursor-not-allowed disabled:opacity-70"
                        type="submit"
                        :disabled="form.processing"
                    >
                        {{ $t('auth.login.submit') }}
                    </button>
                </form>

                <p class="mt-5 text-center text-xs text-muted">{{ $t('auth.login.foot') }}</p>
            </div>
        </div>
    </div>
</template>
