<script setup>
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Forgot password" />

    <div class="page">
        <div class="shell">
            <div class="brand">
                <div class="mark" aria-hidden="true">AT</div>
                <h1>Artist Tree</h1>
            </div>

            <div class="card">
                <h2>Forgot password</h2>
                <p class="lead">
                    Password reset is not enabled yet. Contact your organization admin if you need access.
                </p>
                <Link class="back" href="/login">Back to sign in</Link>
            </div>
        </div>
    </div>
</template>

<style scoped>
.page {
    --white: #ffffff;
    --charcoal: #1a1a1a;
    --teal: #1f7a74;
    --blue: #3d6b8a;
    --muted: #6b7280;
    --border: #e5e7eb;
    --page: #f4f5f7;

    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    padding: 24px;
    background: var(--page);
    color: var(--charcoal);
    font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
    -webkit-font-smoothing: antialiased;
}

.shell {
    width: 100%;
    max-width: 400px;
}

.brand {
    text-align: center;
    margin-bottom: 28px;
}

.mark {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: var(--teal);
    color: white;
    font-weight: 700;
    font-size: 15px;
    margin-bottom: 12px;
}

.brand h1 {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
    letter-spacing: -0.02em;
}

.card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 32px 28px 28px;
}

.card h2 {
    margin: 0 0 6px;
    font-size: 22px;
    font-weight: 700;
    letter-spacing: -0.02em;
}

.lead {
    margin: 0 0 24px;
    font-size: 14px;
    color: var(--muted);
    line-height: 1.45;
}

.back {
    color: var(--blue);
    text-decoration: none;
    font-weight: 700;
    font-size: 14px;
}

.back:hover {
    text-decoration: underline;
}
</style>
