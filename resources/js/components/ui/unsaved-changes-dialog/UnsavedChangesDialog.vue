<script setup>
import { toRef } from 'vue';
import { Button } from '../button';
import { Dialog } from '../dialog';
import { useUnsavedNavigation } from '../../../composables/useUnsavedNavigation';

const props = defineProps({
    dirty: { type: Boolean, default: false },
    busy: { type: Boolean, default: false },
});
const emit = defineEmits(['save']);
const { open, continueNavigation, cancel } = useUnsavedNavigation(
    toRef(props, 'dirty'),
);

// The page receives a continuation callback and calls it only after saving
// successfully. Validation or request failures leave the warning open.
const save = () => emit('save', continueNavigation);
</script>

<template>
    <Dialog
        v-model:open="open"
        :title="$t('ui.unsaved_changes.title')"
        :description="$t('ui.unsaved_changes.description')"
        :show-cancel="false"
        :show-confirm="false"
        :busy="busy"
        sectioned
        focus-trap
        @cancel="cancel"
    >
        <div class="mt-5 flex flex-wrap justify-end gap-3">
            <Button
                type="button"
                variant="outline"
                :disabled="busy"
                @click="continueNavigation"
                >{{ $t('ui.unsaved_changes.continue') }}</Button
            >
            <Button
                type="button"
                :loading="busy"
                @click="save"
                >{{ $t('ui.unsaved_changes.save_continue') }}</Button
            >
        </div>
    </Dialog>
</template>
