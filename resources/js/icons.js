import { library } from '@fortawesome/fontawesome-svg-core';
import {
    faCheck,
    faChevronDown,
    faCircleExclamation,
    faCircleInfo,
    faEllipsis,
    faEllipsisVertical,
    faMagnifyingGlass,
    faPen,
    faPlus,
    faTrash,
    faUser,
    faXmark,
} from '@fortawesome/free-solid-svg-icons';
import { faUser as farUser } from '@fortawesome/free-regular-svg-icons';

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
    faEllipsisVertical,
    faMagnifyingGlass,
    faPen,
    faPlus,
    faTrash,
    faUser,
    faXmark,
    farUser,
];

library.add(...kitIcons);

/**
 * @param {string} name Icon name without prefix (e.g. "check")
 * @param {'fas' | 'far' | 'fab'} [prefix='fas']
 * @returns {[string, string]}
 */
export function icon(name, prefix = 'fas') {
    return [prefix, name];
}
