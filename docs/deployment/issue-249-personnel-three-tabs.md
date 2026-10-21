# Personnel hours: three report tabs

Deploy application code and compiled assets together. No migration, backfill, persistent data cache, or temporary nullability is introduced. Rollback uses the previous code and assets.

Summary, By team and By shift share the report second navigation row. Summary retains event totals and configured teams; a team opens By team directly. By team retains one selected team, with no sub-page or Back button. The date range carries between Summary and By team while By shift retains its independent day. Existing successful report requests remain cached for the page visit.

The URL accepts view=summary|team|shift. Without view it opens Summary, except a legacy group_id link opens By team. The last selected team is remembered in browser storage per signed-in user and event; a removed remembered team shows the shared Choose a team empty state and picker. A first-time viewer starts with the first team in the event catalogue, ordered by name. Disabled browser storage does not prevent report use.

The locked 64*3 designs guide navigation. Pay, cost, employment filtering, CSV/email and currency configuration are outside this prerequisite and belong to #190 or its follow-ups. The existing Personnel terminology, permissions and hours calculations remain unchanged.
