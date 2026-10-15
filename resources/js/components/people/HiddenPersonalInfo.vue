<script setup>
import { Icon } from '../ui/icon';
import { trans } from 'laravel-vue-i18n';
</script>
<template>
    <span
        class="inline-flex items-center gap-2 text-xs text-muted"
        :title="trans('permissions.hidden')"
    >
        <span
            aria-hidden="true"
            class="blur-sm select-none"
            >{{ trans('permissions.placeholder') }}</span
        >
        <Icon
            :name="['fas', 'lock']"
            size="sm"
        />
        <span>{{ trans('permissions.hidden') }}</span>
    </span>
</template>
