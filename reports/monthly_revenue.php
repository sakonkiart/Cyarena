<?php
require_once __DIR__.'/../db_connect.php';
require_once __DIR__.'/../includes/table_helpers.php';

$year = $_GET['year'] ?? '';
$sql = "SELECT ym, revenue, bookings, avg_order_value FROM vw_monthly_revenue";
$params = []; $types = '';
if ($year !== '') { $sql .= " WHERE ym LIKE CONCAT(?, '-%')"; $params[] = $year; $types .= 's'; }
$sql .= " ORDER BY ym";

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($rows as &$r) {
    $r['revenue'] = number_format((float)$r['revenue'], 2);
    $r['avg_order_value'] = number_format((float)$r['avg_order_value'], 2);
}
render_table($rows, [
  'ym' => 'เดือน', 'revenue' => 'รายได้ (฿)',
  'bookings' => 'จำนวนบิล', 'avg_order_value' => 'บิลเฉลี่ย (฿)'
]);
