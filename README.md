# IT Asset Dashboard for GLPI

A hardware and software inventory dashboard for **GLPI 10, 11, and 12**, developed by **Piseth SEANG**.

[![GLPI 10](https://img.shields.io/badge/GLPI-10-blue)](glpi_version_10/itassetdashboard)
[![GLPI 11](https://img.shields.io/badge/GLPI-11-blue)](glpi_version_11/itassetdashboard)
[![GLPI 12](https://img.shields.io/badge/GLPI-12-blue)](glpi_version_12/itassetdashboard)
![License](https://img.shields.io/badge/License-GPLv2%2B-green)

**Repository:** https://github.com/pisethseang/glpi-itassetdashboard

> [!IMPORTANT]
> This plugin reports on data already recorded in GLPI. It does **not** perform real-time endpoint scans, enforce software policy, or measure live antivirus protection. Its results depend on the freshness and completeness of GLPI inventory records.

## Overview

**IT Asset Dashboard** provides a single place for IT administrators to review computer inventory, assigned users, hardware characteristics, installed software, and selected inventory-health indicators.

The GLPI 12 implementation exposes four sections under the **Tools** menu:

| Section | What it provides |
| --- | --- |
| **Dashboard** | Hardware and software KPI cards, charts, and inventory-health summaries. |
| **Hardware Report** | Searchable and sortable computer inventory, with CSV export. |
| **Software Report** | Installed software by computer, with filters, sorting, pagination, and CSV export. |
| **Summary** | Application deployment matrix, version analysis, computer explorer, and inventory audit. |

## Features

### 1. Inventory dashboard

- Total, assigned, and unassigned computers.
- Computer breakdown by **operating system**, **status**, **department**, and **manufacturer**.
- Software title and installation counts, top installed applications, and publisher distribution.
- Clickable charts that open corresponding filtered reports.
- Lists of older computers based on GLPI purchase dates.

### 2. Hardware report

Review recorded fields including computer name, status, serial number, model, manufacturer, assigned employee, staff ID, department, position, operating system, and OS version.

Filter by status, department, OS, manufacturer, assignment, or search text. Sort the table and export matching results as **CSV**.

### 3. Software report

Review installations associated with computers, including software name and version, publisher, category, associated computer, assigned user/department, and installation date when recorded.

Filter by application, publisher, computer, department, software category, or search text. The software table supports sorting, pagination (10, 20, 50, or 100 rows per page), and **CSV export**.

### 4. Inventory health indicators

| Indicator | How it is calculated in the GLPI 12 source |
| --- | --- |
| **Cisco Secure Endpoint presence** | Checks whether the installed-software inventory contains the exact title `Cisco Secure Endpoint` for each computer. |
| **C: disk warning** | Less than 15% free space on recorded disks with mount point `C:`. |
| **C: disk critical** | Less than 10% free space on recorded disks with mount point `C:`. |
| **Older computers** | Computers whose recorded purchase date is at least five years old. |

**Important interpretation:** An inventory record showing Cisco Secure Endpoint installed does not prove that its service is running or that endpoint protection is healthy. A missing installation record may also reflect stale or incomplete inventory. Disk statistics are based on last-reported GLPI disk data, not live disk telemetry.

### 5. Summary and audit

The **Summary** page offers four analysis areas:

1. **Application Matrix** — applications installed across departments.
2. **Version Analysis** — version distribution for recorded applications.
3. **Computer Explorer** — hardware and software information grouped by computer.
4. **Inventory Audit** — review of stale, missing, or inconsistent inventory information.

The available results depend on software installations and computer records already present in GLPI.

## Version compatibility

This repository maintains separate plugin directories. Versions below are taken from each directory's `setup.php` on the `main` branch (checked 26 November 2026).

| Source directory | Plugin version | Declared GLPI range |
| --- | --- | --- |
| [`glpi_version_10/itassetdashboard`](glpi_version_10/itassetdashboard) | `1.0.1` | 10.0.0 – 10.0.99 |
| [`glpi_version_11/itassetdashboard`](glpi_version_11/itassetdashboard) | `1.1.4` | 10.0.0 – 11.0.99 |
| [`glpi_version_12/itassetdashboard`](glpi_version_12/itassetdashboard) | `1.2.0` | 10.0.0 – 12.0.99 |

For a new deployment, **select the directory intended for your GLPI major version**. The declared version range is metadata; it does not substitute for testing the chosen release on your own GLPI installation.

**Other requirements**

- A working GLPI installation and database with computer and, for software analytics, software inventory records.
- PHP and database versions supported by your GLPI release.
- A browser able to load **Chart.js 4.4.0** from `cdn.jsdelivr.net` for dashboard charts (the GLPI 12 Dashboard page loads this external script). In restricted networks, verify this dependency before deployment.
- A GLPI user with the **Computer: Read** permission. See the security notes below regarding entity isolation.

## Installation

### A. Download the repository

```bash
git clone https://github.com/pisethseang/glpi-itassetdashboard.git
cd glpi-itassetdashboard
```

### B. Choose your GLPI version

For **GLPI 12** on a standard Linux installation, copy the plugin directory to GLPI's `plugins` location:

```bash
sudo cp -a glpi_version_12/itassetdashboard /var/www/html/plugins/
```

For **GLPI 11** or **GLPI 10**, use `glpi_version_11/itassetdashboard` or `glpi_version_10/itassetdashboard` instead.

The final directory name **must be `itassetdashboard`**, not `glpi-itassetdashboard` or `glpi_version_12`:

```text
/var/www/html/plugins/itassetdashboard/
├── setup.php
├── hook.php
├── inc/
├── front/
└── css/
```

> Paths vary between GLPI packages, distributions, and Docker images. Substitute the actual GLPI installation and plugin paths in your environment.

### C. Docker Desktop / Docker example

If your GLPI web container is named `glpi` and the directory is **not bind-mounted**:

```bash
# Run from the cloned repository on the Docker host.
docker cp glpi_version_12/itassetdashboard glpi:/var/www/html/plugins/
```

If `plugins/` is bind-mounted from your host, copy the files into the **host-mounted plugins directory** instead. For persistent deployment, use a volume/bind mount so a container re-creation does not discard the plugin.

### D. Install and enable in GLPI

1. Sign in with an administrator account.
2. Open **Setup → Plugins** (the menu location may vary by GLPI version).
3. Find **IT Asset Dashboard**.
4. Select **Install**, then **Enable** if those actions are offered.
5. Open **Tools → IT Asset Dashboard**.

The GLPI 12 `hook.php` install and uninstall functions return success without creating custom plugin tables.

## How to use

### Dashboard

Open **Tools → IT Asset Dashboard → Dashboard**. Use the hardware and software KPIs, clickable graphs, and inventory-health cards to identify records needing review. Click a chart segment to open the matching filtered report where supported.

### Hardware Report

Open **Hardware Report**, choose filters, sort a column as required, and select **Export CSV** to download the filtered inventory.

### Software Report

Open **Software Report**, select an application, publisher, computer, department, or category, adjust rows per page, and export the matching software installation records to CSV.

### Summary

Use **Application Matrix**, **Version Analysis**, **Computer Explorer**, and **Inventory Audit** to investigate inventory coverage, software deployment, and discrepancies in the GLPI data.

## Data sources and interpretation

The plugin queries standard GLPI inventory data, including computer, user, model, manufacturer, operating system, software installation, disk, and financial/purchase-date records. It relies on GLPI software inventory relationships, not an independent scanner.

- **Department** is derived from the assigned user's GLPI user category (`glpi_usercategories`) in the inspected source. It is not necessarily a dedicated organizational department field.
- **Old computer** is based on `glpi_infocoms.buy_date` and a five-year threshold; it is **not** a warranty expiration check.
- **Cisco Secure Endpoint coverage** is based on an exact application-name match in installed software, **not** live security-agent state.
- **Disk alerts** use the recorded C: mount point; machines with other mount points or missing disk inventory may not appear.
- **Software inventory counts** may differ from unique computer counts because a computer can have multiple installed applications and versions.

## Security and privacy

> [!WARNING]
> **Entity isolation must be reviewed before production use.** The inspected GLPI 12 pages enforce `Session::checkRight('computer', READ)` and the menu is gated on the same right, but several reporting queries filter only non-deleted computers rather than consistently restricting records to the logged-in user's permitted GLPI entities. **Do not assume this release enforces complete multi-entity separation.** Test with restricted accounts and remediate the queries before deployment in environments requiring entity isolation.

Additional considerations:

- **CSV exports include employee-related fields** such as names, staff IDs, and departments; distribute exported files according to your organization's data-handling policy.
- The inspected report and summary pages read GLPI records and do not implement asset-editing workflows. The plugin's install/uninstall hooks do not create custom tables.
- The implementation escapes spreadsheet-formula prefixes in exported cell values to reduce CSV formula-injection risk.
- Keep GLPI and the plugin updated; verify file permissions and control who can access the reporting views.
- The Dashboard currently loads Chart.js from a third-party CDN. Review that dependency against organizational network and privacy policies.

## Upgrade an existing installation

1. Back up the current `itassetdashboard` plugin directory and the GLPI database according to your normal change procedure.
2. Identify your installed GLPI major version and download the matching plugin directory.
3. Replace the existing plugin files with that version's files. Avoid combining PHP files from multiple releases.
4. Review **Setup → Plugins** and apply any prompted update or activation step.
5. Clear GLPI caches if the updated interface does not appear; then test all four sections and CSV exports.
6. If you encounter problems, restore the backed-up directory and review GLPI/PHP logs.

For Docker deployments, also confirm whether the plugin folder is managed through a bind mount or a persistent volume.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| Plugin does not appear | Directory must be named `itassetdashboard`; confirm `setup.php` is directly inside it; check permissions and plugin compatibility. |
| Access denied | Verify the user's **Computer: Read** right. Also review the entity-isolation warning above. |
| Dashboard graphs do not load | Confirm browser access to Chart.js on `cdn.jsdelivr.net`; inspect browser console errors. |
| Inventory is empty | Verify the GLPI computer/software records and inventory synchronization. |
| Cisco Secure Endpoint appears missing | Confirm the software inventory includes the **exact title** `Cisco Secure Endpoint`. |
| C: disk metrics are absent | Verify GLPI disk records include `mountpoint = 'C:'` and valid size/free-space values. |
| Old-device list is empty | Check whether GLPI computer financial records contain `buy_date`. |
| PHP error or blank page | Review GLPI application/PHP logs and the relevant version's `setup.php`, `front/`, and `inc/` files. |

## Suggested validation checklist

- [ ] Plugin installs and enables on the intended GLPI version.
- [ ] The four pages open without PHP errors.
- [ ] Hardware totals agree with filtered GLPI computer inventory.
- [ ] Hardware and software CSV exports open correctly and respect selected filters.
- [ ] Chart.js loads and chart drill-down links work.
- [ ] C: disk alerts match recorded GLPI disk inventory.
- [ ] Cisco Secure Endpoint indicators match software title inventory.
- [ ] Old-device results match recorded purchase dates.
- [ ] **Restricted-user and multi-entity visibility is reviewed before production deployment.**

## Repository structure

```text
glpi-itassetdashboard/
├── glpi_version_10/itassetdashboard/  # GLPI 10 release
├── glpi_version_11/itassetdashboard/  # GLPI 11 release
├── glpi_version_12/itassetdashboard/  # GLPI 12 release
└── README.md                        # Repository documentation (this file)
```

## License and author

- **Author:** Piseth SEANG
- **Plugin license declaration:** GPLv2 or later (`GPLv2+` in `setup.php`).
- **Source:** [pisethseang/glpi-itassetdashboard](https://github.com/pisethseang/glpi-itassetdashboard)

## Feedback and contributions

Please use [GitHub Issues](https://github.com/pisethseang/glpi-itassetdashboard/issues) for reproducible bugs and improvement requests. Include your GLPI version, plugin version, PHP version, relevant non-sensitive log excerpts, and steps to reproduce. Do not post employee details, serial numbers, secrets, or private inventory exports.

---

*Documentation based on the repository's published `main` branch, inspected on 26 November 2026. Confirm compatibility and security requirements in a test environment before production use.*
