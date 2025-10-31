<?php
require_once __DIR__.'/../db_connect.php';
require_once __DIR__.'/../includes/table_helpers.php';

$rows = $conn->query(
"SELECT employee_name, handled_bookings, revenue_approved, first_booking_at, last_booking_at
 FROM vw_employee_performance ORDER BY revenue_approved DESC"
)->fetch_all(MYSQLI_ASSOC);

foreach ($rows as &$r) {
  $r['revenue_approved'] = number_format((float)$r['revenue_approved'],2);
  $r['first_booking_at'] = $r['first_booking_at'] ? date('Y-m-d', strtotime($r['first_booking_at'])):'-';
  $r['last_booking_at']  = $r['last_booking_at']  ? date('Y-m-d', strtotime($r['last_booking_at'])) :'-';
}
render_table($rows, [
  'employee_name'=>'พนักงาน','handled_bookings'=>'จำนวนบิลที่ดูแล',
  'revenue_approved'=>'รายได้ยืนยัน (฿)','first_booking_at'=>'บิลแรก','last_booking_at'=>'บิลล่าสุด'
]);
