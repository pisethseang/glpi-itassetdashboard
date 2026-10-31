# Searchable filters — GLPI 12 UAT build

Based on the previously working `itassetdashboard_glpi12_speedfix.zip`.

- No Summary tab, no new reports, and no database schema changes.
- Hardware filter dropdowns: Status, Department, OS, Manufacturer, Assignment.
- Software filter dropdowns: Software Name, Publisher, Computer, Department, Category.
- Existing select elements are retained for form submission and for no-JavaScript fallback.
- Type in a dropdown to narrow options. Click a result to choose it; use Filter to apply.
- Arrow keys, Enter, Escape and Tab supported. No third-party JS dependencies.
- This improves usability; it does not change initial server-side option-query performance.
- Test on Docker Desktop/UAT before replacing the LIVE plugin.
