/** Query string for the Roles list. Search runs on the server. */
export function rolesQuery({ search = '' }) {
    const term = search.trim();
    return term === '' ? {} : { search: term };
}
