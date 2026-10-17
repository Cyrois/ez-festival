<script setup>
import { Button } from '../button';
import { Icon } from '../icon';
import { UnsavedChangesDialog } from '../unsaved-changes-dialog';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineOptions({ inheritAttrs: false });
const props = defineProps({
    href: { type: String, required: true },
    page: { type: String, required: true },
    form: { type: Object, default: null },
    dirty: { type: Boolean, default: undefined },
    restrictContext: { type: Boolean, default: false },
});
const emit = defineEmits(['save']);
const currentPage = usePage();
const navigation = computed(() =>
    !props.restrictContext ||
    currentPage.props.returnNavigation?.href.split('?')[0] ===
        props.href.split('?')[0]
        ? currentPage.props.returnNavigation
        : null,
);
</script>

<template>
    <Button
        v-bind="$attrs"
        :href="navigation?.href ?? href"
        :disabled="form?.processing"
        variant="ghost"
        size="sm"
        class="mb-3 self-start px-2.5"
        data-unsaved-navigation
    >
        <Icon
            :name="['fas', 'arrow-left']"
            size="sm"
            class="mr-1.5"
        />
        {{
            $t('navigation.back_to', {
                page: navigation ? $t(navigation.page) : page,
            })
        }}
    </Button>
    <UnsavedChangesDialog
        v-if="form"
        :dirty="dirty ?? form.isDirty"
        :busy="form.processing"
        @save="emit('save', $event)"
    />
</template>
