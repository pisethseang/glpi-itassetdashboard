<?php
/** Summary autocomplete: bounded, authenticated JSON search. */
include('../../../inc/includes.php');
Session::checkRight('computer', READ);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
$term = trim((string)($_GET['q'] ?? ''));
$term = mb_substr($term, 0, 80);
if (mb_strlen($term) < 2) {
    echo json_encode(['items' => []]);
    exit;
}
global $DB;
try {
    // Search only GLPI software titles, not every installed-software row.
    // Limited output and client debounce keep requests lightweight.
    $pattern = $DB->escape($term);
    $result = $DB->doQuery("SELECT DISTINCT name FROM glpi_softwares WHERE is_deleted=0 AND name LIKE '%$pattern%' ORDER BY name LIMIT 25");
    $items = [];
    if ($result === false) {
        http_response_code(500);
        echo json_encode(['items' => []]);
        exit;
    }
    while ($row = $DB->fetchAssoc($result)) {
        $items[] = (string)$row['name'];
    }
    echo json_encode(['items' => $items], JSON_INVALID_UTF8_SUBSTITUTE);
} catch (\Throwable $e) {
    error_log('[itassetdashboard application search] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['items' => []]);
}
