<script setup>
import { Icon } from '../ui/icon';
</script>
<template>
    <span
        class="inline-flex items-center gap-2 text-xs text-muted"
        :title="$t('permissions.hidden')"
    >
        <span
            aria-hidden="true"
            class="blur-sm select-none"
            >{{ $t('permissions.placeholder') }}</span
        >
        <Icon
            :name="['fas', 'lock']"
            size="sm"
        />
        <span>{{ $t('permissions.hidden') }}</span>
    </span>
</template>
