<?php
require_once __DIR__.'/../db_connect.php';
require_once __DIR__.'/../includes/table_helpers.php';

$rows = $conn->query("SELECT ym, total_bookings, cancelled, cancel_rate_pct FROM vw_monthly_cancellation_rate ORDER BY ym")->fetch_all(MYSQLI_ASSOC);
foreach ($rows as &$r) {
  $r['cancel_rate_pct'] = number_format((float)$r['cancel_rate_pct'],2).'%';
}
render_table($rows, [
  'ym'=>'เดือน','total_bookings'=>'จำนวนทั้งหมด','cancelled'=>'ยกเลิก','cancel_rate_pct'=>'อัตรายกเลิก'
]);
