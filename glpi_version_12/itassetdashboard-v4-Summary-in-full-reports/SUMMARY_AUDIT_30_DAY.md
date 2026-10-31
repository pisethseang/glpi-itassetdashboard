# Summary Audit: last GLPI inventory date

- Based on the last known working Summary DB scope fix; existing Hardware/Software PHP unchanged.
- The Audit uses `glpi_computers.last_inventory_update` (actual inventory timestamp), never `date_mod`.
- 30-day stale: non-null inventory timestamp older than `NOW() - INTERVAL 30 DAY`.
- Never inventoried: null or zero timestamp. These are separate from stale unless combined.
- Counts exclude deleted computers, detail is paginated and optionally filters by hostname/username and user-category department.
- Searchable summary dropdowns reuse the existing dependency-free dropdown JavaScript; the Summary stylesheet is embedded.
- If the column does not exist, Audit shows an unavailable message rather than silently falling back to date_mod.
- Validate on GLPI 12 UAT and check GLPI PHP logs if any query errors are shown.
