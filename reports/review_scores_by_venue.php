<?php
require_once __DIR__.'/../db_connect.php';
require_once __DIR__.'/../includes/table_helpers.php';

$sql = "SELECT VenueName, reviews_count, avg_rating, first_review_at, last_review_at
        FROM vw_review_scores_by_venue ORDER BY avg_rating DESC, reviews_count DESC";
$rows = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
foreach ($rows as &$r) {
  $r['avg_rating'] = number_format((float)$r['avg_rating'],2);
  $r['first_review_at'] = $r['first_review_at'] ? date('Y-m-d', strtotime($r['first_review_at'])) : '-';
  $r['last_review_at']  = $r['last_review_at']  ? date('Y-m-d', strtotime($r['last_review_at']))  : '-';
}
render_table($rows, [
  'VenueName'=>'สนาม','reviews_count'=>'จำนวนรีวิว','avg_rating'=>'คะแนนเฉลี่ย',
  'first_review_at'=>'รีวิวแรก','last_review_at'=>'รีวิวล่าสุด'
]);
