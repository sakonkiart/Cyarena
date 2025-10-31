<?php
require_once __DIR__.'/../db_connect.php';
require_once __DIR__.'/../includes/table_helpers.php';

$topOnly = isset($_GET['top1']);
$sql = "SELECT TypeName, hour_of_day, bookings, rn_in_type FROM vw_peak_hours_by_type";
if ($topOnly) $sql .= " WHERE rn_in_type = 1";
$sql .= " ORDER BY TypeName, bookings DESC";

$rows = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
render_table($rows, [
  'TypeName'=>'ประเภทสนาม','hour_of_day'=>'ชั่วโมง','bookings'=>'จำนวนบิล','rn_in_type'=>'อันดับในประเภท'
]);
