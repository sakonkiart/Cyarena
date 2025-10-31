<?php
require_once __DIR__.'/../db_connect.php';
require_once __DIR__.'/../includes/table_helpers.php';

$code = $_GET['code'] ?? '';
$sql = "SELECT promo_code, uses_count, revenue_from_promo, first_used_at, last_used_at
        FROM vw_promotion_performance";
if ($code !== '') { $sql .= " WHERE promo_code = ?"; }

$stmt = $conn->prepare($sql);
if ($code !== '') $stmt->bind_param('s', $code);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($rows as &$r) {
    $r['revenue_from_promo'] = number_format((float)$r['revenue_from_promo'],2);
    $r['first_used_at'] = $r['first_used_at'] ? date('Y-m-d H:i', strtotime($r['first_used_at'])) : '-';
    $r['last_used_at']  = $r['last_used_at']  ? date('Y-m-d H:i', strtotime($r['last_used_at']))  : '-';
}
render_table($rows, [
  'promo_code'=>'โค้ด','uses_count'=>'จำนวนใช้',
  'revenue_from_promo'=>'รายได้ (฿)','first_used_at'=>'ใช้ครั้งแรก','last_used_at'=>'ใช้ล่าสุด'
]);
