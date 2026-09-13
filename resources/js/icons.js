import { library } from '@fortawesome/fontawesome-svg-core';
import {
    faCheck,
    faChevronDown,
    faCircleExclamation,
    faCircleInfo,
    faEllipsis,
    faInbox,
    faMagnifyingGlass,
    faPen,
    faPencil,
    faPlus,
    faTrash,
    faTrashCan,
    faUser,
    faXmark,
} from '@fortawesome/free-solid-svg-icons';

/**
 * Curated Font Awesome Free icons for the UI kit.
 * Add icons here as needed — do not import entire fas/far/fab packs.
 */
export const kitIcons = [
    faCheck,
    faChevronDown,
    faCircleExclamation,
    faCircleInfo,
    faEllipsis,
    faInbox,
    faMagnifyingGlass,
    faPen,
    faPencil,
    faPlus,
    faTrash,
    faTrashCan,
    faUser,
    faXmark,
];

library.add(...kitIcons);
