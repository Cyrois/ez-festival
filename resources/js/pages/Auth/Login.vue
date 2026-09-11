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
    <Head title="Sign in" />

    <div class="login-page">
        <div class="shell">
            <div class="brand">
                <div class="mark" aria-hidden="true">AT</div>
                <h1>Artist Tree</h1>
            </div>

            <div class="card">
                <h2>Sign in</h2>
                <p class="lead">Office access for your festival organization.</p>

                <form @submit.prevent="submit">
                    <div class="field">
                        <label class="field-label" for="email">Email</label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            name="email"
                            autocomplete="username"
                            required
                            autofocus
                        />
                        <p v-if="form.errors.email" class="error">{{ form.errors.email }}</p>
                    </div>

                    <div class="field">
                        <label class="field-label" for="password">Password</label>
                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            name="password"
                            autocomplete="current-password"
                            required
                        />
                        <p v-if="form.errors.password" class="error">{{ form.errors.password }}</p>
                    </div>

                    <div class="row">
                        <label class="check">
                            <input v-model="form.remember" type="checkbox" name="remember" />
                            Remember me
                        </label>
                        <Link href="/forgot-password">Forgot password?</Link>
                    </div>

                    <button class="primary" type="submit" :disabled="form.processing">
                        Sign in
                    </button>
                </form>

                <p class="foot">No public site. Organization accounts only.</p>
            </div>
        </div>
    </div>
</template>

<style scoped>
.login-page {
    --white: #ffffff;
    --charcoal: #1a1a1a;
    --teal: #1f7a74;
    --teal-hover: #196560;
    --blue: #3d6b8a;
    --muted: #6b7280;
    --border: #e5e7eb;
    --page: #f4f5f7;
    --danger: #b91c1c;

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

.field {
    margin-bottom: 16px;
}

.field-label {
    display: block;
    font-size: 13px;
    font-weight: 700;
    color: var(--charcoal);
    margin-bottom: 6px;
}

input[type='email'],
input[type='password'] {
    width: 100%;
    height: 42px;
    padding: 0 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--white);
    font: inherit;
    font-size: 14px;
    color: var(--charcoal);
    outline: none;
    box-sizing: border-box;
}

input[type='email']:focus,
input[type='password']:focus {
    border-color: var(--teal);
    box-shadow: 0 0 0 3px rgba(31, 122, 116, 0.22);
}

.error {
    margin: 6px 0 0;
    font-size: 13px;
    color: var(--danger);
    line-height: 1.35;
}

.row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin: 4px 0 22px;
    font-size: 13px;
}

.check {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--muted);
    font-weight: 400;
    cursor: pointer;
}

.check input {
    width: 15px;
    height: 15px;
    accent-color: var(--teal);
}

a {
    color: var(--blue);
    text-decoration: none;
    font-weight: 700;
}

a:hover {
    text-decoration: underline;
}

button.primary {
    width: 100%;
    height: 42px;
    border: none;
    border-radius: 8px;
    background: var(--teal);
    color: white;
    font: inherit;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
}

button.primary:hover:not(:disabled) {
    background: var(--teal-hover);
}

button.primary:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.foot {
    margin-top: 20px;
    text-align: center;
    font-size: 12px;
    color: var(--muted);
}
</style>
