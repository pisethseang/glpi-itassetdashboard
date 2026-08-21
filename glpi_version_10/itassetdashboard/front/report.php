<?php
define('DO_NOT_CHECK_HTTP_REFERER', 1);
include('../../../inc/includes.php');

Session::checkRight('computer', READ);

$export = isset($_GET['export']) && $_GET['export'] === 'csv';

$filters = [
    'status'       => $_GET['status']       ?? '',
    'department'   => $_GET['department']   ?? '',
    'os'           => $_GET['os']           ?? '',
    'manufacturer' => $_GET['manufacturer'] ?? '',
    'search'       => $_GET['search']       ?? '',
    'assigned'     => $_GET['assigned']     ?? '',
];

// Sorting
$allowed_sort = ['ComputerName','Status','Computer_Serial','Computer_Model',
                 'Manufacturers','StaffID','FirstName','LastName',
                 'Department','Position','OS_Name','Version'];
$sort_col = (isset($_GET['sort']) && in_array($_GET['sort'], $allowed_sort)) ? $_GET['sort'] : 'ComputerName';
$sort_dir = (isset($_GET['dir']) && $_GET['dir'] === 'desc') ? 'desc' : 'asc';

$rows = PluginItassetdashboardDashboard::getComputers($filters);
$opts = PluginItassetdashboardDashboard::getFilterOptions();

// Sort in PHP (already have all rows)
usort($rows, function($a, $b) use ($sort_col, $sort_dir) {
    $va = strtolower($a[$sort_col] ?? '');
    $vb = strtolower($b[$sort_col] ?? '');
    $cmp = strcmp($va, $vb);
    return $sort_dir === 'desc' ? -$cmp : $cmp;
});

$glpi_computer_url = '/front/computer.form.php?id=';

// CSV export
if ($export) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="IT_Asset_Report_' . date('Ymd_His') . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Status','ComputerName','Computer_Serial','Computer_Model',
                   'Manufacturers','Staffid','FirstName','LastName',
                   'Department','Position','OS_Name','Version']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['Status']??'', $r['ComputerName']??'', $r['Computer_Serial']??'',
            $r['Computer_Model']??'', $r['Manufacturers']??'', $r['StaffID']??'',
            $r['FirstName']??'', $r['LastName']??'', $r['Department']??'',
            $r['Position']??'', $r['OS_Name']??'', $r['Version']??'',
        ]);
    }
    fclose($out);
    exit;
}

Html::header('IT Asset Report', $_SERVER['PHP_SELF'], 'tools', 'PluginItassetdashboardDashboard', 'report');

// Active filter labels
$active_filters = [];
if (!empty($filters['status']))       $active_filters[] = 'Status: <strong>'.htmlspecialchars($filters['status']).'</strong>';
if (!empty($filters['department']))   $active_filters[] = 'Dept: <strong>'.htmlspecialchars($filters['department']).'</strong>';
if (!empty($filters['os']))           $active_filters[] = 'OS: <strong>'.htmlspecialchars($filters['os']).'</strong>';
if (!empty($filters['manufacturer'])) $active_filters[] = 'Mfr: <strong>'.htmlspecialchars($filters['manufacturer']).'</strong>';
if (!empty($filters['search']))       $active_filters[] = 'Search: <strong>'.htmlspecialchars($filters['search']).'</strong>';
if ($filters['assigned']==='1')       $active_filters[] = '<strong>Assigned only</strong>';
if ($filters['assigned']==='0')       $active_filters[] = '<strong>Unassigned only</strong>';

$total_rows  = count($rows);
$base_params = array_merge($filters, ['sort' => $sort_col, 'dir' => $sort_dir]);
$current_url = '/plugins/itassetdashboard/front/report.php?' . http_build_query($base_params);

// Helper: sort link URL
function sort_url($col, $current_col, $current_dir, $base) {
    $dir = ($col === $current_col && $current_dir === 'asc') ? 'desc' : 'asc';
    return '/plugins/itassetdashboard/front/report.php?' . http_build_query(array_merge($base, ['sort'=>$col,'dir'=>$dir]));
}
function sort_icon($col, $current_col, $current_dir) {
    if ($col !== $current_col) return '<span class="iad-sort-icon iad-sort-none">↕</span>';
    return $current_dir === 'asc'
        ? '<span class="iad-sort-icon iad-sort-active">↑</span>'
        : '<span class="iad-sort-icon iad-sort-active">↓</span>';
}
?>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/plugins/itassetdashboard/css/dashboard.css">

<div class="iad-wrap">

  <!-- Page Header -->
  <div class="iad-header">
    <div class="iad-header-left">
      <div class="iad-header-icon"><i class="fas fa-table"></i></div>
      <div>
        <h1 class="iad-title">Computer Asset Report</h1>
        <p class="iad-subtitle">
          <?= $total_rows ?> record<?= $total_rows !== 1 ? 's' : '' ?> found
          <?php if ($active_filters): ?>&nbsp;—&nbsp;<?= implode(' &nbsp;·&nbsp; ', $active_filters) ?><?php endif; ?>
        </p>
      </div>
    </div>
    <div class="iad-header-right">
      <a href="/plugins/itassetdashboard/front/dashboard.php" class="iad-btn iad-btn-secondary">
        <i class="fas fa-tachometer-alt"></i> Dashboard
      </a>
      <a href="<?= htmlspecialchars($current_url) ?>&export=csv" class="iad-btn iad-btn-green">
        <i class="fas fa-file-csv"></i> Export CSV
      </a>
    </div>
  </div>

  <!-- Tabs -->
  <div class="iad-tabs">
    <a href="/plugins/itassetdashboard/front/report.php" class="iad-tab iad-tab-active">
      <i class="fas fa-laptop"></i> Hardware
    </a>
    <a href="/plugins/itassetdashboard/front/software.php" class="iad-tab">
      <i class="fas fa-box"></i> Software
    </a>
  </div>

  <!-- Filter Bar -->
  <form method="GET" action="" class="iad-filter-bar">
    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort_col) ?>">
    <input type="hidden" name="dir"  value="<?= htmlspecialchars($sort_dir) ?>">
    <div class="iad-filter-group">
      <label>Search</label>
      <input type="text" name="search" class="iad-input" placeholder="Name, Staff ID, Serial…"
             value="<?= htmlspecialchars($filters['search']) ?>">
    </div>
    <div class="iad-filter-group">
      <label>Status</label>
      <select name="status" class="iad-select">
        <option value="">All Statuses</option>
        <?php foreach ($opts['statuses'] as $st): ?>
        <option value="<?= htmlspecialchars($st) ?>" <?= $filters['status']===$st?'selected':'' ?>><?= htmlspecialchars($st) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="iad-filter-group">
      <label>Department</label>
      <select name="department" class="iad-select">
        <option value="">All Departments</option>
        <?php foreach ($opts['departments'] as $d): ?>
        <option value="<?= htmlspecialchars($d) ?>" <?= $filters['department']===$d?'selected':'' ?>><?= htmlspecialchars($d) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="iad-filter-group">
      <label>OS</label>
      <select name="os" class="iad-select">
        <option value="">All OS</option>
        <?php foreach ($opts['os_list'] as $o): ?>
        <option value="<?= htmlspecialchars($o) ?>" <?= $filters['os']===$o?'selected':'' ?>><?= htmlspecialchars($o) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="iad-filter-group">
      <label>Manufacturer</label>
      <select name="manufacturer" class="iad-select">
        <option value="">All Manufacturers</option>
        <?php foreach ($opts['manufacturers'] as $mf): ?>
        <option value="<?= htmlspecialchars($mf) ?>" <?= $filters['manufacturer']===$mf?'selected':'' ?>><?= htmlspecialchars($mf) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="iad-filter-group">
      <label>Assignment</label>
      <select name="assigned" class="iad-select">
        <option value="">All</option>
        <option value="1" <?= $filters['assigned']==='1'?'selected':'' ?>>Assigned only</option>
        <option value="0" <?= $filters['assigned']==='0'?'selected':'' ?>>Unassigned only</option>
      </select>
    </div>
    <div class="iad-filter-actions">
      <button type="submit" class="iad-btn iad-btn-primary"><i class="fas fa-search"></i> Filter</button>
      <a href="/plugins/itassetdashboard/front/report.php" class="iad-btn iad-btn-secondary"><i class="fas fa-times"></i> Reset</a>
    </div>
  </form>

  <!-- GLPI-style Table -->
  <div class="iad-glpi-table-wrap">

    <!-- Top pagination bar -->
    <div class="iad-pager-bar" id="pagerTop">
      <div class="iad-pager-left">
        <select class="iad-rpp-select" id="rppSelect">
          <option value="10">10</option>
          <option value="20" selected>20</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
        <span class="iad-rpp-label">rows / page</span>
      </div>
      <div class="iad-pager-center" id="pagerInfo">Showing 1 to 20 of <?= $total_rows ?> rows</div>
      <div class="iad-pager-right" id="pagerBtns">
        <button class="iad-page-btn" id="btnFirst" onclick="goPage(1)">«</button>
        <button class="iad-page-btn" id="btnPrev"  onclick="goPage(currentPage-1)">‹</button>
        <span class="iad-page-nums" id="pageNums"></span>
        <button class="iad-page-btn" id="btnNext"  onclick="goPage(currentPage+1)">›</button>
        <button class="iad-page-btn" id="btnLast"  onclick="goPage(totalPages)">»</button>
      </div>
    </div>

    <div class="iad-table-scroll">
      <table class="iad-glpi-table" id="reportTable">
        <thead>
          <tr>
            <th class="iad-th-check"><input type="checkbox" id="checkAll" onclick="toggleAll(this)"></th>
            <?php
            $cols = [
              ['key'=>'ComputerName',    'label'=>'NAME'],
              ['key'=>'Status',          'label'=>'STATUS'],
              ['key'=>'Manufacturers',   'label'=>'MANUFACTURERS'],
              ['key'=>'Computer_Serial', 'label'=>'SERIAL NUMBER'],
              ['key'=>'Computer_Model',  'label'=>'MODEL'],
              ['key'=>'OS_Name',         'label'=>'OPERATING SYSTEM'],
              ['key'=>'Version',         'label'=>'OS VERSION'],
              ['key'=>'StaffID',         'label'=>'STAFF ID'],
              ['key'=>'FirstName',       'label'=>'FIRST NAME'],
              ['key'=>'LastName',        'label'=>'LAST NAME'],
              ['key'=>'Department',      'label'=>'DEPARTMENT'],
              ['key'=>'Position',        'label'=>'POSITION'],
            ];
            foreach ($cols as $col):
              $url = sort_url($col['key'], $sort_col, $sort_dir, $base_params);
              $icon = sort_icon($col['key'], $sort_col, $sort_dir);
            ?>
            <th class="iad-th-sortable <?= $sort_col===$col['key']?'iad-th-active':'' ?>">
              <a href="<?= htmlspecialchars($url) ?>" class="iad-th-link">
                <?= $col['label'] ?> <?= $icon ?>
              </a>
            </th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody id="tableBody">
          <?php if (empty($rows)): ?>
          <tr><td colspan="13" class="iad-no-data"><i class="fas fa-inbox"></i> No records found.</td></tr>
          <?php else: ?>
          <?php foreach ($rows as $i => $r):
            $os   = $r['OS_Name'] ?? '';
            $icon_cls = 'fas fa-desktop'; $os_cls = 'iad-os-default';
            if (stripos($os,'windows')!==false)                                       { $icon_cls='fab fa-windows'; $os_cls='iad-os-win'; }
            elseif (stripos($os,'mac')!==false||stripos($os,'darwin')!==false)        { $icon_cls='fab fa-apple';   $os_cls='iad-os-mac'; }
            elseif (stripos($os,'linux')!==false||stripos($os,'ubuntu')!==false)      { $icon_cls='fab fa-linux';   $os_cls='iad-os-linux'; }
            $computer_link = !empty($r['ComputerID']) ? $glpi_computer_url.(int)$r['ComputerID'] : '#';
            $row_class = ($i % 2 === 0) ? 'iad-row-white' : 'iad-row-grey';
          ?>
          <tr class="iad-glpi-row <?= $row_class ?>" data-index="<?= $i ?>">
            <td class="iad-td-check"><input type="checkbox" class="iad-row-check"></td>
            <td class="iad-td-name">
              <a href="<?= $computer_link ?>" class="iad-name-link">
                <?= htmlspecialchars($r['ComputerName'] ?? '') ?>
              </a>
            </td>
            <td>
              <?php
                $st = $r['Status'] ?? '';
                $st_cls = 'iad-status-default';
                if (stripos($st,'use')!==false)         $st_cls='iad-status-inuse';
                elseif (stripos($st,'stock')!==false)   $st_cls='iad-status-stock';
                elseif (stripos($st,'maint')!==false)   $st_cls='iad-status-maint';
                elseif (stripos($st,'retire')!==false)  $st_cls='iad-status-retire';
              ?>
              <span class="iad-status-pill <?= $st_cls ?>"><?= htmlspecialchars($st) ?></span>
            </td>
            <td><?= htmlspecialchars($r['Manufacturers'] ?? '') ?></td>
            <td class="iad-td-mono"><?= htmlspecialchars($r['Computer_Serial'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['Computer_Model'] ?? '') ?></td>
            <td>
              <?php if ($os): ?>
              <span class="iad-os-cell <?= $os_cls ?>">
                <i class="<?= $icon_cls ?>"></i> <?= htmlspecialchars($os) ?>
              </span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($r['Version'] ?? '') ?></td>
            <td class="iad-td-mono"><?= htmlspecialchars($r['StaffID'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['FirstName'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['LastName'] ?? '') ?></td>
            <td>
              <?php if (!empty($r['Department'])): ?>
              <a href="/plugins/itassetdashboard/front/report.php?department=<?= urlencode($r['Department']) ?>"
                 class="iad-dept-link"><?= htmlspecialchars($r['Department']) ?></a>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($r['Position'] ?? '') ?></td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Bottom pagination bar -->
    <div class="iad-pager-bar" id="pagerBottom">
      <div class="iad-pager-left"></div>
      <div class="iad-pager-center" id="pagerInfoBottom">Showing 1 to 20 of <?= $total_rows ?> rows</div>
      <div class="iad-pager-right" id="pagerBtnsBottom">
        <button class="iad-page-btn" onclick="goPage(1)">«</button>
        <button class="iad-page-btn" id="btnPrevB"  onclick="goPage(currentPage-1)">‹</button>
        <span class="iad-page-nums" id="pageNumsB"></span>
        <button class="iad-page-btn" id="btnNextB"  onclick="goPage(currentPage+1)">›</button>
        <button class="iad-page-btn" onclick="goPage(totalPages)">»</button>
      </div>
    </div>

  </div><!-- /.iad-glpi-table-wrap -->
</div><!-- /.iad-wrap -->

<script>
const allRows    = Array.from(document.querySelectorAll('#tableBody tr[data-index]'));
const totalCount = <?= $total_rows ?>;
let currentPage  = 1;
let rowsPerPage  = 20;
let totalPages   = Math.ceil(totalCount / rowsPerPage);

function renderPage() {
    totalPages = Math.ceil(allRows.length / rowsPerPage);
    if (currentPage > totalPages) currentPage = totalPages || 1;
    const start = (currentPage - 1) * rowsPerPage;
    const end   = start + rowsPerPage;

    allRows.forEach((tr, i) => {
        tr.style.display = (i >= start && i < end) ? '' : 'none';
        // Re-apply alternating colour based on visible position
        tr.classList.remove('iad-row-white','iad-row-grey');
        tr.classList.add((i % 2 === 0) ? 'iad-row-white' : 'iad-row-grey');
    });

    const showing_start = allRows.length === 0 ? 0 : start + 1;
    const showing_end   = Math.min(end, allRows.length);
    const info = `Showing ${showing_start} to ${showing_end} of ${allRows.length} rows`;
    document.getElementById('pagerInfo').textContent       = info;
    document.getElementById('pagerInfoBottom').textContent = info;

    renderPageNums('pageNums');
    renderPageNums('pageNumsB');

    document.getElementById('btnPrev').disabled  = currentPage === 1;
    document.getElementById('btnFirst').disabled = currentPage === 1;
    document.getElementById('btnNext').disabled  = currentPage === totalPages || totalPages === 0;
    document.getElementById('btnLast').disabled  = currentPage === totalPages || totalPages === 0;
    document.getElementById('btnPrevB').disabled = currentPage === 1;
    document.getElementById('btnNextB').disabled = currentPage === totalPages || totalPages === 0;
}

function renderPageNums(id) {
    const el = document.getElementById(id);
    el.innerHTML = '';
    // Show up to 5 page numbers centred around current page
    let startP = Math.max(1, currentPage - 2);
    let endP   = Math.min(totalPages, startP + 4);
    startP     = Math.max(1, endP - 4);
    for (let p = startP; p <= endP; p++) {
        const btn = document.createElement('button');
        btn.className = 'iad-page-btn' + (p === currentPage ? ' iad-page-active' : '');
        btn.textContent = p;
        btn.onclick = () => goPage(p);
        el.appendChild(btn);
    }
}

function goPage(p) {
    if (p < 1 || p > totalPages) return;
    currentPage = p;
    renderPage();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

document.getElementById('rppSelect').addEventListener('change', function() {
    rowsPerPage = parseInt(this.value);
    currentPage = 1;
    renderPage();
});

function toggleAll(cb) {
    document.querySelectorAll('.iad-row-check').forEach(c => c.checked = cb.checked);
}

// Init
renderPage();
</script>

<?php Html::footer(); ?>
