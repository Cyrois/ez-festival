# Team hours: All groups overview

Deploy application code and compiled assets together. No migration, backfill, persistent cache, or temporary nullability is introduced. The required Team groups migration from #242 must already be applied.

Calvin confirmed this slice as overview-only: every configured group, including zero-hour groups, in name order, without a "No group" row. The aggregate DataTable deliberately has no pagination, search, or user sorting. Blank dates cover all scheduled days; filtering requires a valid pair of dates and uses the shift's event-local start day, including dates outside the event dates. Approved and Unapproved remain unavailable until #205.

Group selection and Back belong to #236, By shift to #238, and Export to #239. The later controls in the design frames are intentionally omitted; loading, Retry, and empty states are adapted to the overview. The report is readable on locked events with `team.hours_report.view` (admins also pass).

The data endpoint executes one grouped hours query. Its event total is the sum of the same group aggregates; no people, days, shifts, or pay are returned. Successful responses are remembered only for the page visit and event/date range. Failed responses are not cached. Application rollback needs no schema rollback.
