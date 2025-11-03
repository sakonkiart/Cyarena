<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'db_connect.php';

if (!isset($_GET['venue_id']) || !is_numeric($_GET['venue_id'])) {
    die("❌ ไม่พบรหัสสนาม");
}

$venue_id = (int)$_GET['venue_id'];

// ✅ ดึงข้อมูลสนาม + คะแนนเฉลี่ย
$sql = "SELECT v.*, vt.TypeName, 
               IFNULL(ROUND(AVG(r.Rating),1), 0) AS AvgRating, 
               COUNT(r.ReviewID) AS ReviewCount
        FROM Tbl_Venue v
        JOIN Tbl_Venue_Type vt ON v.VenueTypeID = vt.VenueTypeID
        LEFT JOIN Tbl_Review r ON v.VenueID = r.VenueID
        WHERE v.VenueID = ?
        GROUP BY v.VenueID";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $venue_id);
$stmt->execute();
$venue = $stmt->get_result()->fetch_assoc();

// ✅ ดึงรีวิวทั้งหมดของสนามนี้
$sql_reviews = "SELECT r.*, c.FirstName
                FROM Tbl_Review r
                JOIN Tbl_Customer c ON r.CustomerID = c.CustomerID
                WHERE r.VenueID = ?
                ORDER BY r.ReviewID DESC";
$stmt = $conn->prepare($sql_reviews);
$stmt->bind_param("i", $venue_id);
$stmt->execute();
$reviews = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>รีวิวทั้งหมด - <?php echo htmlspecialchars($venue['VenueName']); ?></title>
<style>
body {
  font-family: "Prompt", sans-serif;
  background: #f3f4f6;
  margin: 0;
  color: #1e293b;
}
.container {
  max-width: 900px;
  margin: 40px auto;
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 4px 20px rgba(0,0,0,0.08);
  padding: 35px 40px;
}
h1 {
  color: #0f172a;
  margin-bottom: 5px;
}
.sub-title {
  color: #64748b;
  margin-bottom: 20px;
}
.avg-rating {
  background: #fff7ed;
  color: #92400e;
  padding: 10px 18px;
  border-radius: 10px;
  display: inline-block;
  margin-bottom: 25px;
  font-weight: 600;
}
.review-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.review-card {
  display: flex;
  align-items: flex-start;
  gap: 15px;
  background: #f9fafb;
  border-radius: 12px;
  padding: 18px 20px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.05);
  transition: transform 0.2s;
}
.review-card:hover {
  transform: translateY(-2px);
}
.avatar {
  width: 50px;
  height: 50px;
  border-radius: 50%;
  background: #3b82f6;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  font-weight: bold;
  text-transform: uppercase;
  flex-shrink: 0;
}
.review-content {
  flex: 1;
}
.review-content strong {
  font-size: 16px;
  color: #0f172a;
}
.rating {
  color: #facc15;
  font-weight: 600;
  font-size: 15px;
  margin-left: 5px;
}
.comment {
  color: #475569;
  margin: 6px 0 0;
  line-height: 1.6;
}
.no-review {
  color: #94a3b8;
  font-style: italic;
}
.btn {
  display: inline-block;
  padding: 10px 16px;
  background: #3b82f6;
  color: white;
  border-radius: 6px;
  text-decoration: none;
  font-weight: 600;
  margin-top: 25px;
}
.btn:hover {
  background: #2563eb;
}
</style>
</head>
<body>

<div class="container">
  <h1><?php echo htmlspecialchars($venue['VenueName']); ?></h1>
  <p class="sub-title"><?php echo htmlspecialchars($venue['TypeName']); ?></p>

  <div class="avg-rating">
    ⭐ ค่าเฉลี่ย: <?php echo $venue['AvgRating']; ?>/5 
    (จาก <?php echo $venue['ReviewCount']; ?> รีวิว)
  </div>

  <h2>💬 รีวิวทั้งหมด</h2>

  <?php if ($reviews->num_rows == 0): ?>
    <p class="no-review">ยังไม่มีรีวิวสำหรับสนามนี้</p>
  <?php else: ?>
    <div class="review-list">
      <?php while ($r = $reviews->fetch_assoc()): ?>
        <?php $firstLetter = strtoupper(substr($r['FirstName'], 0, 1)); ?>
        <div class="review-card">
          <div class="avatar"><?php echo $firstLetter; ?></div>
          <div class="review-content">
            <strong><?php echo htmlspecialchars($r['FirstName']); ?></strong>
            <span class="rating">⭐ <?php echo $r['Rating']; ?>/5</span>
            <p class="comment"><?php echo nl2br(htmlspecialchars($r['Comment'])); ?></p>
          </div>
        </div>
      <?php endwhile; ?>
    </div>
  <?php endif; ?>

  <a href="venue_detail.php?venue_id=<?php echo $venue_id; ?>" class="btn">⬅ กลับ</a>
</div>

</body>
</html>
