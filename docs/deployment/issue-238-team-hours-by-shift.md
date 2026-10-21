# Team hours: By shift

Deploy application code and compiled assets together after #233, #234, #235 and #236. No migration, backfill, new index, persistent cache, temporary nullability, or schema rollback is needed.

Calvin confirmed on October 20, 2026 that By shift loads every current-event shift for one selected day and the complete assigned roster of one opened shift, without pagination, search or user sorting. This narrow list exception is recorded in AGENTS.md. With no explicit day, the report starts on today in the event's timezone, even outside event dates. The selected view and day live in the URL and Inertia history so same-tab member visits and browser Back restore them.

The locked desktop and phone frames are 64d and 64k:
- https://drive.google.com/file/d/1JQfR8crFjsPL3pGUZRnzHLv_JPYr1Nbv/view
- https://drive.google.com/file/d/1dMcDmiFBvI9vXycivsBbKNxFc7W7NbL4/view

Both GET endpoints require team.hours_report.view, resolve the current event on every request, and remain readable on locked events. A report-only viewer can read supervisor and member names but receives no Team member links without team.view. No scheduling permission is needed. Approved and Unapproved remain unavailable until #205. No pay rates or money are read or returned, and an opened shift contains no group summary or per-person shift breakdown.

Day metadata and shift people each use one SQL projection over the shared personal-break calculation. Assignments count on their shift's event-local start day; retained assignments continue to count after a member leaves Hired, including net-zero assignments. Unassigned shifts remain present with zero people, zero minutes and no supervisor. PostgreSQL and SQLite use the existing driver-specific duration calculation.

Successful day and people responses, including empty responses, are cached in memory only for the page visit. View switching preserves By group selections and loaded results. No prefetch or automatic network refresh is introduced. Pending requests are deduplicated; failed responses can be retried; stale responses cannot replace another day, shift or event. A successful people fetch reconciles that shift's displayed count, supervisor and hours locally so its visible row agrees with its people even if assignments changed after the original day fetch. Leaving the page or changing events discards the cache.

The shared DataTable supports one inline expanded Vue slot and keeps nested tables' mobile layouts independent. By shift uses the summary mobile layout for both shifts and people. CSV export (#239), money (#190), worked-hours calculations (#205), and other report phone screens (#240) remain outside this slice.

Navigation update (Calvin, October 20, 2026): person names and member row links/chevrons open the Team member page in a new tab with team.view. Only shift names open the existing shift page in a new tab with scheduling.view; otherwise names remain plain. Clicking a shift row or its expand/collapse button toggles the inline roster; clicking the shift name link does not toggle it. This supersedes the earlier same-tab navigation description above. Report URLs still retain the selected filters.
