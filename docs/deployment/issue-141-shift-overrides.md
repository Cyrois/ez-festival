# Shift assignment overrides (#141)

Deploy `2026_10_05_000002_allow_shift_assignment_overrides.php` with the application and built assets. It makes `shift_assignments.role_id` permanently nullable for direct shift overrides. Role slots still require a role. No existing rows are remapped or backfilled; assignment identities, personal breaks, foreign keys, interval checks and uniqueness remain intact.

An override has neither a role slot nor an assignment role. It increments the assigned-person total without changing required headcount or a role slot's vacancies. Existing detached assignments retain their historical role and extra status. Create, Edit and Copy keep roster changes pending until the page's Create/Save action.

Old application code assumes every assignment has a role. Do not roll application code back while role-free overrides exist. The migration refuses rollback in that case rather than deleting people or inventing roles; use a forward fix. With no role-free assignments it can safely restore the required role column.

This release does not introduce optional-role headcount rows, supervisors, new permissions or navigation. Overrides use the existing scheduling permission and event-lock gates. Event and shift deletion retain their existing cascade behavior; Team member deletion remains restricted while assigned. There is no temporary nullability.
