export function checkInQuery({ type, pass, status, search }) {
    return {
        type: type || 'all',
        pass: pass || undefined,
        status: status || 'all',
        search: search?.trim() || undefined,
    };
}
