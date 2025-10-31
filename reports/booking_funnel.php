<?php
require_once __DIR__.'/../db_connect.php';
require_once __DIR__.'/../includes/table_helpers.php';

$rows = $conn->query("SELECT booking_status, payment_status, cnt FROM vw_booking_funnel ORDER BY booking_status, payment_status")->fetch_all(MYSQLI_ASSOC);

// หมุนเป็น Pivot: booking_status เป็นแถว, payment_status เป็นคอลัมน์
$bs = []; $ps = []; $grid = []; $maxCnt = 0;
foreach ($rows as $r) {
  $bs[$r['booking_status']] = true;
  $ps[$r['payment_status']] = true;
  $grid[$r['booking_status']][$r['payment_status']] = (int)$r['cnt'];
  if ($r['cnt'] > $maxCnt) $maxCnt = (int)$r['cnt'];
}
$bs = array_keys($bs); $ps = array_keys($ps);

echo '<div class="table-responsive"><table class="table table-sm table-bordered heat">';
echo '<thead><tr><th>จอง \\ จ่าย</th>';
foreach ($ps as $c) echo '<th>'.htmlspecialchars($c).'</th>';
echo '</tr></thead><tbody>';

foreach ($bs as $r) {
  echo '<tr><th>'.htmlspecialchars($r).'</th>';
  foreach ($ps as $c) {
    $v = $grid[$r][$c] ?? 0;
    $p = $maxCnt ? round($v*100/$maxCnt, 1) : 0;
    echo '<td data-val="'.(int)$v.'" style="--p:'.$p.'%"><strong>'.(int)$v.'</strong></td>';
  }
  echo '</tr>';
}
echo '</tbody></table></div>';
