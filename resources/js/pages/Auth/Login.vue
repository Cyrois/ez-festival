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
        class="flex min-h-screen items-center justify-center bg-[#F4F5F7] p-6 text-[#1A1A1A] antialiased"
    >
        <div class="w-full max-w-[400px]">
            <div class="mb-7 text-center">
                <div
                    class="mb-3 inline-flex h-11 w-11 items-center justify-center rounded-[10px] bg-[#1F7A74] text-[15px] font-bold text-white"
                    aria-hidden="true"
                >
                    {{ $t('app.mark') }}
                </div>
                <h1 class="m-0 text-xl font-bold tracking-tight">{{ $t('app.name') }}</h1>
            </div>

            <div class="rounded-xl border border-[#E5E7EB] bg-white px-7 pb-7 pt-8">
                <h2 class="mb-1.5 text-[22px] font-bold tracking-tight">{{ $t('auth.login.title') }}</h2>
                <p class="mb-6 text-sm leading-snug text-[#6B7280]">{{ $t('auth.login.lead') }}</p>

                <form @submit.prevent="submit">
                    <div class="mb-4">
                        <label class="mb-1.5 block text-[13px] font-bold text-[#1A1A1A]" for="email">
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
                            class="box-border h-[42px] w-full rounded-lg border border-[#E5E7EB] bg-white px-3 text-sm text-[#1A1A1A] outline-none focus:border-[#1F7A74] focus:shadow-[0_0_0_3px_rgba(31,122,116,0.22)]"
                        />
                        <p v-if="form.errors.email" class="mt-1.5 text-[13px] leading-snug text-[#B91C1C]">
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <div class="mb-4">
                        <label class="mb-1.5 block text-[13px] font-bold text-[#1A1A1A]" for="password">
                            {{ $t('auth.login.password') }}
                        </label>
                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            name="password"
                            autocomplete="current-password"
                            required
                            class="box-border h-[42px] w-full rounded-lg border border-[#E5E7EB] bg-white px-3 text-sm text-[#1A1A1A] outline-none focus:border-[#1F7A74] focus:shadow-[0_0_0_3px_rgba(31,122,116,0.22)]"
                        />
                        <p v-if="form.errors.password" class="mt-1.5 text-[13px] leading-snug text-[#B91C1C]">
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <div class="mb-[22px] mt-1 flex items-center justify-between text-[13px]">
                        <label class="flex cursor-pointer items-center gap-2 font-normal text-[#6B7280]">
                            <input
                                v-model="form.remember"
                                type="checkbox"
                                name="remember"
                                class="h-[15px] w-[15px] accent-[#1F7A74]"
                            />
                            {{ $t('auth.login.remember') }}
                        </label>
                        <Link href="/forgot-password" class="font-bold text-[#3D6B8A] no-underline hover:underline">
                            {{ $t('auth.login.forgot') }}
                        </Link>
                    </div>

                    <button
                        class="h-[42px] w-full cursor-pointer rounded-lg border-none bg-[#1F7A74] text-sm font-bold text-white hover:bg-[#196560] disabled:cursor-not-allowed disabled:opacity-70"
                        type="submit"
                        :disabled="form.processing"
                    >
                        {{ $t('auth.login.submit') }}
                    </button>
                </form>

                <p class="mt-5 text-center text-xs text-[#6B7280]">{{ $t('auth.login.foot') }}</p>
            </div>
        </div>
    </div>
</template>
