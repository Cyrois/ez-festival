import { ref, watch } from 'vue';

export const sidebarStorageKey = 'artist-tree.sidebar-collapsed';

export const readSidebarCollapsed = (storage = globalThis.localStorage) => {
    try {
        return storage?.getItem(sidebarStorageKey) === 'true';
    } catch {
        return false;
    }
};

export const writeSidebarCollapsed = (
    collapsed,
    storage = globalThis.localStorage,
) => {
    try {
        storage?.setItem(sidebarStorageKey, String(collapsed));
    } catch {
        // The preference is optional; keep the in-memory state.
    }
};

export const shouldUseCompactSidebar = (collapsed, hovered) =>
    collapsed && !hovered;

export const useSidebarCollapsed = ({ enabled = true } = {}) => {
    const collapsed = ref(enabled && readSidebarCollapsed());

    watch(collapsed, (value) => {
        if (enabled) {
            writeSidebarCollapsed(value);
        }
    });

    return collapsed;
};
