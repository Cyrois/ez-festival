import { ref } from 'vue';
import { trans } from 'laravel-vue-i18n';

const message = ref('');
const visible = ref(false);
let dismissTimer = null;

export function useFlashToast() {
    const dismiss = () => {
        visible.value = false;
        if (dismissTimer) {
            clearTimeout(dismissTimer);
            dismissTimer = null;
        }
    };

    const showDanger = (text) => {
        message.value = text || trans('setup.errors.generic');
        visible.value = true;
        if (dismissTimer) {
            clearTimeout(dismissTimer);
        }
        dismissTimer = setTimeout(() => {
            visible.value = false;
            dismissTimer = null;
        }, 4000);
    };

    const showFormError = (errors) => {
        const values = Object.values(errors ?? {});
        let first = values[0];
        if (Array.isArray(first)) {
            first = first[0];
        }
        showDanger(
            typeof first === 'string' && first
                ? first
                : trans('setup.errors.generic'),
        );
    };

    return {
        message,
        visible,
        showDanger,
        showFormError,
        dismiss,
    };
}
