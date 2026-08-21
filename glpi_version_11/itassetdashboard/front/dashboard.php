<?php
define('DO_NOT_CHECK_HTTP_REFERER', 1);
include('../../../inc/includes.php');

Session::checkRight('computer', READ);

Html::header('IT Asset Dashboard', $_SERVER['PHP_SELF'], 'tools', 'PluginItassetdashboardDashboard', 'dashboard');
?><style><?php readfile(dirname(__FILE__).'/../css/dashboard.css'); ?></style>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script><?php

$stats  = PluginItassetdashboardDashboard::getSummaryStats();
$sw     = PluginItassetdashboardDashboard::getSoftwareStats();
$h      = PluginItassetdashboardDashboard::getHealthStats();

$by_os_labels   = json_encode(array_column($stats['by_os'],           'os'));
$by_os_data     = json_encode(array_column($stats['by_os'],           'cnt'));
$by_mfr_labels  = json_encode(array_column($stats['by_manufacturer'], 'manufacturer'));
$by_mfr_data    = json_encode(array_column($stats['by_manufacturer'], 'cnt'));
$by_dept_labels = json_encode(array_column($stats['by_department'],   'department'));
$by_dept_data   = json_encode(array_column($stats['by_department'],   'cnt'));
$by_stat_labels = json_encode(array_column($stats['by_status'],       'status'));
$by_stat_data   = json_encode(array_column($stats['by_status'],       'cnt'));
$sw_top_labels  = json_encode(array_column($sw['top_software'],       'software'));
$sw_top_data    = json_encode(array_column($sw['top_software'],       'cnt'));
$sw_pub_labels  = json_encode(array_column($sw['by_publisher'],       'publisher'));
$sw_pub_data    = json_encode(array_column($sw['by_publisher'],       'cnt'));

$report_url   = '/plugins/itassetdashboard/front/report.php';
$software_url = '/plugins/itassetdashboard/front/software.php';
$glpi_pc      = '/front/computer.form.php?id=';

$av_pct      = $h['total'] > 0 ? round($h['av_protected'] / $h['total'] * 100) : 0;
$disk_alerts = $h['disk_summary']['critical'] + $h['disk_summary']['warning'];

function fmt_mb($mb) {
    if ($mb <= 0) return '0 B';
    if ($mb >= 1024) return round($mb/1024, 1).' GB';
    return $mb.' MB';
}
?>
<meta name="viewport" content="width=device-width, initial-scale=1">


<div class="iad-dash">

  <!-- ── TOP HEADER ── -->
  <div class="iad-dash-header">
    <div class="iad-dash-header-left">
      <div class="iad-header-icon"><i class="fas fa-desktop"></i></div>
      <div>
        <h1 class="iad-title">INVENTORY DASHBOARD</h1>
        <!-- <p class="iad-subtitle">Hardware &amp; Software inventory — click any chart to filter</p> -->
      </div>
    </div>
    <div class="iad-dash-header-right">
      <a href="/plugins/itassetdashboard/front/report.php" class="iad-btn iad-btn-primary">
        <i class="fas fa-table"></i> Full Report
      </a>
    </div>
  </div>

  <!-- ── TWO-COLUMN GRID ── -->
  <div class="iad-two-col">

    <!-- ════ LEFT COLUMN — HARDWARE ════ -->
    <div class="iad-col">

      <!-- <div class="iad-col-label"><i class="fas fa-laptop"></i> HARDWARE</div> -->

      <!-- HW KPI row -->
      <div class="iad-kpi-mini-row">
        <a href="<?= $report_url ?>" class="iad-kpi-mini iad-kpi-mini-blue">
          <i class="fas fa-laptop"></i>
          <div class="iad-kpi-mini-val"><?= number_format($stats['total']) ?></div>
          <div class="iad-kpi-mini-lbl">Total PCs</div>
        </a>
        <a href="<?= $report_url ?>?assigned=1" class="iad-kpi-mini iad-kpi-mini-green">
          <i class="fas fa-user-check"></i>
          <div class="iad-kpi-mini-val"><?= number_format($stats['assigned']) ?></div>
          <div class="iad-kpi-mini-lbl">Assigned</div>
        </a>
        <a href="<?= $report_url ?>?assigned=0" class="iad-kpi-mini iad-kpi-mini-orange">
          <i class="fas fa-user-times"></i>
          <div class="iad-kpi-mini-val"><?= number_format($stats['unassigned']) ?></div>
          <div class="iad-kpi-mini-lbl">Unassigned</div>
        </a>
        <a href="<?= $report_url ?>" class="iad-kpi-mini iad-kpi-mini-purple">
          <i class="fas fa-building"></i>
          <div class="iad-kpi-mini-val"><?= count($stats['by_department']) ?></div>
          <div class="iad-kpi-mini-lbl">Departments</div>
        </a>
      </div>

      <!-- HW Charts 2-col -->
      <div class="iad-chart-pair">
        <div class="iad-chart-box">
          <div class="iad-box-hdr"><i class="fab fa-windows"></i> Computer By OS <span class="iad-hint">click</span></div>
          <div class="iad-box-body"><canvas id="chartOS"></canvas></div>
        </div>
        <div class="iad-chart-box">
          <div class="iad-box-hdr"><i class="fas fa-tag"></i> By Status <span class="iad-hint">click</span></div>
          <div class="iad-box-body"><canvas id="chartStatus"></canvas></div>
        </div>
      </div>

      <div class="iad-chart-pair">
        <div class="iad-chart-box">
          <div class="iad-box-hdr"><i class="fas fa-sitemap"></i> By Department <span class="iad-hint">click</span></div>
          <div class="iad-box-body"><canvas id="chartDept"></canvas></div>
        </div>
        <div class="iad-chart-box">
          <div class="iad-box-hdr"><i class="fas fa-industry"></i> By Manufacturer <span class="iad-hint">click</span></div>
          <div class="iad-box-body"><canvas id="chartMfr"></canvas></div>
        </div>
      </div>

      <!-- HW Status mini-table -->
      <?php if (!empty($stats['by_status'])): ?>
      <div class="iad-mini-table-card">
        <div class="iad-mini-table-hdr"><i class="fas fa-list-alt"></i> Status Breakdown</div>
        <table class="iad-mini-table">
          <thead><tr><th>Status</th><th>Count</th><th>Share</th></tr></thead>
          <tbody>
            <?php foreach ($stats['by_status'] as $row):
              $pct = $stats['total'] > 0 ? round($row['cnt']/$stats['total']*100,1) : 0; ?>
            <tr class="iad-clickable-row"
                onclick="location='<?= $report_url ?>?status=<?= urlencode($row['status']) ?>'">
              <td><?= htmlspecialchars($row['status']) ?></td>
              <td><strong><?= $row['cnt'] ?></strong></td>
              <td><div class="iad-bar-wrap"><div class="iad-bar" style="width:<?= $pct ?>%"></div><span><?= $pct ?>%</span></div></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>



      <!-- ── Old Devices > 5 Years with Purchase Date ── -->
      <?php if (!empty($h['old_devices'])): ?>
      <div class="iad-mini-table-card">
        <div class="iad-mini-table-hdr iad-hdr-old">
          <i class="fas fa-history"></i> Old Devices &gt;5 Years
          <span class="iad-mini-table-badge">
            <span class="iad-status-dot iad-dot-amber"></span> <?= $h['old_total'] ?> devices
          </span>
          <a href="<?= $report_url ?>" class="iad-mini-table-link">Report ↗</a>
        </div>
        <!-- Age bucket pills -->
        <div class="iad-old-bucket-row">
          <div class="iad-old-bucket iad-ob-amber"><b><?= $h['old_buckets']['y5_6'] ?></b><small>5–6 yrs</small></div>
          <div class="iad-old-bucket iad-ob-orange"><b><?= $h['old_buckets']['y7_9'] ?></b><small>7–9 yrs</small></div>
          <div class="iad-old-bucket iad-ob-red"><b><?= $h['old_buckets']['y10plus'] ?></b><small>10+ yrs</small></div>
        </div>
        <div class="iad-scroll-table-wrap" id="wrap-old">
        <table class="iad-mini-table iad-scroll-table">
          <thead><tr><th>Computer</th><th>Dept</th><th>Purchased</th><th>Age</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($h['old_devices'] as $i => $d):
              $age = (int)$d['age_years'];
              $age_cls = $age >= 10 ? 'iad-age-crit' : ($age >= 7 ? 'iad-age-warn' : 'iad-age-old');
              $lnk = !empty($d['id']) ? '/front/computer.form.php?id='.(int)$d['id'] : '#';
            ?>
            <tr class="iad-clickable-row<?= $i >= 5 ? ' iad-row-extra' : '' ?>" onclick="location='<?= $lnk ?>'">
              <td><a href="<?= $lnk ?>" class="iad-pc-link" onclick="event.stopPropagation()"><i class="fas fa-desktop" style="color:#94a3b8;font-size:10px;margin-right:3px"></i><?= htmlspecialchars($d['computer_name']) ?></a></td>
              <td style="font-size:11px;color:#64748b"><?= htmlspecialchars($d['department']) ?></td>
              <td style="font-size:11px;color:#64748b;white-space:nowrap">
                <?php if (!empty($d['buy_date_fmt'])): ?>
                <i class="fas fa-calendar-alt" style="color:#94a3b8;font-size:9px"></i> <?= htmlspecialchars($d['buy_date_fmt']) ?>
                <?php else: ?><span style="color:#cbd5e1">—</span><?php endif; ?>
              </td>
              <td><span class="iad-age-tag <?= $age_cls ?>"><?= $age ?> yrs</span></td>
              <!-- <td>
                <a href="<?= $lnk ?>" class="iad-act-btn iad-act-blue" onclick="event.stopPropagation()" title="GLPI"><i class="fas fa-external-link-alt"></i></a>
              </td> -->
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        </div><!-- /wrap-old -->
        <?php if ($h['old_total'] > 5): ?>
        <div class="iad-show-more-bar">
          <button class="iad-show-more-btn" onclick="toggleRows('old', <?= $h['old_total'] ?>, this)">
            <i class="fas fa-chevron-down"></i> Show all <?= $h['old_total'] ?> devices
          </button>
          <a href="<?= $report_url ?>" class="iad-show-more-link">View in report ↗</a>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>






    </div><!-- /left col -->

    <!-- ════ RIGHT COLUMN — SOFTWARE ════ -->
    <div class="iad-col">

      <!-- <div class="iad-col-label iad-col-label-sw"><i class="fas fa-box"></i> SOFTWARE</div> -->

      <!-- SW KPI row -->
      <div class="iad-kpi-mini-row">
        <a href="<?= $software_url ?>" class="iad-kpi-mini iad-kpi-mini-swpurple">
          <i class="fas fa-cubes"></i>
          <div class="iad-kpi-mini-val"><?= number_format($sw['total_titles']) ?></div>
          <div class="iad-kpi-mini-lbl">Titles</div>
        </a>
        <a href="<?= $software_url ?>" class="iad-kpi-mini iad-kpi-mini-swindigo">
          <i class="fas fa-download"></i>
          <div class="iad-kpi-mini-val"><?= number_format($sw['total_installs']) ?></div>
          <div class="iad-kpi-mini-lbl">Installs</div>
        </a>
        <a href="<?= $software_url ?>" class="iad-kpi-mini iad-kpi-mini-swteal">
          <i class="fas fa-building"></i>
          <div class="iad-kpi-mini-val"><?= count($sw['by_publisher']) ?></div>
          <div class="iad-kpi-mini-lbl">Publishers</div>
        </a>
        <a href="" class="iad-kpi-mini iad-kpi-mini-swpink">
          <i class="fas fa-laptop-code"></i>
          <div class="iad-kpi-mini-val"><?= $stats['total'] > 0 ? round($sw['total_installs']/$stats['total'],1) : 0 ?></div>
          <div class="iad-kpi-mini-lbl">Avg / PC</div>
        </a>
      </div>

      <!-- Security KPI row — separate items -->
      <div class="iad-kpi-mini-row iad-kpi-security-row">
        <a href="<?= $software_url ?>?software=Cisco+Secure+Endpoint" class="iad-kpi-mini <?= $h['av_unprotected']>0?'iad-kpi-mini-danger':'iad-kpi-mini-safe' ?>">
          <i class="fas fa-shield-alt"></i>
          <div class="iad-kpi-mini-val"><?= $av_pct ?>%</div>
          <div class="iad-kpi-mini-lbl">Cisco SE</div>
          <div class="iad-kpi-mini-sub"><?= $h['av_unprotected'] ?> missing</div>
        </a>
        <a href="" class="iad-kpi-mini <?= $disk_alerts>0?'iad-kpi-mini-danger':'iad-kpi-mini-safe' ?>">
          <i class="fas fa-hdd"></i>
          <div class="iad-kpi-mini-val"><?= $disk_alerts ?></div>
          <div class="iad-kpi-mini-lbl">Disk Alerts C:</div>
          <div class="iad-kpi-mini-sub"><?= $h['disk_summary']['critical'] ?> critical</div>
        </a>
        <a href="" class="iad-kpi-mini <?= $h['old_total']>0?'iad-kpi-mini-warn':'iad-kpi-mini-safe' ?>">
          <i class="fas fa-history"></i>
          <div class="iad-kpi-mini-val"><?= $h['old_total'] ?></div>
          <div class="iad-kpi-mini-lbl">PC old &gt;5 Yrs</div>
          <div class="iad-kpi-mini-sub"><?= $h['old_buckets']['y10plus'] ?> over 10yr</div>
        </a>
      </div>

      <!-- SW Charts 2-col: Top Installed table + Publisher chart -->
      <div class="iad-chart-pair">

        <div class="iad-chart-box iad-chart-box-sw">
          <div class="iad-box-hdr iad-box-hdr-sw"><i class="fas fa-trophy"></i> Top Installed <span class="iad-hint">click</span></div>
          <div class="iad-box-body"><canvas id="chartTopSw"></canvas></div>
        </div>

        <div class="iad-chart-box iad-chart-box-sw">
          <div class="iad-box-hdr iad-box-hdr-sw"><i class="fas fa-building"></i> By Publisher <span class="iad-hint">click</span></div>
          <div class="iad-box-body"><canvas id="chartPublisher"></canvas></div>
        </div>
      </div>
<!-- Cisco SE + Disk section -->
      <!-- ── Cisco Secure Endpoint (replaces Top Installed table) ── -->
      <div class="iad-mini-table-card">
        <div class="iad-mini-table-hdr <?= $h['av_unprotected'] > 10 ? 'iad-hdr-danger' : 'iad-hdr-safe' ?>">
          <i class="fas fa-shield-alt"></i> Cisco Secure Endpoint
          <span class="iad-mini-table-badge">
            <?php if ($h['av_unprotected'] === 0): ?>
              <span class="iad-status-dot iad-dot-green"></span> All Protected
            <?php else: ?>
              <span class="iad-status-dot iad-dot-red"></span> <?= $h['av_unprotected'] ?> Missing
            <?php endif; ?>
          </span>
          <a href="<?= $software_url ?>?software=Cisco+Secure+Endpoint" class="iad-mini-table-link">View ↗</a>
        </div>

        <!-- Coverage bar -->
        <div class="iad-av-cover-wrap">
          <div class="iad-av-cover-bar">
            <div class="iad-av-cover-fill" style="width:<?= $av_pct ?>%"></div>
          </div>
          <div class="iad-av-cover-meta">
            <span class="iad-av-pct-label"><?= $av_pct ?>% coverage</span>
            <span class="iad-av-chips">
              <span class="iad-av-chip iad-av-chip-green"><i class="fas fa-check-circle"></i> <?= $h['av_protected'] ?> Protected</span>
              <span class="iad-av-chip iad-av-chip-red"><i class="fas fa-times-circle"></i> <?= $h['av_unprotected'] ?> Missing</span>
            </span>
          </div>
        </div>

        <!-- Unprotected computer list -->
        <?php if (!empty($h['av_unprotected_list'])): ?>
        <div class="iad-av-unprotected-label"><i class="fas fa-exclamation-triangle"></i> Computers without Cisco SE</div>
        <div class="iad-scroll-table-wrap" id="wrap-cisco">
        <table class="iad-mini-table iad-scroll-table">
          <thead><tr><th>Computer</th><th>Department</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($h['av_unprotected_list'] as $i => $pc):
              $lnk = !empty($pc['computer_id']) ? $glpi_pc.(int)$pc['computer_id'] : '#';
            ?>
            <tr class="<?= $i >= 5 ? 'iad-row-extra' : '' ?>">
              <td><a href="<?= $lnk ?>" class="iad-pc-link"><i class="fas fa-desktop" style="color:#94a3b8;font-size:10px;margin-right:4px"></i><?= htmlspecialchars($pc['computer_name']) ?></a></td>
              <td style="font-size:11px;color:#64748b"><?= htmlspecialchars($pc['department']) ?></td>
              <!-- <td>
                <a href="<?= $lnk ?>" class="iad-act-btn iad-act-blue" title="Open in GLPI"><i class="fas fa-external-link-alt"></i></a>
                <a href="<?= $software_url ?>?computer=<?= urlencode($pc['computer_name']) ?>" class="iad-act-btn" title="View software"><i class="fas fa-box"></i></a>
              </td> -->
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        </div><!-- /wrap-cisco -->
        <?php if (count($h['av_unprotected_list']) > 5): ?>
        <div class="iad-show-more-bar">
          <button class="iad-show-more-btn" onclick="toggleRows('cisco', <?= count($h['av_unprotected_list']) ?>, this)">
            <i class="fas fa-chevron-down"></i> Show all <?= count($h['av_unprotected_list']) ?> computers
          </button>
          <a href="<?= $software_url ?>?software=Cisco+Secure+Endpoint" class="iad-show-more-link">View in software report ↗</a>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <div class="iad-av-all-ok"><i class="fas fa-check-circle"></i> All computers have Cisco Secure Endpoint installed</div>
        <?php endif; ?>
      </div>

      <!-- ── Disk Usage — C: Drive ── -->
      <div class="iad-mini-table-card">
        <div class="iad-mini-table-hdr <?= $disk_alerts > 10 ? 'iad-hdr-danger' : 'iad-hdr-safe' ?>">
          <i class="fas fa-hdd"></i> Disk Usage — C: Drive
          <span class="iad-mini-table-badge">
            <?= $disk_alerts > 0
              ? '<span class="iad-status-dot iad-dot-red"></span> '.$disk_alerts.' alerts'
              : '<span class="iad-status-dot iad-dot-green"></span> All healthy' ?>
          </span>
        </div>

        <!-- Summary pills -->
        <div class="iad-disk-summary-row">
          <div class="iad-disk-pill iad-disk-pill-red">
            <b><?= $h['disk_summary']['critical'] ?></b>
            <small>Critical &lt;10%</small>
          </div>
          <div class="iad-disk-pill iad-disk-pill-amber">
            <b><?= $h['disk_summary']['warning'] ?></b>
            <small>Warning 10–15%</small>
          </div>
          <div class="iad-disk-pill iad-disk-pill-green">
            <b><?= $h['disk_summary']['healthy'] ?></b>
            <small>Healthy &gt;15%</small>
          </div>
        </div>

        <!-- Low disk list -->
        <?php if (empty($h['low_disk'])): ?>
        <div class="iad-av-all-ok"><i class="fas fa-check-circle"></i> All C: drives have sufficient space</div>
        <?php else: ?>
        <div class="iad-scroll-table-wrap" id="wrap-disk">
        <table class="iad-mini-table iad-scroll-table">
          <thead><tr><th>Computer</th><th>Free</th><th>Total</th><th>Usage</th></tr></thead>
          <tbody>
            <?php foreach ($h['low_disk'] as $i => $d):
              $fp   = (float)$d['free_pct'];
              $used = $d['totalsize'] - $d['freesize'];
              $upct = $d['totalsize'] > 0 ? round($used / $d['totalsize'] * 100) : 0;
              $crit = $fp < 10;
            ?>
            <tr class="iad-clickable-row<?= $i >= 5 ? ' iad-row-extra' : '' ?>"
                onclick="location='<?= !empty($d['computer_id']) ? $glpi_pc.(int)$d['computer_id'] : $report_url.'?search='.urlencode($d['computer_name']) ?>'">
              <td class="iad-pc-link">
                <i class="fas fa-desktop" style="color:#94a3b8;font-size:10px;margin-right:4px"></i>
                <?= htmlspecialchars($d['computer_name']) ?>
              </td>
              <td class="<?= $crit ? 'iad-t-red' : 'iad-t-amber' ?>" style="font-weight:700;font-size:11px">
                <?= fmt_mb($d['freesize']) ?>
                <small style="font-weight:400;color:#94a3b8">(<?= $fp ?>%)</small>
              </td>
              <td style="font-size:11px;color:#64748b"><?= fmt_mb($d['totalsize']) ?></td>
              <td style="min-width:90px">
                <div class="iad-disk-bar-wrap">
                  <div class="iad-disk-bar-track">
                    <div class="iad-disk-bar-fill <?= $crit ? 'iad-disk-fill-red' : 'iad-disk-fill-amber' ?>"
                         style="width:<?= $upct ?>%"></div>
                  </div>
                  <span style="font-size:10px;color:<?= $crit ? '#dc2626' : '#d97706' ?>;font-weight:700;min-width:30px;text-align:right"><?= $upct ?>%</span>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        </div><!-- /wrap-disk -->
        <?php if (count($h['low_disk']) > 5): ?>
        <div class="iad-show-more-bar">
          <button class="iad-show-more-btn" onclick="toggleRows('disk', <?= count($h['low_disk']) ?>, this)">
            <i class="fas fa-chevron-down"></i> Show all <?= count($h['low_disk']) ?> computers
          </button>
        </div>
        <?php endif; ?>
        <?php endif; ?>
      </div>


    </div><!-- /right col -->

  </div><!-- /.iad-two-col -->
</div><!-- /.iad-dash -->

<script>
const REPORT   = '<?= $report_url ?>';
const SOFTWARE = '<?= $software_url ?>';
const PALETTE  = ['#6f8bc6','#5fd68b','#d7a56c','#86bad4','#ca7373','#7abecf','#a6c778','#b38472','#7bb9cc','#84bbb6','#ad815f','#4338ca'];
const SW_PAL   = ['#957fba','#9279b9','#7fd5a0','#dfbf92','#a78bfa','#9333ea','#c084fc','#4c1d95','#e879f9','#7e22ce'];

function clickable(chart, url, param) {
    chart.options.onClick = function(e) {
        const pts = chart.getElementsAtEventForMode(e,'nearest',{intersect:true},true);
        if (pts.length) location = url+'?'+param+'='+encodeURIComponent(chart.data.labels[pts[0].index]);
    };
    chart.canvas.style.cursor = 'pointer';
    chart.update();
}

const opts_donut = { responsive:true, maintainAspectRatio:false,
    plugins:{ legend:{ position:'right', labels:{ font:{size:11}, padding:10, boxWidth:12 } } } };

const opts_bar_v = { responsive:true, maintainAspectRatio:false,
    plugins:{ legend:{display:false} },
    scales:{ x:{ ticks:{maxRotation:28,font:{size:10}}, grid:{display:false} }, y:{ grid:{color:'#f1f5f9'}, ticks:{font:{size:10}} } } };

const opts_bar_h = { responsive:true, maintainAspectRatio:false, indexAxis:'y',
    plugins:{ legend:{display:false} },
    scales:{ x:{ grid:{color:'#f1f5f9'}, ticks:{font:{size:10}} }, y:{ grid:{display:false}, ticks:{font:{size:10}} } } };

const cOS     = new Chart(document.getElementById('chartOS'),        {type:'doughnut', data:{labels:<?= $by_os_labels ?>,   datasets:[{data:<?= $by_os_data ?>,   backgroundColor:PALETTE, borderWidth:2, borderColor:'#fff'}]}, options:{...opts_donut}});
const cSt     = new Chart(document.getElementById('chartStatus'),    {type:'doughnut', data:{labels:<?= $by_stat_labels ?>, datasets:[{data:<?= $by_stat_data ?>, backgroundColor:PALETTE, borderWidth:2, borderColor:'#fff'}]}, options:{...opts_donut}});
const cDept   = new Chart(document.getElementById('chartDept'),      {type:'bar',      data:{labels:<?= $by_dept_labels ?>, datasets:[{data:<?= $by_dept_data ?>, backgroundColor:'#93b4f0', borderRadius:4}]}, options:{...opts_bar_v}});
const cMfr    = new Chart(document.getElementById('chartMfr'),       {type:'bar',      data:{labels:<?= $by_mfr_labels ?>, datasets:[{data:<?= $by_mfr_data ?>, backgroundColor:PALETTE,   borderRadius:4}]}, options:{...opts_bar_h}});
const cTopSw  = new Chart(document.getElementById('chartTopSw'),     {type:'doughnut', data:{labels:<?= $sw_top_labels ?>, datasets:[{data:<?= $sw_top_data ?>, backgroundColor:SW_PAL, borderWidth:2, borderColor:'#fff'}]}, options:{...opts_donut}});
const cPub    = new Chart(document.getElementById('chartPublisher'),  {type:'doughnut', data:{labels:<?= $sw_pub_labels ?>, datasets:[{data:<?= $sw_pub_data ?>, backgroundColor:SW_PAL, borderWidth:2, borderColor:'#fff'}]}, options:{...opts_donut}});

clickable(cOS,    REPORT,   'os');
clickable(cSt,    REPORT,   'status');
clickable(cDept,  REPORT,   'department');
clickable(cMfr,   REPORT,   'manufacturer');
clickable(cTopSw, SOFTWARE, 'software');
clickable(cPub,   SOFTWARE, 'publisher');

// ── Show/collapse extra rows ──────────────────────────────────────
function toggleRows(section, total, btn) {
    const extras = document.querySelectorAll('#wrap-' + section + ' .iad-row-extra');
    const wrap   = document.getElementById('wrap-' + section);
    const isOpen = wrap.classList.contains('iad-expanded');

    if (isOpen) {
        // Collapse
        wrap.classList.remove('iad-expanded');
        extras.forEach(r => r.style.display = 'none');
        btn.innerHTML = '<i class="fas fa-chevron-down"></i> Show all ' + total + ' ' + (section === 'old' ? 'devices' : 'computers');
    } else {
        // Expand
        wrap.classList.add('iad-expanded');
        extras.forEach(r => r.style.display = '');
        btn.innerHTML = '<i class="fas fa-chevron-up"></i> Show less';
        // Smooth scroll the wrap into view
        wrap.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

// Hide extra rows on load
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.iad-row-extra').forEach(r => r.style.display = 'none');
});
</script>

<?php Html::footer(); ?>
