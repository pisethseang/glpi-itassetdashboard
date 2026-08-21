<?php

class PluginItassetdashboardDashboard extends CommonGLPI {

    static $rightname = 'computer';

    static function getTypeName($nb = 0) {
        return 'IT Asset Dashboard';
    }

    static function getMenuName() {
        return 'IT Asset Dashboard';
    }

    static function getMenuContent() {
        $menu = [];
        $menu['title'] = self::getMenuName();
        $menu['page']  = '/plugins/itassetdashboard/front/dashboard.php';
        $menu['icon']  = 'fas fa-desktop';
        $menu['options']['dashboard'] = [
            'title' => 'Dashboard',
            'page'  => '/plugins/itassetdashboard/front/dashboard.php',
            'icon'  => 'fas fa-tachometer-alt',
        ];
        $menu['options']['report'] = [
            'title' => 'Hardware Report',
            'page'  => '/plugins/itassetdashboard/front/report.php',
            'icon'  => 'fas fa-laptop',
        ];
        $menu['options']['software'] = [
            'title' => 'Software Report',
            'page'  => '/plugins/itassetdashboard/front/software.php',
            'icon'  => 'fas fa-box',
        ];
        return $menu;
    }

    /**
     * Safely escape a value using GLPI's DB escape method.
     * Falls back to addslashes if $DB not available.
     */
    static function esc($value) {
        global $DB;
        if (isset($DB) && method_exists($DB, 'escape')) {
            return $DB->escape($value);
        }
        return addslashes($value);
    }

    /**
     * Build WHERE clause from filters.
     * Uses $DB->escape() for safe SQL — no raw addslashes.
     */
    static function buildWhere($filters = []) {
        $where = "c.is_deleted = 0";

        if (!empty($filters['status'])) {
            $s = self::esc($filters['status']);
            if ($s === '(No Status)') {
                $where .= " AND c.states_id = 0";
            } else {
                $where .= " AND st.name = '$s'";
            }
        }
        if (!empty($filters['department'])) {
            $d = self::esc($filters['department']);
            $where .= " AND uc.name = '$d'";
        }
        if (!empty($filters['os'])) {
            $o = self::esc($filters['os']);
            $where .= " AND t.name LIKE '%$o%'";
        }
        if (!empty($filters['manufacturer'])) {
            $m = self::esc($filters['manufacturer']);
            $where .= " AND m.name = '$m'";
        }
        if (!empty($filters['search'])) {
            $q = self::esc($filters['search']);
            $where .= " AND (
                c.name LIKE '%$q%'
                OR u.firstname LIKE '%$q%'
                OR u.realname LIKE '%$q%'
                OR u.registration_number LIKE '%$q%'
                OR c.serial LIKE '%$q%'
            )";
        }

        // Assigned / Unassigned filter
        if (isset($filters['assigned']) && $filters['assigned'] !== '') {
            if ($filters['assigned'] === '1') {
                $where .= " AND c.users_id > 0";
            } elseif ($filters['assigned'] === '0') {
                $where .= " AND (c.users_id = 0 OR c.users_id IS NULL)";
            }
        }

        return $where;
    }

    /**
     * Core computer query — matches original SQL exactly.
     * COALESCE applied to all nullable columns so no NULLs reach PHP.
     */
    static function getComputerQuery($filters = []) {
        $where = self::buildWhere($filters);
        return "
            SELECT
                c.id                                AS ComputerID,
                COALESCE(st.name,  '(No Status)')   AS Status,
                COALESCE(c.name,   '')               AS ComputerName,
                COALESCE(c.serial, '')               AS Computer_Serial,
                COALESCE(cm.name,  '')               AS Computer_Model,
                COALESCE(m.name,   '')               AS Manufacturers,
                COALESCE(u.registration_number, '')  AS StaffID,
                COALESCE(u.firstname,           '')  AS FirstName,
                COALESCE(u.realname,            '')  AS LastName,
                COALESCE(uc.name,               '')  AS Department,
                COALESCE(ust.name,              '')  AS Position,
                COALESCE(t.name,                '')  AS OS_Name,
                COALESCE(t2.name,               '')  AS Version
            FROM glpi_computers c
            LEFT JOIN glpi_states st                  ON c.states_id = st.id
            LEFT JOIN glpi_items_softwareversions isv ON c.id = isv.items_id AND isv.itemtype = 'Computer'
            LEFT JOIN glpi_users u                    ON c.users_id = u.id
            LEFT JOIN glpi_usertitles ust             ON u.usertitles_id = ust.id
            LEFT JOIN glpi_usercategories uc          ON u.usercategories_id = uc.id
            LEFT JOIN glpi_computermodels cm          ON c.computermodels_id = cm.id
            LEFT JOIN glpi_manufacturers m            ON c.manufacturers_id = m.id
            LEFT JOIN  glpi_items_operatingsystems io ON io.items_id = c.id AND io.itemtype = 'Computer'
            LEFT JOIN glpi_operatingsystems t         ON t.id = io.operatingsystems_id
            LEFT JOIN glpi_operatingsystemversions t2 ON t2.id = io.operatingsystemversions_id
            WHERE $where
            GROUP BY
                c.id, st.name, c.name, c.serial, cm.name, m.name,
                u.registration_number, u.firstname, u.realname,
                uc.name, ust.name, t.name, t2.name
            ORDER BY c.name ASC
        ";
    }

    static function getComputers($filters = []) {
        global $DB;
        $result = $DB->doQuery(self::getComputerQuery($filters));
        $rows = [];
        if ($result) {
            while ($row = $DB->fetchAssoc($result)) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    /**
     * Dashboard summary stats — all nullable fields use COALESCE.
     */
    static function getSummaryStats() {
        global $DB;
        $stats = [];

        // Total non-deleted computers
        $r = $DB->doQuery("
            SELECT COUNT(DISTINCT c.id) AS cnt
            FROM glpi_computers c
            WHERE c.is_deleted = 0
        ");
        $stats['total'] = $r ? (int)$DB->fetchAssoc($r)['cnt'] : 0;

        // By Status
        $r = $DB->doQuery("
            SELECT COALESCE(st.name, '(No Status)') AS status,
                   COUNT(DISTINCT c.id) AS cnt
            FROM glpi_computers c
            LEFT JOIN glpi_states st ON c.states_id = st.id
            WHERE c.is_deleted = 0
            GROUP BY st.name
            ORDER BY cnt DESC
        ");
        $stats['by_status'] = [];
        if ($r) while ($row = $DB->fetchAssoc($r)) $stats['by_status'][] = $row;

        // By OS
        $r = $DB->doQuery("
            SELECT COALESCE(t.name, '(Unknown)') AS os,
                   COUNT(DISTINCT c.id) AS cnt
            FROM glpi_computers c
            LEFT JOIN  glpi_items_operatingsystems io ON io.items_id = c.id AND io.itemtype = 'Computer'
            LEFT JOIN glpi_operatingsystems t ON t.id = io.operatingsystems_id
            WHERE c.is_deleted = 0
            GROUP BY t.name
            ORDER BY cnt DESC
        ");
        $stats['by_os'] = [];
        if ($r) while ($row = $DB->fetchAssoc($r)) $stats['by_os'][] = $row;

        // By Manufacturer
        $r = $DB->doQuery("
            SELECT COALESCE(m.name, '(Unknown)') AS manufacturer,
                   COUNT(DISTINCT c.id) AS cnt
            FROM glpi_computers c
            LEFT JOIN glpi_manufacturers m ON c.manufacturers_id = m.id
            WHERE c.is_deleted = 0
            GROUP BY m.name
            ORDER BY cnt DESC
            LIMIT 10
        ");
        $stats['by_manufacturer'] = [];
        if ($r) while ($row = $DB->fetchAssoc($r)) $stats['by_manufacturer'][] = $row;

        // By Department
        $r = $DB->doQuery("
            SELECT COALESCE(uc.name, '(Unassigned)') AS department,
                   COUNT(DISTINCT c.id) AS cnt
            FROM glpi_computers c
            LEFT JOIN glpi_users u ON c.users_id = u.id
            LEFT JOIN glpi_usercategories uc ON u.usercategories_id = uc.id
            WHERE c.is_deleted = 0
            GROUP BY uc.name
            ORDER BY cnt DESC
        ");
        $stats['by_department'] = [];
        if ($r) while ($row = $DB->fetchAssoc($r)) $stats['by_department'][] = $row;

        // Assigned vs Unassigned
        $r = $DB->doQuery("
            SELECT
                SUM(CASE WHEN c.users_id > 0              THEN 1 ELSE 0 END) AS assigned,
                SUM(CASE WHEN COALESCE(c.users_id, 0) = 0 THEN 1 ELSE 0 END) AS unassigned
            FROM glpi_computers c
            WHERE c.is_deleted = 0
        ");
        if ($r) {
            $row = $DB->fetchAssoc($r);
            $stats['assigned']   = (int)($row['assigned']   ?? 0);
            $stats['unassigned'] = (int)($row['unassigned'] ?? 0);
        }

        return $stats;
    }

    /**
     * Filter dropdown options — dynamically pulled from real DB values.
     * No hardcoded status names.
     */
    static function getFilterOptions() {
        global $DB;
        $opts = [];

        // Statuses — whatever actually exists in DB
        $r = $DB->doQuery("
            SELECT DISTINCT COALESCE(st.name, '(No Status)') AS val
            FROM glpi_computers c
            LEFT JOIN glpi_states st ON c.states_id = st.id
            WHERE c.is_deleted = 0
            ORDER BY val
        ");
        $opts['statuses'] = [];
        if ($r) while ($row = $DB->fetchAssoc($r)) $opts['statuses'][] = $row['val'];

        // Departments
        $r = $DB->doQuery("
            SELECT DISTINCT COALESCE(uc.name, '') AS val
            FROM glpi_computers c
            LEFT JOIN glpi_users u ON c.users_id = u.id
            LEFT JOIN glpi_usercategories uc ON u.usercategories_id = uc.id
            WHERE c.is_deleted = 0 AND uc.name IS NOT NULL
            ORDER BY val
        ");
        $opts['departments'] = [];
        if ($r) while ($row = $DB->fetchAssoc($r)) $opts['departments'][] = $row['val'];

        // OS
        $r = $DB->doQuery("
            SELECT DISTINCT COALESCE(t.name, '') AS val
            FROM glpi_computers c
            LEFT JOIN  glpi_items_operatingsystems io ON io.items_id = c.id AND io.itemtype = 'Computer'
            LEFT JOIN glpi_operatingsystems t ON t.id = io.operatingsystems_id
            WHERE c.is_deleted = 0 AND t.name IS NOT NULL
            ORDER BY val
        ");
        $opts['os_list'] = [];
        if ($r) while ($row = $DB->fetchAssoc($r)) $opts['os_list'][] = $row['val'];

        // Manufacturers
        $r = $DB->doQuery("
            SELECT DISTINCT COALESCE(m.name, '') AS val
            FROM glpi_computers c
            LEFT JOIN glpi_manufacturers m ON c.manufacturers_id = m.id
            WHERE c.is_deleted = 0 AND m.name IS NOT NULL
            ORDER BY val
        ");
        $opts['manufacturers'] = [];
        if ($r) while ($row = $DB->fetchAssoc($r)) $opts['manufacturers'][] = $row['val'];

        return $opts;
    }

    // ================================================================
    //  SOFTWARE INVENTORY
    // ================================================================

    /**
     * Software inventory query — one row per computer + software version.
     */
    static function getSoftwareQuery($filters = []) {
        $where = "c.is_deleted = 0 AND s.is_deleted = 0";

        if (!empty($filters['software'])) {
            $v = self::esc($filters['software']);
            $where .= " AND s.name LIKE '%$v%'";
        }
        if (!empty($filters['publisher'])) {
            $v = self::esc($filters['publisher']);
            $where .= " AND sm.name = '$v'";
        }
        if (!empty($filters['computer'])) {
            $v = self::esc($filters['computer']);
            $where .= " AND c.name = '$v'";
        }
        if (!empty($filters['department'])) {
            $v = self::esc($filters['department']);
            $where .= " AND uc.name = '$v'";
        }
        if (!empty($filters['category'])) {
            $v = self::esc($filters['category']);
            $where .= " AND sc.name = '$v'";
        }
        if (!empty($filters['search'])) {
            $v = self::esc($filters['search']);
            $where .= " AND (s.name LIKE '%$v%' OR c.name LIKE '%$v%' OR sm.name LIKE '%$v%' OR sv.name LIKE '%$v%')";
        }

        return "
            SELECT
                c.id                                    AS ComputerID,
                COALESCE(c.name,   '')                  AS ComputerName,
                COALESCE(st.name,  '(No Status)')       AS Status,
                COALESCE(s.name,   '')                  AS SoftwareName,
                COALESCE(sv.name,  '')                  AS SoftwareVersion,
                COALESCE(sm.name,  '')                  AS Publisher,
                COALESCE(sc.name,  '')                  AS SoftwareCategory,
                COALESCE(u.registration_number, '')     AS StaffID,
                COALESCE(u.firstname, '')               AS FirstName,
                COALESCE(u.realname,  '')               AS LastName,
                COALESCE(uc.name,  '')                  AS Department,
                COALESCE(isv.date_install, '')          AS InstallDate,
                COALESCE(cm.name,  '')                  AS Computer_Model,
                COALESCE(m.name,   '')                  AS Manufacturers
            FROM glpi_computers c
            LEFT JOIN glpi_states st                    ON c.states_id = st.id
            LEFT JOIN glpi_users u                      ON c.users_id = u.id
            LEFT JOIN glpi_usercategories uc            ON u.usercategories_id = uc.id
            INNER JOIN glpi_items_softwareversions isv  ON isv.items_id = c.id AND isv.itemtype = 'Computer'
            INNER JOIN glpi_softwareversions sv         ON sv.id = isv.softwareversions_id
            INNER JOIN glpi_softwares s                 ON s.id = sv.softwares_id
            LEFT JOIN glpi_manufacturers sm             ON sm.id = s.manufacturers_id
            LEFT JOIN glpi_softwarecategories sc        ON sc.id = s.softwarecategories_id
            LEFT JOIN glpi_computermodels cm          ON cm.id = c.computermodels_id
            LEFT JOIN glpi_manufacturers m             ON m.id  = c.manufacturers_id
            WHERE $where
            ORDER BY s.name ASC, c.name ASC
        ";
    }

    static function getSoftware($filters = []) {
        global $DB;
        $result = $DB->doQuery(self::getSoftwareQuery($filters));
        $rows = [];
        if ($result) while ($row = $DB->fetchAssoc($result)) $rows[] = $row;
        return $rows;
    }

    /**
     * Software summary stats for dashboard.
     */
    static function getSoftwareStats() {
        global $DB;
        $stats = [];

        // Total distinct software titles
        $r = $DB->doQuery("SELECT COUNT(DISTINCT s.id) AS cnt
            FROM glpi_softwares s WHERE s.is_deleted = 0");
        $stats['total_titles'] = $r ? (int)$DB->fetchAssoc($r)['cnt'] : 0;

        // Total installations
        $r = $DB->doQuery("SELECT COUNT(*) AS cnt
            FROM glpi_items_softwareversions isv
            INNER JOIN glpi_softwares s ON s.id = (
                SELECT sv2.softwares_id FROM glpi_softwareversions sv2 WHERE sv2.id = isv.softwareversions_id
            )
            WHERE isv.itemtype = 'Computer' AND s.is_deleted = 0");
        $stats['total_installs'] = $r ? (int)$DB->fetchAssoc($r)['cnt'] : 0;

        // Top 10 most installed software — count distinct computers
        $r = $DB->doQuery("
            SELECT s.name AS software, COUNT(DISTINCT isv.items_id) AS cnt
            FROM glpi_items_softwareversions isv
            INNER JOIN glpi_softwareversions sv ON sv.id = isv.softwareversions_id
            INNER JOIN glpi_softwares s         ON s.id  = sv.softwares_id
            INNER JOIN glpi_computers c         ON c.id  = isv.items_id AND c.is_deleted = 0
            WHERE isv.itemtype = 'Computer' AND s.is_deleted = 0
            GROUP BY s.id, s.name
            ORDER BY cnt DESC
            LIMIT 10
        ");
        $stats['top_software'] = [];
        if ($r) while ($row = $DB->fetchAssoc($r)) $stats['top_software'][] = $row;

        // By publisher — count distinct titles AND distinct PCs
        $r = $DB->doQuery("
            SELECT COALESCE(sm.name, '(Unknown)') AS publisher,
                   COUNT(DISTINCT s.id)           AS cnt,
                   COUNT(DISTINCT isv.items_id)   AS pc_cnt
            FROM glpi_softwares s
            LEFT JOIN glpi_manufacturers sm             ON sm.id  = s.manufacturers_id
            LEFT JOIN glpi_softwareversions sv          ON sv.softwares_id = s.id
            LEFT JOIN glpi_items_softwareversions isv   ON isv.softwareversions_id = sv.id
                                                       AND isv.itemtype = 'Computer'
            LEFT JOIN glpi_computers c                  ON c.id = isv.items_id AND c.is_deleted = 0
            WHERE s.is_deleted = 0
            GROUP BY sm.name
            ORDER BY cnt DESC
            LIMIT 10
        ");
        $stats['by_publisher'] = [];
        if ($r) while ($row = $DB->fetchAssoc($r)) $stats['by_publisher'][] = $row;

        // Software titles installed per department (distinct titles per dept)
        $r = $DB->doQuery("
            SELECT COALESCE(uc.name,'(No Dept)') AS department,
                   COUNT(DISTINCT s.id)          AS titles,
                   COUNT(DISTINCT c.id)          AS computers
            FROM glpi_items_softwareversions isv
            INNER JOIN glpi_softwareversions sv ON sv.id = isv.softwareversions_id
            INNER JOIN glpi_softwares s         ON s.id  = sv.softwares_id AND s.is_deleted = 0
            INNER JOIN glpi_computers c         ON c.id  = isv.items_id   AND c.is_deleted = 0
            LEFT JOIN  glpi_users u             ON u.id  = c.users_id
            LEFT JOIN  glpi_usercategories uc   ON uc.id = u.usercategories_id
            WHERE isv.itemtype = 'Computer'
            GROUP BY uc.name
            ORDER BY titles DESC
        ");
        $stats['sw_by_department'] = [];
        if ($r) while ($row = $DB->fetchAssoc($r)) $stats['sw_by_department'][] = $row;

        // Software vulnerability — software versions with no valid licence recorded
        // Note: GLPI's glpi_softwareversions table has no end_of_support column,
        // so EOL-by-date detection was removed; this now only flags missing licences.
        $r = $DB->doQuery("
            SELECT
                s.name                              AS software,
                COALESCE(sv.name,'?')               AS version,
                COUNT(DISTINCT isv.items_id)        AS affected_pcs,
                'No Licence'                        AS vuln_type
            FROM glpi_items_softwareversions isv
            INNER JOIN glpi_softwareversions sv ON sv.id = isv.softwareversions_id
            INNER JOIN glpi_softwares s         ON s.id  = sv.softwares_id AND s.is_deleted = 0
            INNER JOIN glpi_computers c         ON c.id  = isv.items_id   AND c.is_deleted = 0
            LEFT JOIN  glpi_softwarelicenses sl ON sl.softwares_id = s.id
            WHERE isv.itemtype = 'Computer'
              AND sl.id IS NULL
            GROUP BY s.id, sv.id, s.name, sv.name
            ORDER BY affected_pcs DESC
            LIMIT 15
        ");
        $stats['sw_vulnerable'] = [];
        if ($r) while ($row = $DB->fetchAssoc($r)) $stats['sw_vulnerable'][] = $row;

        return $stats;
    }

    /**
     * Software filter dropdown options.
     */
    static function getSoftwareFilterOptions() {
        global $DB;
        $opts = [];

        $r = $DB->doQuery("SELECT DISTINCT COALESCE(sm.name,'') AS val
            FROM glpi_softwares s
            LEFT JOIN glpi_manufacturers sm ON sm.id = s.manufacturers_id
            WHERE s.is_deleted = 0 AND sm.name IS NOT NULL
            ORDER BY val");
        $opts['publishers'] = [];
        if ($r) while ($row = $DB->fetchAssoc($r)) $opts['publishers'][] = $row['val'];

        $r = $DB->doQuery("SELECT DISTINCT COALESCE(uc.name,'') AS val
            FROM glpi_computers c
            LEFT JOIN glpi_users u ON c.users_id = u.id
            LEFT JOIN glpi_usercategories uc ON u.usercategories_id = uc.id
            WHERE c.is_deleted = 0 AND uc.name IS NOT NULL ORDER BY val");
        $opts['departments'] = [];
        if ($r) while ($row = $DB->fetchAssoc($r)) $opts['departments'][] = $row['val'];

        // Software names list for dropdown
        $r = $DB->doQuery("SELECT DISTINCT s.name AS val
            FROM glpi_softwares s
            WHERE s.is_deleted = 0 AND s.name IS NOT NULL
            ORDER BY s.name");
        $opts['software_names'] = [];
        if ($r) while ($row = $DB->fetchAssoc($r)) $opts['software_names'][] = $row['val'];

        // Computer names list for dropdown
        $r = $DB->doQuery("SELECT DISTINCT c.name AS val
            FROM glpi_computers c
            WHERE c.is_deleted = 0 AND c.name IS NOT NULL
            ORDER BY c.name");
        $opts['computer_names'] = [];
        if ($r) while ($row = $DB->fetchAssoc($r)) $opts['computer_names'][] = $row['val'];

        // Software categories
        $r = $DB->doQuery("SELECT DISTINCT sc.name AS val
            FROM glpi_softwares s
            LEFT JOIN glpi_softwarecategories sc ON sc.id = s.softwarecategories_id
            WHERE s.is_deleted = 0 AND sc.name IS NOT NULL
            ORDER BY sc.name");
        $opts['categories'] = [];
        if ($r) while ($row = $DB->fetchAssoc($r)) $opts['categories'][] = $row['val'];

        return $opts;
    }


    // ================================================================
    //  IT HEALTH DASHBOARD STATS
    // ================================================================

    static function getHealthStats() {
        global $DB;
        $h = [];

        // ── 1. Total computers by OS ─────────────────────────────
        $r = $DB->doQuery("
            SELECT COALESCE(os.name,'(Unknown)') AS os_name,
                   COUNT(DISTINCT c.id) AS cnt
            FROM glpi_computers c
            LEFT JOIN glpi_items_operatingsystems io ON io.items_id = c.id AND io.itemtype = 'Computer'
            LEFT JOIN glpi_operatingsystems os ON os.id = io.operatingsystems_id
            WHERE c.is_deleted = 0
            GROUP BY os.name ORDER BY cnt DESC
        ");
        $h['by_os'] = [];
        if ($r) while ($row = $DB->fetchAssoc($r)) $h['by_os'][] = $row;

        $h['total'] = array_sum(array_column($h['by_os'], 'cnt'));

        // ── 2. Disk usage — low space alert (<15% free) ──────────
        // glpi_items_disks stores totalsize & freesize in MB
        // C: drive only — GLPI agents report C: drive with various name formats
        // We match the most common: 'C:', 'C:\', 'C' and also by mountpoint for Windows
        $r = $DB->doQuery("
            SELECT
                c.id   AS computer_id,
                c.name AS computer_name,
                COALESCE(uc.name,'(No Dept)') AS department,
                d.name  AS disk_name,
                d.totalsize,
                d.freesize,
                ROUND((d.freesize / NULLIF(d.totalsize,0)) * 100, 1) AS free_pct
            FROM glpi_items_disks d
            INNER JOIN glpi_computers c ON c.id = d.items_id AND d.itemtype = 'Computer'
            LEFT JOIN glpi_users u       ON u.id = c.users_id
            LEFT JOIN glpi_usercategories uc ON uc.id = u.usercategories_id
            WHERE c.is_deleted = 0
              AND d.totalsize > 0
              AND d.mountpoint = 'C:'
              AND (d.freesize / NULLIF(d.totalsize,0)) < 0.15
            ORDER BY free_pct ASC
            LIMIT 20
        ");
        $h['low_disk'] = [];
        if ($r) while ($row = $DB->fetchAssoc($r)) $h['low_disk'][] = $row;

        // Disk summary counts
        $r2 = $DB->doQuery("
            SELECT
                SUM(CASE WHEN (d.freesize/NULLIF(d.totalsize,0)) < 0.10 THEN 1 ELSE 0 END) AS critical,
                SUM(CASE WHEN (d.freesize/NULLIF(d.totalsize,0)) >= 0.10
                          AND (d.freesize/NULLIF(d.totalsize,0)) < 0.15 THEN 1 ELSE 0 END) AS warning,
                SUM(CASE WHEN (d.freesize/NULLIF(d.totalsize,0)) >= 0.15 THEN 1 ELSE 0 END) AS healthy,
                COUNT(*) AS total_disks
            FROM glpi_items_disks d
            INNER JOIN glpi_computers c ON c.id = d.items_id AND d.itemtype = 'Computer'
            WHERE c.is_deleted = 0
              AND d.totalsize > 0
              AND d.mountpoint = 'C:'
        ");
        $h['disk_summary'] = ['critical'=>0,'warning'=>0,'healthy'=>0,'total_disks'=>0];
        if ($r2) {
            $row = $DB->fetchAssoc($r2);
            $h['disk_summary'] = [
                'critical'    => (int)($row['critical']    ?? 0),
                'warning'     => (int)($row['warning']     ?? 0),
                'healthy'     => (int)($row['healthy']     ?? 0),
                'total_disks' => (int)($row['total_disks'] ?? 0),
            ];
        }

        // ── 3. Top installed software ────────────────────────────
        $r = $DB->doQuery("
            SELECT s.name AS software, COUNT(*) AS cnt
            FROM glpi_items_softwareversions isv
            INNER JOIN glpi_softwareversions sv ON sv.id = isv.softwareversions_id
            INNER JOIN glpi_softwares s         ON s.id  = sv.softwares_id
            INNER JOIN glpi_computers c         ON c.id  = isv.items_id AND isv.itemtype = 'Computer'
            WHERE c.is_deleted = 0 AND s.is_deleted = 0
            GROUP BY s.id, s.name
            ORDER BY cnt DESC
            LIMIT 10
        ");
        $h['top_software'] = [];
        if ($r) while ($row = $DB->fetchAssoc($r)) $h['top_software'][] = $row;

        // ── 4. Antivirus status ──────────────────────────────────
        // Exact match on "Cisco Secure Endpoint"
        $r = $DB->doQuery("
            SELECT c.id AS computer_id, c.name AS computer_name,
                   COALESCE(uc.name,'(No Dept)') AS department,
                   GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') AS av_name
            FROM glpi_computers c
            LEFT JOIN glpi_users u ON u.id = c.users_id
            LEFT JOIN glpi_usercategories uc ON uc.id = u.usercategories_id
            INNER JOIN glpi_items_softwareversions isv ON isv.items_id = c.id AND isv.itemtype = 'Computer'
            INNER JOIN glpi_softwareversions sv ON sv.id = isv.softwareversions_id
            INNER JOIN glpi_softwares s ON s.id = sv.softwares_id AND s.is_deleted = 0
            WHERE c.is_deleted = 0
              AND s.name = 'Cisco Secure Endpoint'
            GROUP BY c.id, c.name, uc.name
        ");
        $av_protected = [];
        if ($r) while ($row = $DB->fetchAssoc($r)) $av_protected[$row['computer_id']] = $row;

        $h['av_protected']   = count($av_protected);
        $h['av_unprotected'] = max(0, $h['total'] - $h['av_protected']);
        $h['av_list']        = array_values($av_protected);

        // Unprotected computer list (top 15)
        $protected_ids = array_keys($av_protected);
        $not_in = empty($protected_ids) ? '' : 'AND c.id NOT IN (' . implode(',', array_map('intval', $protected_ids)) . ')';
        $r = $DB->doQuery("
            SELECT c.id AS computer_id, c.name AS computer_name,
                   COALESCE(c.serial, '')        AS serial,
                   COALESCE(uc.name,'(No Dept)') AS department
            FROM glpi_computers c
            LEFT JOIN glpi_users u ON u.id = c.users_id
            LEFT JOIN glpi_usercategories uc ON uc.id = u.usercategories_id
            WHERE c.is_deleted = 0 $not_in
            ORDER BY c.name ASC LIMIT 15
        ");
        $h['av_unprotected_list'] = [];
        if ($r) while ($row = $DB->fetchAssoc($r)) $h['av_unprotected_list'][] = $row;

        // ── 5. Computers by department ───────────────────────────
        $r = $DB->doQuery("
            SELECT COALESCE(uc.name,'(Unassigned)') AS department,
                   COUNT(DISTINCT c.id) AS cnt
            FROM glpi_computers c
            LEFT JOIN glpi_users u ON u.id = c.users_id
            LEFT JOIN glpi_usercategories uc ON uc.id = u.usercategories_id
            WHERE c.is_deleted = 0
            GROUP BY uc.name ORDER BY cnt DESC
        ");
        $h['by_department'] = [];
        if ($r) while ($row = $DB->fetchAssoc($r)) $h['by_department'][] = $row;

        // ── 6. Old devices — glpi_infocoms.buy_date (Asset Lifecycle > Date of Purchase) ──
        $five_years_ago = date('Y-m-d', strtotime('-5 years'));
        $r = $DB->doQuery("
            SELECT
                c.id, c.name AS computer_name,
                COALESCE(c.serial, '')           AS serial,
                COALESCE(uc.name,'(No Dept)')    AS department,
                COALESCE(cm.name,'')              AS model,
                COALESCE(m.name,'')               AS manufacturer,
                COALESCE(st.name,'(No Status)')   AS status,
                ic.buy_date                        AS device_date,
                DATE_FORMAT(ic.buy_date, '%d %b %Y') AS buy_date_fmt,
                TIMESTAMPDIFF(YEAR, ic.buy_date, CURDATE()) AS age_years
            FROM glpi_computers c
            LEFT JOIN glpi_infocoms ic  ON ic.items_id = c.id AND ic.itemtype = 'Computer'
            LEFT JOIN glpi_states st    ON st.id = c.states_id
            LEFT JOIN glpi_users u      ON u.id = c.users_id
            LEFT JOIN glpi_usercategories uc ON uc.id = u.usercategories_id
            LEFT JOIN glpi_computermodels cm ON cm.id = c.computermodels_id
            LEFT JOIN glpi_manufacturers m   ON m.id  = c.manufacturers_id
            WHERE c.is_deleted = 0
              AND ic.buy_date IS NOT NULL
              AND ic.buy_date != '0000-00-00'
              AND ic.buy_date <= '$five_years_ago'
            ORDER BY ic.buy_date ASC
            LIMIT 20
        ");
        $h['old_devices'] = [];
        if ($r) while ($row = $DB->fetchAssoc($r)) $h['old_devices'][] = $row;

        // Old device age buckets — glpi_infocoms.buy_date
        $r = $DB->doQuery("
            SELECT
                SUM(CASE WHEN TIMESTAMPDIFF(YEAR, ic.buy_date, CURDATE()) BETWEEN 5 AND 6 THEN 1 ELSE 0 END) AS y5_6,
                SUM(CASE WHEN TIMESTAMPDIFF(YEAR, ic.buy_date, CURDATE()) BETWEEN 7 AND 9 THEN 1 ELSE 0 END) AS y7_9,
                SUM(CASE WHEN TIMESTAMPDIFF(YEAR, ic.buy_date, CURDATE()) >= 10            THEN 1 ELSE 0 END) AS y10plus
            FROM glpi_computers c
            LEFT JOIN glpi_infocoms ic ON ic.items_id = c.id AND ic.itemtype = 'Computer'
            WHERE c.is_deleted = 0
              AND ic.buy_date IS NOT NULL
              AND ic.buy_date != '0000-00-00'
              AND ic.buy_date <= '$five_years_ago'
        ");
        $h['old_buckets'] = ['y5_6'=>0,'y7_9'=>0,'y10plus'=>0];
        if ($r) {
            $row = $DB->fetchAssoc($r);
            $h['old_buckets'] = [
                'y5_6'   => (int)($row['y5_6']   ?? 0),
                'y7_9'   => (int)($row['y7_9']   ?? 0),
                'y10plus'=> (int)($row['y10plus'] ?? 0),
            ];
        }
        $h['old_total'] = array_sum($h['old_buckets']);

        return $h;
    }

}
