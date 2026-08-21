<?php
define('DO_NOT_CHECK_HTTP_REFERER', 1);
include('../../../inc/includes.php');

Session::checkRight('computer', READ);

$export = isset($_GET['export']) && $_GET['export'] === 'csv';

$filters = [
    'software'   => $_GET['software']   ?? '',
    'publisher'  => $_GET['publisher']  ?? '',
    'computer'   => $_GET['computer']   ?? '',
    'department' => $_GET['department'] ?? '',
    'category'   => $_GET['category']   ?? '',
    'search'     => $_GET['search']     ?? '',
];

$rows = PluginItassetdashboardDashboard::getSoftware($filters);
$opts = PluginItassetdashboardDashboard::getSoftwareFilterOptions();

if ($export) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Software_Report_' . date('Ymd_His') . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ComputerName','Computer_Model','Manufacturers','Status',
                   'SoftwareName','Version','Publisher','Category',
                   'StaffID','FirstName','LastName','Department','InstallDate']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['ComputerName']??'',    $r['Computer_Model']??'', $r['Manufacturers']??'',
            $r['Status']??'',          $r['SoftwareName']??'',   $r['SoftwareVersion']??'',
            $r['Publisher']??'',       $r['SoftwareCategory']??'',
            $r['StaffID']??'',         $r['FirstName']??'',      $r['LastName']??'',
            $r['Department']??'',      $r['InstallDate']??'',
        ]);
    }
    fclose($out);
    exit;
}

Html::header('Software Report', $_SERVER['PHP_SELF'], 'tools', 'PluginItassetdashboardDashboard', 'software');

$allowed_sort = ['ComputerName','Computer_Model','Manufacturers','Status',
                 'SoftwareName','SoftwareVersion','Publisher',
                 'SoftwareCategory','Department','InstallDate'];
$sort_col = (isset($_GET['sort']) && in_array($_GET['sort'], $allowed_sort)) ? $_GET['sort'] : 'SoftwareName';
$sort_dir = (isset($_GET['dir']) && $_GET['dir'] === 'desc') ? 'desc' : 'asc';

usort($rows, function($a, $b) use ($sort_col, $sort_dir) {
    $va = strtolower($a[$sort_col] ?? '');
    $vb = strtolower($b[$sort_col] ?? '');
    $cmp = strcmp($va, $vb);
    return $sort_dir === 'desc' ? -$cmp : $cmp;
});

$total_rows  = count($rows);
$base_params = array_merge($filters, ['sort' => $sort_col, 'dir' => $sort_dir]);
$current_url = '/plugins/itassetdashboard/front/software.php?' . http_build_query($base_params);
$glpi_computer_url = '/front/computer.form.php?id=';

function sw_sort_url($col, $cc, $cd, $base) {
    $dir = ($col === $cc && $cd === 'asc') ? 'desc' : 'asc';
    return '/plugins/itassetdashboard/front/software.php?' . http_build_query(array_merge($base, ['sort'=>$col,'dir'=>$dir]));
}
function sw_sort_icon($col, $cc, $cd) {
    if ($col !== $cc) return '<span class="iad-sort-icon iad-sort-none">↕</span>';
    return $cd==='asc' ? '<span class="iad-sort-icon iad-sort-active">↑</span>'
                       : '<span class="iad-sort-icon iad-sort-active">↓</span>';
}

$active_filters = [];
if (!empty($filters['software']))   $active_filters[] = 'Software: <strong>'.htmlspecialchars($filters['software']).'</strong>';
if (!empty($filters['publisher']))  $active_filters[] = 'Publisher: <strong>'.htmlspecialchars($filters['publisher']).'</strong>';
if (!empty($filters['computer']))   $active_filters[] = 'Computer: <strong>'.htmlspecialchars($filters['computer']).'</strong>';
if (!empty($filters['department'])) $active_filters[] = 'Dept: <strong>'.htmlspecialchars($filters['department']).'</strong>';
if (!empty($filters['category']))   $active_filters[] = 'Category: <strong>'.htmlspecialchars($filters['category']).'</strong>';
if (!empty($filters['search']))     $active_filters[] = 'Search: <strong>'.htmlspecialchars($filters['search']).'</strong>';
?>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/plugins/itassetdashboard/css/dashboard.css">

<div class="iad-wrap">

  <!-- Page Header -->
  <div class="iad-header">
    <div class="iad-header-left">
      <div class="iad-header-icon" style="background:#7c3aed"><i class="fas fa-box"></i></div>
      <div>
        <h1 class="iad-title">Software Inventory Report</h1>
        <p class="iad-subtitle">
          <?= number_format($total_rows) ?> installation<?= $total_rows !== 1 ? 's' : '' ?> found
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
    <a href="/plugins/itassetdashboard/front/report.php" class="iad-tab">
      <i class="fas fa-laptop"></i> Hardware
    </a>
    <a href="/plugins/itassetdashboard/front/software.php" class="iad-tab iad-tab-active">
      <i class="fas fa-box"></i> Software
    </a>
  </div>

  <!-- Filter Bar -->
  <form method="GET" action="" class="iad-filter-bar" id="swFilterForm">
    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort_col) ?>">
    <input type="hidden" name="dir"  value="<?= htmlspecialchars($sort_dir) ?>">

    <div class="iad-filter-group">
      <label>Search</label>
      <input type="text" name="search" class="iad-input" placeholder="Computer, staff, serial…"
             value="<?= htmlspecialchars($filters['search']) ?>">
    </div>

    <div class="iad-filter-group">
      <label>Software Name</label>
      <select name="software" class="iad-select">
        <option value="">All Software</option>
        <?php foreach ($opts['software_names'] as $sn): ?>
        <option value="<?= htmlspecialchars($sn) ?>" <?= $filters['software']===$sn?'selected':'' ?>>
          <?= htmlspecialchars($sn) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="iad-filter-group">
      <label>Publisher</label>
      <select name="publisher" class="iad-select">
        <option value="">All Publishers</option>
        <?php foreach ($opts['publishers'] as $p): ?>
        <option value="<?= htmlspecialchars($p) ?>" <?= $filters['publisher']===$p?'selected':'' ?>>
          <?= htmlspecialchars($p) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="iad-filter-group">
      <label>Computer</label>
      <select name="computer" class="iad-select">
        <option value="">All Computers</option>
        <?php foreach ($opts['computer_names'] as $cn): ?>
        <option value="<?= htmlspecialchars($cn) ?>" <?= $filters['computer']===$cn?'selected':'' ?>>
          <?= htmlspecialchars($cn) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="iad-filter-group">
      <label>Department</label>
      <select name="department" class="iad-select">
        <option value="">All Departments</option>
        <?php foreach ($opts['departments'] as $d): ?>
        <option value="<?= htmlspecialchars($d) ?>" <?= $filters['department']===$d?'selected':'' ?>>
          <?= htmlspecialchars($d) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="iad-filter-group">
      <label>Category</label>
      <select name="category" class="iad-select">
        <option value="">All Categories</option>
        <?php foreach ($opts['categories'] as $cat): ?>
        <option value="<?= htmlspecialchars($cat) ?>" <?= $filters['category']===$cat?'selected':'' ?>>
          <?= htmlspecialchars($cat) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="iad-filter-actions iad-filter-actions-inline">
      <button type="submit" class="iad-btn iad-btn-primary"><i class="fas fa-search"></i> Filter</button>
      <a href="/plugins/itassetdashboard/front/software.php" class="iad-btn iad-btn-secondary"><i class="fas fa-times"></i> Reset</a>
    </div>

  </form>

  <!-- GLPI-style Table -->
  <div class="iad-glpi-table-wrap">

    <!-- Top pager -->
    <div class="iad-pager-bar">
      <div class="iad-pager-left">
        <select class="iad-rpp-select" id="rppSelect">
          <option value="10">10</option>
          <option value="20" selected>20</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
        <span class="iad-rpp-label">rows / page</span>
      </div>
      <div class="iad-pager-center" id="pagerInfo"></div>
      <div class="iad-pager-right">
        <button class="iad-page-btn" id="btnFirst" onclick="goPage(1)">«</button>
        <button class="iad-page-btn" id="btnPrev"  onclick="goPage(currentPage-1)">‹</button>
        <span class="iad-page-nums" id="pageNums"></span>
        <button class="iad-page-btn" id="btnNext"  onclick="goPage(currentPage+1)">›</button>
        <button class="iad-page-btn" id="btnLast"  onclick="goPage(totalPages)">»</button>
      </div>
    </div>

    <div class="iad-table-scroll">
      <table class="iad-glpi-table">
        <thead>
          <tr>
            <th class="iad-th-check"><input type="checkbox" onclick="toggleAll(this)"></th>
            <?php
            $cols = [
              ['key'=>'SoftwareName',    'label'=>'SOFTWARE NAME'],
              ['key'=>'SoftwareVersion', 'label'=>'VERSION'],
              ['key'=>'Publisher',       'label'=>'PUBLISHER'],
              ['key'=>'SoftwareCategory','label'=>'CATEGORY'],
              ['key'=>'ComputerName',    'label'=>'COMPUTER'],
              ['key'=>'Computer_Model',  'label'=>'PC MODEL'],
              ['key'=>'Manufacturers',   'label'=>'MANUFACTURER'],
              ['key'=>'Status',          'label'=>'STATUS'],
              ['key'=>'Department',      'label'=>'DEPARTMENT'],
              ['key'=>'StaffID',         'label'=>'STAFF ID'],
              ['key'=>'FirstName',       'label'=>'FIRST NAME'],
              ['key'=>'LastName',        'label'=>'LAST NAME'],
              ['key'=>'InstallDate',     'label'=>'INSTALL DATE'],
            ];
            foreach ($cols as $col):
              $url  = sw_sort_url($col['key'], $sort_col, $sort_dir, $base_params);
              $icon = sw_sort_icon($col['key'], $sort_col, $sort_dir);
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
          <tr><td colspan="14" class="iad-no-data">
            <i class="fas fa-inbox"></i> No software records found.
          </td></tr>
          <?php else: ?>
          <?php foreach ($rows as $i => $r):
            $row_class     = ($i % 2 === 0) ? 'iad-row-white' : 'iad-row-grey';
            $computer_link = !empty($r['ComputerID']) ? $glpi_computer_url.(int)$r['ComputerID'] : '#';
            $st = $r['Status'] ?? '';
            $st_cls = 'iad-status-default';
            if (stripos($st,'use')!==false)        $st_cls='iad-status-inuse';
            elseif (stripos($st,'stock')!==false)  $st_cls='iad-status-stock';
            elseif (stripos($st,'maint')!==false)  $st_cls='iad-status-maint';
            elseif (stripos($st,'retire')!==false) $st_cls='iad-status-retire';
          ?>
          <tr class="iad-glpi-row <?= $row_class ?>" data-index="<?= $i ?>">
            <td class="iad-td-check"><input type="checkbox" class="iad-row-check"></td>
            <td class="iad-td-name">
              <i class="fas fa-cube iad-sw-icon"></i>
              <?= htmlspecialchars($r['SoftwareName'] ?? '') ?>
            </td>
            <td class="iad-td-mono"><?= htmlspecialchars($r['SoftwareVersion'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['Publisher'] ?? '') ?></td>
            <td>
              <?php if (!empty($r['SoftwareCategory'])): ?>
              <span class="iad-cat-pill"><?= htmlspecialchars($r['SoftwareCategory']) ?></span>
              <?php endif; ?>
            </td>
            <td>
              <a href="<?= $computer_link ?>" class="iad-name-link">
                <?= htmlspecialchars($r['ComputerName'] ?? '') ?>
              </a>
            </td>
            <td><?= htmlspecialchars($r['Computer_Model'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['Manufacturers'] ?? '') ?></td>
            <td><span class="iad-status-pill <?= $st_cls ?>"><?= htmlspecialchars($st) ?></span></td>
            <td>
              <?php if (!empty($r['Department'])): ?>
              <a href="/plugins/itassetdashboard/front/software.php?department=<?= urlencode($r['Department']) ?>"
                 class="iad-dept-link"><?= htmlspecialchars($r['Department']) ?></a>
              <?php endif; ?>
            </td>
            <td class="iad-td-mono"><?= htmlspecialchars($r['StaffID'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['FirstName'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['LastName'] ?? '') ?></td>
            <td class="iad-td-mono"><?= htmlspecialchars($r['InstallDate'] ?? '') ?></td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Bottom pager -->
    <div class="iad-pager-bar">
      <div class="iad-pager-left"></div>
      <div class="iad-pager-center" id="pagerInfoBottom"></div>
      <div class="iad-pager-right">
        <button class="iad-page-btn" onclick="goPage(1)">«</button>
        <button class="iad-page-btn" id="btnPrevB" onclick="goPage(currentPage-1)">‹</button>
        <span class="iad-page-nums" id="pageNumsB"></span>
        <button class="iad-page-btn" id="btnNextB" onclick="goPage(currentPage+1)">›</button>
        <button class="iad-page-btn" onclick="goPage(totalPages)">»</button>
      </div>
    </div>

  </div><!-- /.iad-glpi-table-wrap -->
</div><!-- /.iad-wrap -->

<script>
const allRows   = Array.from(document.querySelectorAll('#tableBody tr[data-index]'));
let currentPage = 1, rowsPerPage = 20;
let totalPages  = Math.ceil(allRows.length / rowsPerPage);

function renderPage() {
    totalPages = Math.ceil(allRows.length / rowsPerPage) || 1;
    if (currentPage > totalPages) currentPage = totalPages;
    const start = (currentPage - 1) * rowsPerPage, end = start + rowsPerPage;
    allRows.forEach((tr, i) => {
        tr.style.display = (i >= start && i < end) ? '' : 'none';
        tr.classList.toggle('iad-row-white', i % 2 === 0);
        tr.classList.toggle('iad-row-grey',  i % 2 !== 0);
    });
    const s = allRows.length ? start + 1 : 0, e = Math.min(end, allRows.length);
    const info = `Showing ${s} to ${e} of ${allRows.length} rows`;
    document.getElementById('pagerInfo').textContent       = info;
    document.getElementById('pagerInfoBottom').textContent = info;
    renderNums('pageNums'); renderNums('pageNumsB');
    document.getElementById('btnFirst').disabled = document.getElementById('btnPrev').disabled  = currentPage === 1;
    document.getElementById('btnLast').disabled  = document.getElementById('btnNext').disabled  = currentPage >= totalPages;
    document.getElementById('btnPrevB').disabled = currentPage === 1;
    document.getElementById('btnNextB').disabled = currentPage >= totalPages;
}
function renderNums(id) {
    const el = document.getElementById(id); el.innerHTML = '';
    let sp = Math.max(1, currentPage-2), ep = Math.min(totalPages, sp+4);
    sp = Math.max(1, ep-4);
    for (let p = sp; p <= ep; p++) {
        const b = document.createElement('button');
        b.className = 'iad-page-btn' + (p===currentPage?' iad-page-active':'');
        b.textContent = p; b.onclick = ()=>goPage(p); el.appendChild(b);
    }
}
function goPage(p) {
    if (p<1||p>totalPages) return;
    currentPage = p; renderPage();
    window.scrollTo({top:0,behavior:'smooth'});
}
document.getElementById('rppSelect').onchange = function() {
    rowsPerPage = +this.value; currentPage = 1; renderPage();
};
function toggleAll(cb) {
    document.querySelectorAll('.iad-row-check').forEach(c=>c.checked=cb.checked);
}
renderPage();
</script>

<?php Html::footer(); ?>
