<?php
require_once __DIR__.'/../db_connect.php';
require_once __DIR__.'/../includes/table_helpers.php';

$start = $_GET['start'] ?? '';
$end   = $_GET['end']   ?? '';
$venue = $_GET['venue'] ?? '';

$sql = "SELECT VenueName, usage_date, booked_hours, open_hours, utilization_pct
        FROM vw_venue_utilization_daily WHERE 1=1";
$params=[]; $types='';
if ($start && $end) { $sql.=" AND usage_date BETWEEN ? AND ?"; $params[]=$start; $params[]=$end; $types.='ss'; }
if ($venue) { $sql.=" AND VenueName = ?"; $params[]=$venue; $types.='s'; }
$sql.=" ORDER BY usage_date DESC, VenueName";

$stmt=$conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($rows as &$r) {
    $r['booked_hours'] = number_format((float)$r['booked_hours'],2);
    $r['open_hours']   = number_format((float)$r['open_hours'],2);
    $r['utilization_pct'] = number_format((float)$r['utilization_pct'],2).'%';
}
render_table($rows, [
  'usage_date'=>'วันที่','VenueName'=>'สนาม','booked_hours'=>'ชั่วโมงถูกจอง',
  'open_hours'=>'ชั่วโมงเปิด','utilization_pct'=>'อัตราใช้ (%)'
]);
