<?php
require_once __DIR__.'/../db_connect.php';
require_once __DIR__.'/../includes/table_helpers.php';

$sql = "SELECT VenueID, VenueName, revenue_90d, bookings_90d, rn FROM vw_top10_venues_by_revenue";
$rows = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
foreach ($rows as &$r) {
    $r['revenue_90d'] = number_format((float)$r['revenue_90d'], 2);
}
render_table($rows, [
  'rn'=>'อันดับ','VenueName'=>'สนาม','revenue_90d'=>'รายได้ 90 วัน (฿)','bookings_90d'=>'จำนวนบิล'
]);
