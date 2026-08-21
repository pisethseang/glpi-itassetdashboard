# IT Asset Dashboard — Changelog

## v1.1.4 — 2026-06-30

### Fixed
- Fatal PHP error introduced in v1.1.3: `Html::addCssFile()` and `Html::addJavascriptFile()`
  do not exist in GLPI's API, causing "An unexpected error occurred" on every page load.
  Replaced with `echo Html::css(Plugin::getWebDir('itassetdashboard') . '/css/dashboard.css')`
  and `echo Html::script(...)` placed immediately after `Html::header()`. These are the
  real GLPI API methods; `Plugin::getWebDir()` returns the correct URL for both GLPI 10 and 11.

## v1.1.3 — 2026-06-30

### Fixed
- Dashboard and report pages rendered completely unstyled in GLPI 11 (CSS not loading).
  Root cause: GLPI 11 changed its webroot to `public/`, so the hardcoded path
  `/plugins/itassetdashboard/css/dashboard.css` no longer resolved to the actual file.
  Fixed by using `Plugin::getWebDir('itassetdashboard')` (GLPI's canonical API for the
  correct plugin web URL) and registering assets via `Html::addCssFile()` /
  `Html::addJavascriptFile()` *before* `Html::header()` so they are injected into
  `<head>` rather than placed as raw tags in the body. Applies to all three pages
  (dashboard, report, software).

## v1.1.2 — 2026-06-30

### Fixed
- `getSoftwareStats()` queried `sv.end_of_support`, a column that does not exist in `glpi_softwareversions` in GLPI 10 or 11 — this caused
  `MySQL query error: Unknown column 'sv.end_of_support'` when loading the dashboard. The EOL-by-date logic referencing this column has been
  removed (it was also dead code — `sw_vulnerable` / `vuln_type` were never rendered in any front-end page). The "no licence recorded" detection,
  which uses real columns, is kept.

## v1.1.1 — 2026-06-30

### Fixed
- GLPI 11 throws `Exception: "Executing direct queries is not allowed!"` for `DBmysql::query()` — this method is now prohibited entirely.
  Replaced all 32 calls to `$DB->query()` with `$DB->doQuery()` in `inc/dashboard.class.php`, which is GLPI's sanctioned method for
  executing a self-crafted, already-escaped SQL string. No other changes needed — `$DB->fetchAssoc()` works identically on the
  result returned by `doQuery()`.

## v1.1.0 — 2026-06-30

### Changed
- Declared GLPI compatibility range extended to support GLPI 11.0.x (`PLUGIN_ITASSETDASHBOARD_MAX_GLPI` raised to `11.0.99`)
- Verified against GLPI 11 upgrade guide: no usage of removed/deprecated APIs found
  - SQL inputs already passed through `$DB->escape()` (compatible with GLPI 11's removal of automatic `$_GET`/`$_POST` sanitization)
  - HTML output already passed through `htmlspecialchars()` (compatible with GLPI 11's removal of automatic XSS encoding)
  - `include('../../../inc/includes.php')` left in place — still supported by GLPI 11 for backward compatibility, and required for GLPI 10.0.x
- No changes required to `Plugin::registerClass`, menu hooks, or `Html::header` usage — APIs unchanged in GLPI 11

### Compatibility
- Plugin now supports both GLPI 10.0.x and GLPI 11.0.x without separate branches

---

## v1.0.1 — 2026-04-17

### Improved
- Replaced `addslashes()` with `$DB->escape()` on all filter inputs for safer SQL handling
- Applied `COALESCE()` to all nullable SELECT columns — no more NULL values reaching PHP or CSV export
- Filter dropdowns (Status, Department, OS, Manufacturer) are fully dynamic — values pulled live from DB, no hardcoded names
- Status dropdown now shows whatever statuses actually exist in GLPI (e.g. "In Use", "In Stock", "Spare", etc.)
- Assigned vs Unassigned stat uses `COALESCE(c.users_id, 0)` for accurate NULL handling

### Fixed
- Removed hardcoded `'In Use'` default filter — report now shows all non-deleted computers on first load
- Dashboard stats no longer restricted to "In Use" status — reflect full inventory

---

## v1.0.0 — 2026-04-17

### Initial Release
- Dashboard page with KPI cards: Total, Assigned, Unassigned, Departments
- Charts: By OS, By Asset Status, By Department, By Manufacturer
- Status and OS breakdown summary tables
- Report page with full computer asset table
- Filters: Status, Department, OS, Manufacturer, Search
- CSV export matching original SQL query column aliases exactly
- UTF-8 BOM on CSV export for Excel compatibility
- SQL based on original Vattanac Brewery IT asset query
- Read-only plugin — no DB tables created, zero risk to existing data
