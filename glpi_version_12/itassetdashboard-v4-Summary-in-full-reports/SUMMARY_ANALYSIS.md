# Summary analysis (UAT candidate)

Built from `itassetdashboard_searchable_dropdowns_UAT.zip`. No edits to existing hardware/software data functions or searchable JavaScript. New Summary tab uses 4 internal sections: Application Matrix, Version Analysis, Computer Explorer, Inventory Audit. Drill-down links stay inside Summary and page 20 records at a time.

**Audit caveat:** stale computer records uses `glpi_computers.date_mod`, not GLPI Agent's last inventory time. Departments use the assigned user's `usercategories_id`, matching current plugin filters.

Install on UAT only after backing up the current plugin directory. Do not assume the GLPI 10/11/12 compatibility declaration equals runtime certification.

## Safe-fix notes
- Software join requires `isv.itemtype='Computer'`.
- SQL failures are logged as `[itassetdashboard summary]` and show empty results rather than crashing the page.
- Summary header does not force a GLPI sub-menu key.
- GLPI integration testing still required; if an unexpected error persists, inspect the PHP error log.
