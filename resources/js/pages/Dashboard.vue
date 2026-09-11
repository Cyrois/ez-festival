<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);
</script>

<template>
    <Head title="Dashboard" />

    <div class="dash">
        <header class="bar">
            <div class="brand-row">
                <div class="mark" aria-hidden="true">AT</div>
                <span class="name">Artist Tree</span>
            </div>
            <Link method="post" href="/logout" as="button" class="logout">Sign out</Link>
        </header>

        <main class="main">
            <h1>Dashboard</h1>
            <p class="muted">
                Signed in as {{ user?.email ?? 'unknown' }}.
            </p>
        </main>
    </div>
</template>

<style scoped>
.dash {
    --teal: #1f7a74;
    --charcoal: #1a1a1a;
    --muted: #6b7280;
    --border: #e5e7eb;
    --page: #f4f5f7;

    min-height: 100vh;
    background: var(--page);
    color: var(--charcoal);
    font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
}

.bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 24px;
    background: #fff;
    border-bottom: 1px solid var(--border);
}

.brand-row {
    display: flex;
    align-items: center;
    gap: 10px;
}

.mark {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: var(--teal);
    color: white;
    font-weight: 700;
    font-size: 12px;
}

.name {
    font-weight: 700;
    font-size: 15px;
}

.logout {
    border: 1px solid var(--border);
    background: #fff;
    border-radius: 8px;
    padding: 8px 12px;
    font: inherit;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    color: var(--charcoal);
}

.main {
    max-width: 720px;
    margin: 0 auto;
    padding: 40px 24px;
}

.main h1 {
    margin: 0 0 8px;
    font-size: 24px;
    font-weight: 700;
    letter-spacing: -0.02em;
}

.muted {
    margin: 0;
    color: var(--muted);
    font-size: 14px;
}
</style>
