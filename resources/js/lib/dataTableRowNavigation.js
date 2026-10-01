import { router } from '@inertiajs/vue3';

const interactiveSelector = 'a, button, input, select, textarea';

export const navigateDataTableRow = (row, rowData, href) => {
    row.classList.add('cursor-pointer');
    row.addEventListener('click', (event) => {
        if (event.target.closest(interactiveSelector)) {
            return;
        }

        router.get(href(rowData));
    });
};
