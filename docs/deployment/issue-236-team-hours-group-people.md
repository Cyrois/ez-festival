# Team hours: selected group people

Deploy application code and compiled assets together after the #235 overview. No migration, backfill, persistent cache, new indexes, or temporary nullability is introduced. Rollback needs no schema change.

Calvin confirmed that this selected-group report loads the complete roster once per event/group/date range, including members without assignments at zero hours. This narrow people-list pagination exception is recorded in AGENTS.md. All membership statuses are included. Scheduled hours use the existing grouped SQL calculation and shift start day, with a free inclusive date range or both dates blank for all scheduled days. The group's cards sum the same people rows. Approved and Unapproved remain unavailable until #205, and no pay rates or money are selected or returned.

The supplied group-first frame guides the Group picker, Back to All groups, date fields, group totals, and people table. There is no No group bucket (#234/#242). Person drill-down (#237), By shift (#238), export (#239), and dedicated phone layout (#240) remain out of scope.

The new GET endpoint requires team.hours_report.view (admins also pass), scopes the selected group to the current event, and remains readable on locked events. Each successful response stays in memory only for the page visit; failed responses can be retried and are never cached. External edits do not automatically refresh already loaded report results, so separately fetched overview and detail snapshots can differ after concurrent changes.
