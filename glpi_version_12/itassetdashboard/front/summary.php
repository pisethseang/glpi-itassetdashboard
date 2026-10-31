<?php
include('../../../inc/includes.php');
Session::checkRight('computer', READ);

/** Independent Summary page. No redirection into legacy report filters. */
global $DB;
$DBH = $DB;
$sections = ['matrix', 'versions', 'explorer', 'audit'];
$section = $_GET['section'] ?? 'matrix';
if (!in_array($section, $sections, true)) $section = 'matrix';
$selectedApp = trim((string)($_GET['app'] ?? ''));
$selectedDept = trim((string)($_GET['dept'] ?? ''));
$selectedVersion = trim((string)($_GET['version'] ?? ''));
$selectedComputer = max(0, (int)($_GET['computer_id'] ?? 0));
$auditStatus = (string)($_GET['audit_status'] ?? 'stale');
if (!in_array($auditStatus, ['stale','never','all'], true)) $auditStatus = 'stale';
$auditSearch = trim((string)($_GET['audit_search'] ?? ''));
$auditSearch = mb_substr($auditSearch, 0, 100);
$auditDepartment = trim((string)($_GET['audit_department'] ?? ''));
$auditDepartment = mb_substr($auditDepartment, 0, 100);
$detail = $_GET['detail'] ?? '';
$allowedDetails = ['','matrix','version','stale','unassigned','noos','multiversion'];
if (!in_array($detail, $allowedDetails, true)) $detail='';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit=20;
$offset=($page-1)*$limit;
$esc = static function($value) use ($DBH): string { return $DBH->escape((string)$value); };
$h = static function($value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); };
$fetch = static function($sql) use ($DBH): array {
    $data=[];
    try {
        $r=$DBH->doQuery($sql);
        if ($r === false) {
            error_log('[itassetdashboard summary] Database query returned false: ' . $sql);
            $GLOBALS['ias_query_error'] = true;
        } else {
            while ($row=$DBH->fetchAssoc($r)) $data[]=$row;
        }
    } catch (\Throwable $e) {
        // Do not expose SQL or stack traces in the dashboard UI.
        error_log('[itassetdashboard summary] Database query failed: ' . $e->getMessage());
        $GLOBALS['ias_query_error'] = true;
    }
    return $data;
};
$scalar = static function($sql) use ($fetch): int { $rows=$fetch($sql);return (int)($rows[0]['n']??0); };
$link = static function(array $args=[]) {
    return PluginItassetdashboardDashboard::webBase().'/plugins/itassetdashboard/front/summary.php?'.http_build_query($args);
};
// Shared inventory relation: only non-deleted computers and software.
$join="FROM glpi_items_softwareversions isv
    JOIN glpi_computers c ON c.id=isv.items_id AND isv.itemtype='Computer' AND c.is_deleted=0
    JOIN glpi_softwareversions sv ON sv.id=isv.softwareversions_id
    JOIN glpi_softwares s ON s.id=sv.softwares_id AND s.is_deleted=0
    LEFT JOIN glpi_users u ON u.id=c.users_id
    LEFT JOIN glpi_usercategories uc ON uc.id=u.usercategories_id";
$totalComputers=$scalar('SELECT COUNT(*) n FROM glpi_computers WHERE is_deleted=0');
$totalApplications=$scalar("SELECT COUNT(DISTINCT s.id) n $join");
$totalInstalls=$scalar("SELECT COUNT(*) n $join");

Html::header('IT Asset Summary', $_SERVER['PHP_SELF'], 'tools', 'PluginItassetdashboardDashboard');
?><style><?php readfile(__DIR__.'/../css/dashboard.css'); ?></style>
<style><?php readfile(__DIR__.'/../css/summary.css'); ?></style>
<div class="iad-wrap iad-summary-ui">
<?php if (!empty($GLOBALS['ias_query_error'])): ?>
<div class="alert alert-warning">Some summary statistics could not be loaded. Check the GLPI PHP error log for "itassetdashboard summary".</div>
<?php endif; ?>
<div class="iad-header"><div class="iad-header-left"><div class="iad-header-icon"><i class="fas fa-chart-bar"></i></div><div><h1 class="iad-title">IT Asset Summary</h1><p class="iad-subtitle">Application inventory and asset analysis</p></div></div></div>
<div class="iad-tabs">
<a class="iad-tab" href="<?= $h(PluginItassetdashboardDashboard::webBase()) ?>/plugins/itassetdashboard/front/report.php"><i class="fas fa-laptop"></i> Hardware</a>
<a class="iad-tab" href="<?= $h(PluginItassetdashboardDashboard::webBase()) ?>/plugins/itassetdashboard/front/software.php"><i class="fas fa-box"></i> Software</a>
<a class="iad-tab iad-tab-active" href="<?= $h($link()) ?>"><i class="fas fa-chart-bar"></i> Summary</a>
</div>
<div class="ias-kpis">
<div><strong><?= number_format($totalComputers) ?></strong><span>Computers</span></div>
<div><strong><?= number_format($totalApplications) ?></strong><span>Installed application titles</span></div>
<div><strong><?= number_format($totalInstalls) ?></strong><span>Installation records</span></div>
<div><strong>4</strong><span>Analysis sections</span></div>
</div>
<section class="ias-analysis" aria-label="Inventory analysis">
<h2 class="ias-analysis-title">Inventory analysis <small>Select a report</small></h2>
<div class="ias-section-nav">
<?php foreach ([
    'matrix' => ['Application Matrix', 'Applications by department', 'fas fa-table'],
    'versions' => ['Version Analysis', 'Software version distribution', 'fas fa-chart-bar'],
    'explorer' => ['Computer Explorer', 'Hardware and software per device', 'fas fa-desktop'],
    'audit' => ['Inventory Audit', 'Missing and inconsistent records', 'fas fa-shield-alt'],
] as $key => $card): ?>
<a class="ias-analysis-card <?= $section===$key?'active':'' ?>" href="<?= $h($link(['section'=>$key])) ?>" <?= $section===$key?'aria-current="page"':'' ?>>
  <span class="ias-card-icons"><i class="<?= $h($card[2]) ?>" aria-hidden="true"></i><?php if ($section===$key): ?><i class="far fa-check-circle ias-card-check" aria-hidden="true"></i><?php endif; ?></span>
  <span class="ias-card-title"><?= $h($card[0]) ?></span>
  <span class="ias-card-description"><?= $h($card[1]) ?></span>
</a>
<?php endforeach; ?></div>
</section>
<?php if ($section==='matrix'):
    $where='';
    if ($selectedApp!=='') $where.=" AND s.name='".$esc($selectedApp)."'";
    if ($selectedDept!=='') $where.=" AND COALESCE(uc.name, '(No Department)')='".$esc($selectedDept)."'";
    $matrix=$fetch("SELECT s.name application, COALESCE(uc.name,'(No Department)') department,
       COUNT(DISTINCT c.id) computers $join WHERE 1=1 $where GROUP BY s.name,uc.name
       ORDER BY computers DESC, application ASC LIMIT 30");
    $depts=$fetch("SELECT DISTINCT COALESCE(uc.name,'(No Department)') name FROM glpi_computers c LEFT JOIN glpi_users u ON u.id=c.users_id LEFT JOIN glpi_usercategories uc ON uc.id=u.usercategories_id WHERE c.is_deleted=0 ORDER BY name");
?>
<h2>Application Deployment Matrix</h2><p>Compare installations by application and assigned user's category/department. Choose a filter to narrow results.</p>
<form class="ias-filters" method="get"><input type="hidden" name="section" value="matrix">
<label>Application <input class="ias-app-autocomplete" type="search" name="app" value="<?= $h($selectedApp) ?>" placeholder="Search applications (e.g. 365)..." autocomplete="off" data-source="<?= $h(PluginItassetdashboardDashboard::webBase().'/plugins/itassetdashboard/front/application_search.php') ?>"></label>
<label>Department <select class="iad-select" name="dept"><option value="">All departments</option><?php foreach($depts as $d): ?><option value="<?= $h($d['name']) ?>" <?= $selectedDept===$d['name']?'selected':'' ?>><?= $h($d['name']) ?></option><?php endforeach; ?></select></label>
<button type="submit">Apply</button></form>
<div class="ias-scroll"><table class="ias-table"><thead><tr><th>Application</th><th>Department</th><th>Computers (view)</th></tr></thead><tbody>
<?php foreach($matrix as $r): ?><tr><td><?= $h($r['application']) ?></td><td><?= $h($r['department']) ?></td><td><a href="<?= $h($link(['section'=>'matrix','app'=>$r['application'],'dept'=>$r['department'],'detail'=>'matrix'])) ?>"><?= (int)$r['computers'] ?></a></td></tr><?php endforeach; ?>
</tbody></table></div><p class="ias-note">Top 30 combinations shown; apply filters to narrow results.</p>
<?php if ($detail==='matrix' && $selectedApp!=='' && $selectedDept!==''):
 $w=" AND s.name='".$esc($selectedApp)."' AND COALESCE(uc.name,'(No Department)')='".$esc($selectedDept)."'";
 $rows=$fetch("SELECT DISTINCT c.id, c.name computer, COALESCE(sv.name,'') version $join WHERE 1=1 $w ORDER BY computer LIMIT $limit OFFSET $offset");
?>
<h3>Computers: <?= $h($selectedApp) ?> / <?= $h($selectedDept) ?></h3>
<?php render_ias_computers($rows,$h); render_ias_next($rows,$page,$limit,$link,['section'=>'matrix','app'=>$selectedApp,'dept'=>$selectedDept,'detail'=>'matrix'],$h); endif; ?>
<?php elseif ($section==='versions'):
  // Search on demand; avoid grouping the entire inventory on every page visit.
  $w=" AND s.name='".$esc($selectedApp)."'";
  $versions=$selectedApp!==''?$fetch("SELECT COALESCE(sv.name,'(Unknown)') version, COUNT(DISTINCT c.id) computers $join WHERE 1=1 $w GROUP BY sv.name ORDER BY computers DESC LIMIT 100"):[];
?>
<h2>Application Version Analysis</h2><p>Compare detected versions; version differences do not automatically indicate vulnerabilities.</p>
<form class="ias-filters" method="get"><input type="hidden" name="section" value="versions"><label>Application <input class="ias-app-autocomplete" type="search" name="app" value="<?= $h($selectedApp) ?>" placeholder="Search applications (e.g. 365)..." autocomplete="off" data-source="<?= $h(PluginItassetdashboardDashboard::webBase().'/plugins/itassetdashboard/front/application_search.php') ?>"></label><button type="submit">Analyze</button></form>
<?php if ($selectedApp===''): ?><p class="ias-note">Search for an application to see its installed versions.</p><?php endif; ?>
<div class="ias-scroll"><table class="ias-table"><thead><tr><th>Version</th><th>Computers</th></tr></thead><tbody><?php foreach($versions as $v): ?><tr><td><?= $h($v['version']) ?></td><td><a href="<?= $h($link(['section'=>'versions','app'=>$selectedApp,'version'=>$v['version'],'detail'=>'version'])) ?>"><?= (int)$v['computers'] ?></a></td></tr><?php endforeach; ?></tbody></table></div>
<?php if ($detail==='version' && $selectedApp!=='' && $selectedVersion!==''):
 $w=" AND s.name='".$esc($selectedApp)."' AND COALESCE(sv.name,'(Unknown)')='".$esc($selectedVersion)."'";
 $rows=$fetch("SELECT DISTINCT c.id,c.name computer, COALESCE(sv.name,'') version $join WHERE 1=1 $w ORDER BY computer LIMIT $limit OFFSET $offset");
?><h3><?= $h($selectedApp) ?> — <?= $h($selectedVersion) ?></h3><?php render_ias_computers($rows,$h); render_ias_next($rows,$page,$limit,$link,['section'=>'versions','app'=>$selectedApp,'version'=>$selectedVersion,'detail'=>'version'],$h); endif; ?>
<?php elseif ($section==='explorer'):
 $computers=$fetch("SELECT c.id,c.name FROM glpi_computers c WHERE c.is_deleted=0 ORDER BY c.name LIMIT 2000");
 if (!$selectedComputer && $computers) $selectedComputer=(int)$computers[0]['id'];
 $data=$selectedComputer?$fetch("SELECT c.id,c.name,COALESCE(c.serial,'') serial,COALESCE(cm.name,'') model,COALESCE(m.name,'') manufacturer,COALESCE(u.realname,'') lastname,COALESCE(u.firstname,'') firstname,COALESCE(uc.name,'') department FROM glpi_computers c LEFT JOIN glpi_computermodels cm ON cm.id=c.computermodels_id LEFT JOIN glpi_manufacturers m ON m.id=c.manufacturers_id LEFT JOIN glpi_users u ON u.id=c.users_id LEFT JOIN glpi_usercategories uc ON uc.id=u.usercategories_id WHERE c.id=$selectedComputer AND c.is_deleted=0 LIMIT 1"):[];
 $software=$data?$fetch("SELECT DISTINCT s.name application,COALESCE(sv.name,'') version $join WHERE c.id=$selectedComputer ORDER BY application,version LIMIT $limit OFFSET $offset"):[];
?>
<h2>Computer Inventory Explorer</h2><p>Select a GLPI computer to inspect its assigned user and installed applications.</p>
<form class="ias-filters" method="get"><input type="hidden" name="section" value="explorer"><label>Computer <select class="iad-select" name="computer_id"><?php foreach($computers as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $selectedComputer===(int)$c['id']?'selected':'' ?>><?= $h($c['name']) ?></option><?php endforeach; ?></select></label><button type="submit">View computer</button></form>
<?php if($data): $c=$data[0]; ?><div class="ias-profile"><strong><?= $h($c['name']) ?></strong><span>Department: <?= $h($c['department']) ?></span><span>Assigned: <?= $h(trim($c['firstname'].' '.$c['lastname'])) ?></span><span>Manufacturer: <?= $h($c['manufacturer']) ?></span><span>Model: <?= $h($c['model']) ?></span><span>Serial: <?= $h($c['serial']) ?></span></div>
<h3>Installed applications</h3><div class="ias-scroll"><table class="ias-table"><thead><tr><th>Application</th><th>Version</th></tr></thead><tbody><?php foreach($software as $s): ?><tr><td><?= $h($s['application']) ?></td><td><?= $h($s['version']) ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php render_ias_next($software,$page,$limit,$link,['section'=>'explorer','computer_id'=>$selectedComputer],$h); else: ?><p>No computer found.</p><?php endif; ?>
<?php elseif ($section==='audit'):
 // The actual agent inventory timestamp; do not substitute the asset modification date.
 $inventoryColumn = $fetch("SHOW COLUMNS FROM glpi_computers LIKE 'last_inventory_update'");
 $hasInventoryDate = !empty($inventoryColumn);
 $isNever = "(c.last_inventory_update IS NULL OR c.last_inventory_update='0000-00-00 00:00:00')";
 $isStale = "(c.last_inventory_update IS NOT NULL AND c.last_inventory_update <> '0000-00-00 00:00:00' AND c.last_inventory_update < DATE_SUB(NOW(), INTERVAL 30 DAY))";
 $stale = $hasInventoryDate ? $scalar("SELECT COUNT(*) n FROM glpi_computers c WHERE c.is_deleted=0 AND $isStale") : null;
 $never = $hasInventoryDate ? $scalar("SELECT COUNT(*) n FROM glpi_computers c WHERE c.is_deleted=0 AND $isNever") : null;
 $recent = $hasInventoryDate ? $scalar("SELECT COUNT(*) n FROM glpi_computers c WHERE c.is_deleted=0 AND c.last_inventory_update >= DATE_SUB(NOW(), INTERVAL 30 DAY)") : null;
 $unassigned=$scalar('SELECT COUNT(*) n FROM glpi_computers WHERE is_deleted=0 AND (users_id=0 OR users_id IS NULL)');
 $noos=$scalar("SELECT COUNT(*) n FROM glpi_computers c WHERE c.is_deleted=0 AND NOT EXISTS (SELECT 1 FROM glpi_items_operatingsystems io WHERE io.items_id=c.id AND io.itemtype='Computer')");
 $multi=$scalar("SELECT COUNT(*) n FROM (SELECT s.id FROM glpi_softwares s JOIN glpi_softwareversions sv ON sv.softwares_id=s.id JOIN glpi_items_softwareversions isv ON isv.softwareversions_id=sv.id AND isv.itemtype='Computer' JOIN glpi_computers c ON c.id=isv.items_id AND c.is_deleted=0 WHERE s.is_deleted=0 GROUP BY s.id HAVING COUNT(DISTINCT sv.name)>1) x");
 $types=['stale'=>['Computers not inventoried for over 30 days',$stale], 'unassigned'=>['Computers without assigned user',$unassigned], 'noos'=>['Computers missing OS inventory',$noos], 'multiversion'=>['Applications with multiple installed versions',$multi]];
?>
<div class="ias-panel-heading"><div><h2>Inventory Audit</h2><p>Monitor agent inventory freshness and data-quality exceptions.</p></div><i class="fas fa-clipboard-check"></i></div>
<?php if (!$hasInventoryDate): ?>
<div class="alert alert-warning">The <code>glpi_computers.last_inventory_update</code> field was not found. Inventory freshness is unavailable; no modification-date fallback is used.</div>
<?php else: ?>
<div class="ias-audit-stats">
<a href="<?= $h($link(['section'=>'audit','detail'=>'stale','audit_status'=>'stale'])) ?>"><span class="ias-stat-icon"><i class="fas fa-clock"></i></span><strong><?= number_format((int)$stale) ?></strong><span>Not inventoried over 30 days</span></a>
<a href="<?= $h($link(['section'=>'audit','detail'=>'stale','audit_status'=>'never'])) ?>"><span class="ias-stat-icon"><i class="fas fa-question-circle"></i></span><strong><?= number_format((int)$never) ?></strong><span>Never inventoried</span></a>
<div><span class="ias-stat-icon"><i class="fas fa-check-circle"></i></span><strong><?= number_format((int)$recent) ?></strong><span>Inventoried within 30 days</span></div>
</div>
<?php endif; ?>
<h3>Additional audit checks</h3>
<div class="ias-audit-grid">
<?php foreach (['unassigned','noos','multiversion'] as $key): $value=$types[$key]; ?>
<a href="<?= $h($link(['section'=>'audit','detail'=>$key])) ?>"><strong><?= number_format((int)$value[1]) ?></strong><span><?= $h($value[0]) ?></span></a>
<?php endforeach; ?>
</div>
<?php if ($detail==='stale' && $hasInventoryDate):
 $departments=$fetch("SELECT DISTINCT COALESCE(uc.name,'(No Department)') name FROM glpi_computers c LEFT JOIN glpi_users u ON u.id=c.users_id LEFT JOIN glpi_usercategories uc ON uc.id=u.usercategories_id WHERE c.is_deleted=0 ORDER BY name");
 $dateWhere = $auditStatus==='never' ? $isNever : ($auditStatus==='stale' ? $isStale : "($isStale OR $isNever)");
 $filters = "c.is_deleted=0 AND $dateWhere";
 if ($auditSearch !== '') { $safe=$esc($auditSearch); $filters .= " AND (c.name LIKE '%$safe%' OR COALESCE(u.name,'') LIKE '%$safe%')"; }
 if ($auditDepartment !== '') { $safe=$esc($auditDepartment); $filters .= " AND COALESCE(uc.name,'(No Department)')='$safe'"; }
 $totalMatching=$scalar("SELECT COUNT(*) n FROM glpi_computers c LEFT JOIN glpi_users u ON u.id=c.users_id LEFT JOIN glpi_usercategories uc ON uc.id=u.usercategories_id WHERE $filters");
 $rows=$fetch("SELECT c.id,c.name computer,COALESCE(uc.name,'(No Department)') department, TRIM(CONCAT(COALESCE(u.firstname,''),' ',COALESCE(u.realname,''))) assigned_user,c.last_inventory_update last_inventory, DATEDIFF(NOW(),c.last_inventory_update) days_since FROM glpi_computers c LEFT JOIN glpi_users u ON u.id=c.users_id LEFT JOIN glpi_usercategories uc ON uc.id=u.usercategories_id WHERE $filters ORDER BY CASE WHEN c.last_inventory_update IS NULL THEN 0 ELSE 1 END, c.last_inventory_update ASC, c.name ASC LIMIT $limit OFFSET $offset");
 $params=['section'=>'audit','detail'=>'stale','audit_status'=>$auditStatus,'audit_search'=>$auditSearch,'audit_department'=>$auditDepartment];
?>
<div class="ias-audit-detail">
<h3>Inventory freshness — computer details</h3>
<form class="ias-filters ias-audit-filters" method="get">
<input type="hidden" name="section" value="audit"><input type="hidden" name="detail" value="stale">
<label>Status<select name="audit_status"><option value="stale" <?= $auditStatus==='stale'?'selected':'' ?>>Over 30 days</option><option value="never" <?= $auditStatus==='never'?'selected':'' ?>>Never inventoried</option><option value="all" <?= $auditStatus==='all'?'selected':'' ?>>Over 30 days + never</option></select></label>
<label>Computer or username<input type="search" name="audit_search" value="<?= $h($auditSearch) ?>" placeholder="Search computer or user..."></label>
<label>Department<select class="iad-select" name="audit_department"><option value="">All departments</option><?php foreach ($departments as $d): ?><option value="<?= $h($d['name']) ?>" <?= $auditDepartment===$d['name']?'selected':'' ?>><?= $h($d['name']) ?></option><?php endforeach; ?></select></label>
<button type="submit"><i class="fas fa-search"></i> Apply filters</button>
</form>
<p class="ias-note"><?= number_format($totalMatching) ?> matching computers · Based on last successful inventory update, not asset modification.</p>
<div class="ias-scroll"><table class="ias-table"><thead><tr><th>Computer</th><th>Department</th><th>Assigned user</th><th>Last inventory</th><th>Days since</th><th>GLPI asset</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= $h($r['computer']) ?></td><td><?= $h($r['department']) ?></td><td><?= $h($r['assigned_user']?:'Unassigned') ?></td><td><?= $h(($r['last_inventory']===null || $r['last_inventory']==='0000-00-00 00:00:00')?'Never':$r['last_inventory']) ?></td><td><?= $r['days_since']===null?'—':(int)$r['days_since'] ?></td><td><a href="<?= $h(PluginItassetdashboardDashboard::webBase().'/front/computer.form.php?id='.(int)$r['id']) ?>">View</a></td></tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="6">No computers match these filters.</td></tr><?php endif; ?>
</tbody></table></div>
<?php render_ias_next($rows,$page,$limit,$link,$params,$h); ?>
</div>
<?php elseif($detail && $detail!=='stale' && isset($types[$detail])):
 if($detail==='unassigned') $rows=$fetch("SELECT c.id,c.name computer FROM glpi_computers c WHERE c.is_deleted=0 AND (c.users_id=0 OR c.users_id IS NULL) ORDER BY c.name LIMIT $limit OFFSET $offset");
 elseif($detail==='noos') $rows=$fetch("SELECT c.id,c.name computer FROM glpi_computers c WHERE c.is_deleted=0 AND NOT EXISTS (SELECT 1 FROM glpi_items_operatingsystems io WHERE io.items_id=c.id AND io.itemtype='Computer') ORDER BY c.name LIMIT $limit OFFSET $offset");
 else $rows=$fetch("SELECT s.id,s.name application,COUNT(DISTINCT sv.name) versions $join GROUP BY s.id,s.name HAVING versions>1 ORDER BY versions DESC LIMIT $limit OFFSET $offset");
?><h3><?= $h($types[$detail][0]) ?></h3><div class="ias-scroll"><table class="ias-table"><thead><tr><th><?= $detail==='multiversion'?'Application':'Computer' ?></th><th><?= $detail==='multiversion'?'Versions':'GLPI asset' ?></th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= $h($r['computer']??$r['application']??'') ?></td><td><?php if($detail==='multiversion'): ?><?= (int)$r['versions'] ?><?php else: ?><a href="<?= $h(PluginItassetdashboardDashboard::webBase().'/front/computer.form.php?id='.(int)$r['id']) ?>">View computer</a><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php render_ias_next($rows,$page,$limit,$link,['section'=>'audit','detail'=>$detail],$h); endif; ?>
<?php endif; ?>
</div>
<script><?php readfile(__DIR__.'/../js/searchable-filters.js'); ?></script>
<script><?php readfile(__DIR__.'/../js/summary-app-search.js'); ?></script>
<?php Html::footer();
function render_ias_computers(array $rows, callable $h): void { ?>
<div class="ias-scroll"><table class="ias-table"><thead><tr><th>Computer</th><th>Installed version</th><th>GLPI asset</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= $h($r['computer']) ?></td><td><?= $h($r['version']??'') ?></td><td><a href="<?= $h(PluginItassetdashboardDashboard::webBase().'/front/computer.form.php?id='.(int)$r['id']) ?>">View</a></td></tr><?php endforeach; ?></tbody></table></div><?php }
function render_ias_next(array $rows, int $page, int $limit, callable $link, array $params, callable $h): void { ?>
<div class="ias-pager"><?php if($page>1): ?><a href="<?= $h($link(array_merge($params,['page'=>$page-1]))) ?>">Previous</a><?php endif; ?>
<span>Page <?= $page ?></span>
<?php if(count($rows)===$limit): ?><a href="<?= $h($link(array_merge($params,['page'=>$page+1]))) ?>">Next</a><?php endif; ?></div><?php }
