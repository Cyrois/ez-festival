import { router } from '@inertiajs/vue3';

const interactiveSelector = 'a, button, input, select, textarea';

export const activateDataTableRow = (row, rowData, action) => {
    row.classList.add('cursor-pointer');
    row.dataset.rowLink = '';
    row.addEventListener('click', (event) => {
        if (
            event.target.closest(interactiveSelector) ||
            window.getSelection()?.toString()
        ) {
            return;
        }

        action(rowData, event);
    });
};

export const navigateDataTableRow = (
    row,
    rowData,
    href,
    { newTab = false } = {},
) => {
    activateDataTableRow(row, rowData, (item, event) => {
        const destination = href(item);

        if (newTab) {
            window.open(destination, '_blank', 'noopener');
            return;
        }

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

        if (newTab) window.open(href(rowData), '_blank', 'noopener');
        else window.open(href(rowData), '_blank');
    });
};
