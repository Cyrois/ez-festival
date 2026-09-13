import { ref } from 'vue';
import { trans } from 'laravel-vue-i18n';

const message = ref('');
const title = ref('');
const variant = ref('info');
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

    const show = ({
        variant: nextVariant = 'info',
        title: nextTitle = '',
        message: nextMessage = '',
        duration = 4000,
    } = {}) => {
        variant.value = nextVariant;
        title.value = nextTitle;
        message.value = nextMessage;
        visible.value = true;
        if (dismissTimer) {
            clearTimeout(dismissTimer);
        }
        if (duration > 0) {
            dismissTimer = setTimeout(() => {
                visible.value = false;
                dismissTimer = null;
            }, duration);
        }
    };

    const showError = (text, heading = '') => {
        show({
            variant: 'error',
            title: heading || trans('setup.toast.error_title'),
            message: text || trans('setup.errors.generic'),
        });
    };

    const showSuccess = (text, heading = '') => {
        show({
            variant: 'success',
            title: heading || trans('setup.toast.saved_title'),
            message: text,
        });
    };

    const showInfo = (text, heading = '') => {
        show({
            variant: 'info',
            title: heading,
            message: text,
        });
    };

    const showFormError = (errors) => {
        const values = Object.values(errors ?? {});
        let first = values[0];
        if (Array.isArray(first)) {
            first = first[0];
        }
        showError(
            typeof first === 'string' && first
                ? first
                : trans('setup.errors.generic'),
        );
    };

    return {
        message,
        title,
        variant,
        visible,
        show,
        showError,
        showSuccess,
        showInfo,
        showFormError,
        dismiss,
    };
}
