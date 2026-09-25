<script setup>
import { Card } from '../../components/ui/card';
import { Icon } from '../../components/ui/icon';
import { Head } from '@inertiajs/vue3';

defineProps({
    form: { type: Object, required: true },
    event: { type: Object, required: true },
});
</script>

<template>
    <Head :title="$t('team.forms.public.confirmation_title')" />
    <main
        class="flex min-h-screen items-center justify-center bg-page px-4 py-10 text-charcoal antialiased sm:px-6"
    >
        <Card class="w-full max-w-lg text-center">
            <div
                class="mx-auto mb-5 inline-flex h-14 w-14 items-center justify-center rounded-full bg-success/10 text-success"
            >
                <Icon
                    :name="['fas', 'circle-check']"
                    size="lg"
                />
            </div>
            <p class="m-0 text-sm font-semibold text-primary">
                {{ event.name }}
            </p>
            <h1 class="mt-2 mb-0 text-2xl font-bold">
                {{ $t('team.forms.public.confirmation_title') }}
            </h1>
            <p class="mt-3 mb-0 text-sm leading-6 text-muted">
                {{
                    $t('team.forms.public.confirmation_body', {
                        form: form.name,
                    })
                }}
            </p>
        </Card>
    </main>
</template>
