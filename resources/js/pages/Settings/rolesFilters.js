export const ROLE_STATUSES = ['on', 'off', 'all'];

export const DEFAULT_ROLE_STATUS = 'on';

/**
 * Query string for the Roles list. Leaves out the defaults so the plain
 * /settings/roles URL means "On roles, no search".
 *
 * @param {{ search?: string, status?: string }} filters
 * @returns {Record<string, string>}
 */
export function rolesQuery({ search = '', status = DEFAULT_ROLE_STATUS }) {
    const query = {};
    const term = search.trim();

    if (term !== '') {
        query.search = term;
    }

    if (ROLE_STATUSES.includes(status) && status !== DEFAULT_ROLE_STATUS) {
        query.status = status;
    }

    return query;
}
