# IT Asset Dashboard v1.2.0 — Compatibility candidate

This build retains the original dashboard, hardware and software reports.

## Changes
- Advertises GLPI 10.0.0–12.0.99 compatibility (runtime compatibility is not yet proven).
- Uses the GLPI configured `root_doc` for dashboard and report links.
- Protects exported data cells from CSV formula injection.
- Removes unnecessary HTTP referer bypass flags from GET-only report pages.

## Test before production
1. Back up your original plugin and database. Install only on UAT first.
2. In Setup > Plugins, install/upgrade and enable IT Asset Dashboard.
3. Verify Dashboard, Hardware Report, Software Report and both CSV exports.
4. Test with a restricted account and across GLPI entities; original reporting SQL is not fully entity-scoped. Do not expose to accounts that must not see inventory of other entities.
5. Validate queries against your GLPI schema, notably `glpi_items_disks`, software inventory and OS tables.
6. Test separately on GLPI 10, 11 and 12 before declaring production support.

## Remaining limitations
- Original reporting uses raw SQL and reads whole result sets into memory.
- External Chart.js CDN dependency remains.
- Some GLPI 12 schema differences may require additional changes.
- No GLPI application-level integration tests have been run as part of this package.
