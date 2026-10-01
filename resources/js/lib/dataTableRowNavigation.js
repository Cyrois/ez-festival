import { router } from '@inertiajs/vue3';

const interactiveSelector = 'a, button, input, select, textarea';

export const navigateDataTableRow = (row, rowData, href) => {
    row.classList.add('cursor-pointer');
    row.dataset.rowLink = '';
    row.addEventListener('click', (event) => {
        if (
            event.target.closest(interactiveSelector) ||
            window.getSelection()?.toString()
        ) {
            return;
        }

        const destination = href(rowData);

        if (event.metaKey || event.ctrlKey) {
            window.open(destination, '_blank');
            return;
        }

        router.get(destination);
    });
    row.addEventListener('auxclick', (event) => {
        if (
            event.button !== 1 ||
            event.target.closest(interactiveSelector) ||
            window.getSelection()?.toString()
        ) {
            return;
        }

        window.open(href(rowData), '_blank');
    });
};
