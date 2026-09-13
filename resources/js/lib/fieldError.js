import { trans } from 'laravel-vue-i18n';

/**
 * Inline field message: empty value → Required i18n; else Laravel message.
 *
 * @param {{ errors: Record<string, string>, [key: string]: unknown }} form
 * @param {string} key
 * @returns {string}
 */
export function fieldError(form, key) {
    const error = form.errors[key];
    if (!error) {
        return '';
    }

    const value = form[key];
    if (value === '' || value === null || value === undefined) {
        return trans('setup.errors.required');
    }

    return error;
}

/**
 * Toast: if every errored field is empty → required_fields copy; else first real message.
 *
 * @param {{ [key: string]: unknown }} form
 * @param {Record<string, string|string[]>|undefined} errors
 * @param {{ showError: (text: string) => void, showFormError: (errors: object) => void }} toast
 */
export function toastFormErrors(form, errors, { showError, showFormError }) {
    const keys = Object.keys(errors ?? {});
    const allEmpty =
        keys.length > 0 &&
        keys.every((key) => {
            const value = form[key];
            return value === '' || value === null || value === undefined;
        });

    if (allEmpty) {
        showError(trans('setup.errors.required_fields'));
        return;
    }

    showFormError(errors);
}
