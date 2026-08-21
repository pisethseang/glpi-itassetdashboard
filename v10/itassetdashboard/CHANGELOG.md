# IT Asset Dashboard — Changelog

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
