<?php
require_once __DIR__.'/../db_connect.php';
require_once __DIR__.'/../includes/table_helpers.php';

$limit = (int)($_GET['limit'] ?? 50);
$sql = "SELECT customer_name, total_bookings, total_revenue, avg_order_value, first_booking_at, last_booking_at, recency_days
        FROM vw_customer_ltv ORDER BY total_revenue DESC LIMIT ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $limit);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($rows as &$r) {
  $r['total_revenue']   = number_format((float)$r['total_revenue'],2);
  $r['avg_order_value'] = number_format((float)$r['avg_order_value'],2);
  $r['first_booking_at']= $r['first_booking_at'] ? date('Y-m-d', strtotime($r['first_booking_at'])):'-';
  $r['last_booking_at'] = $r['last_booking_at']  ? date('Y-m-d', strtotime($r['last_booking_at'])):'-';
}
render_table($rows, [
  'customer_name'=>'ลูกค้า','total_bookings'=>'จำนวนบิล','total_revenue'=>'รายได้ (฿)',
  'avg_order_value'=>'บิลเฉลี่ย (฿)','first_booking_at'=>'ครั้งแรก','last_booking_at'=>'ครั้งล่าสุด','recency_days'=>'ไม่ซื้อมา (วัน)'
]);
