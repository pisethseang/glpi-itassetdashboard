GLPI 12 Summary hotfix (2026-10-27)

- Declare global $DB before initializing the Summary query helpers.
- Inline Summary CSS to avoid a missing stylesheet URL.
- Log unsuccessful query results.
- Hardware and Software reports are unchanged from the preceding build.

Test in Docker Desktop before production deployment.
