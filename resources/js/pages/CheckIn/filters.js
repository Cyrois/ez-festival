export function checkInQuery({ type, pass, search }) {
    return {
        type: type || 'all',
        pass: pass || undefined,
        search: search?.trim() || undefined,
    };
}
